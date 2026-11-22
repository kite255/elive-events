<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Ticket;
use App\Services\Tickets\TicketCheckInService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class TicketScannerController extends Controller
{
    public function scan(
        Request $request,
        TicketCheckInService $ticketCheckInService
    ): JsonResponse {
        $user = $request->user();
        $gateScanner = $request->routeIs('gate.ticket-scanner.scan');

        abort_unless($gateScanner ? $user->canUseGateScanner() : ! $user->is_gate_staff, 403);

        $validated = $request->validate([
            'qr_token' => [
                'required',
                'string',
                'max:255',
            ],
        ]);

        $credential = trim(
            $validated['qr_token']
        );

        /*
        |--------------------------------------------------------------------------
        | Resolve Ticket
        |--------------------------------------------------------------------------
        |
        | The scanner accepts two input formats:
        |
        | 1. Secure QR credential
        |    - Used by camera QR scanning
        |    - The raw credential is never queried directly
        |    - It is hashed using SHA-256 before lookup
        |
        | 2. Human-readable ticket number
        |    - Used only as a manual fallback
        |    - Example: ELV-REG-8-01-I5J5S2
        |
        | Secure QR scanning remains the primary entry method.
        |
        */

        $ticket = Ticket::query()
            ->with('event')
            ->where(
                'qr_token_hash',
                hash(
                    'sha256',
                    $credential
                )
            )
            ->first();

        /*
        |--------------------------------------------------------------------------
        | Manual Ticket Number Fallback
        |--------------------------------------------------------------------------
        |
        | If the submitted value is not a valid QR credential, attempt an
        | exact lookup using the human-readable ticket number.
        |
        | We deliberately use an exact match. Partial ticket-number searching
        | does not belong in the check-in endpoint.
        |
        */

        if (! $ticket) {
            $ticket = Ticket::query()
                ->with('event')
                ->where(
                    'ticket_number',
                    $credential
                )
                ->first();
        }

        /*
        |--------------------------------------------------------------------------
        | Authorization
        |--------------------------------------------------------------------------
        |
        | If the input resolves to a real ticket, the authenticated user must
        | be authorized to perform check-in for that ticket's event.
        |
        | This applies equally to:
        |
        | - Camera QR scans
        | - Manual QR credentials
        | - Manual ticket-number entry
        |
        */

        if (
            $ticket
            && ! $ticket->event?->canBeCheckedInBy(
                $user
            )
        ) {
            abort(403);
        }

        /*
        |--------------------------------------------------------------------------
        | Determine Secure Check-in Credential
        |--------------------------------------------------------------------------
        |
        | TicketCheckInService remains the single source of truth for:
        |
        | - ticket validation
        | - duplicate-entry protection
        | - database locking
        | - ticket_check_ins creation
        | - marking tickets as used
        |
        | If the officer entered a ticket number, we use the ticket's
        | encrypted QR credential after Laravel decrypts it through the model
        | cast. The service therefore still performs the exact same secure
        | QR-token check-in flow.
        |
        */

        $checkInCredential = $credential;

        if (
            $ticket
            && hash(
                'sha256',
                $credential
            ) !== $ticket->qr_token_hash
        ) {
            $checkInCredential =
                $ticket->qr_token_encrypted;
        }

        /*
        |--------------------------------------------------------------------------
        | Perform Check-in
        |--------------------------------------------------------------------------
        */

        $result = $ticketCheckInService
            ->checkInByQrToken(
                $checkInCredential
            );

        return $this->scannerResponse($result, $gateScanner ? ['holder_name', 'ticket_number'] : null);
    }

    public function showShared(Request $request, Event $event): Response
    {
        abort_if($event->status === Event::STATUS_CANCELLED, 403);

        return response()->view('gate.shared-ticket-scanner', [
            'scanUrl' => $request->getRequestUri(),
            'assignedEvent' => $event->name,
        ], 200, [
            'Cache-Control' => 'private, no-store',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    public function scanShared(Request $request, Event $event, TicketCheckInService $service): JsonResponse
    {
        abort_if($event->status === Event::STATUS_CANCELLED, 403);

        if ($request->has('undo_token')) {
            return $this->undoShared($request, $event, $service);
        }

        $validated = $request->validate(['qr_token' => ['required', 'string', 'max:255']]);
        $result = $service->checkInByCredential($validated['qr_token'], $event->id);

        if ($result['success']) {
            $undoToken = Str::random(48);
            $request->session()->put('shared_scanner_undo', [
                'token' => $undoToken,
                'signature' => $request->query('signature'),
                'event_id' => $event->id,
                'ticket_id' => $result['ticket']->id,
                'check_in_id' => $result['check_in_id'],
            ]);
            $result['undo_token'] = $undoToken;
        }

        return $this->scannerResponse($result, ['holder_name', 'ticket_number'])->header('Cache-Control', 'private, no-store');
    }

    private function undoShared(Request $request, Event $event, TicketCheckInService $service): JsonResponse
    {
        $validated = $request->validate(['undo_token' => ['required', 'string', 'size:48']]);
        $undo = $request->session()->get('shared_scanner_undo');

        abort_unless(
            $undo
                && $undo['event_id'] === $event->id
                && hash_equals($undo['signature'], $request->query('signature'))
                && hash_equals($undo['token'], $validated['undo_token']),
            403,
        );

        $undone = $service->undoCheckIn($event->id, $undo['ticket_id'], $undo['check_in_id']);
        $request->session()->forget('shared_scanner_undo');

        return response()->json([
            'success' => $undone,
            'status' => $undone ? 'check_in_undone' : 'check_in_changed',
            'message' => $undone
                ? 'This ticket is ready to scan again.'
                : 'This check-in has already changed. Ask an event administrator to review it.',
        ], $undone ? 200 : 409)->header('Cache-Control', 'private, no-store');
    }

    private function scannerResponse(array $result, ?array $ticketFields): JsonResponse
    {
        $scannedTicket = $result['ticket'] ?? null;

        if ($scannedTicket instanceof Ticket) {
            $result['ticket_type'] = $scannedTicket->ticketType?->name;

            if ($checkedInAt = $result['checked_in_at'] ?? null) {
                $result['checked_in_time_eat'] = Carbon::parse($checkedInAt)
                    ->timezone('Africa/Dar_es_Salaam')
                    ->format('d M Y, H:i').' EAT';
            }
        }

        if ($ticketFields !== null && $scannedTicket instanceof Ticket) {
            $result['ticket'] = $scannedTicket->only($ticketFields);
        }

        unset($result['check_in_id']);

        return response()->json($result);
    }
}
