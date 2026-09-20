<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Services\Tickets\TicketCheckInService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketScannerController extends Controller
{
    public function scan(
        Request $request,
        TicketCheckInService $ticketCheckInService
    ): JsonResponse {
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
                $request->user()
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

        return response()->json(
            $result
        );
    }
}
