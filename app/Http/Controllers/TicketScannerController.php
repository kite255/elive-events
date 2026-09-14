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

        /*
         * Resolve the ticket only for authorization.
         *
         * We never query using the raw QR value itself. The raw credential
         * is hashed exactly as it is in TicketCheckInService.
         */
        $ticket = Ticket::query()
            ->with('event')
            ->where(
                'qr_token_hash',
                hash(
                    'sha256',
                    $validated['qr_token']
                )
            )
            ->first();

        /*
         * If the QR belongs to a real ticket, only a user who is allowed
         * to perform check-in for that event may continue.
         *
         * Event::canBeCheckedInBy() already contains the project's
         * super-admin / event-role authorization rules.
         */
        if (
            $ticket
            && ! $ticket->event?->canBeCheckedInBy(
                $request->user()
            )
        ) {
            abort(403);
        }

        $result = $ticketCheckInService
            ->checkInByQrToken(
                $validated['qr_token']
            );

        return response()->json(
            $result
        );
    }
}
