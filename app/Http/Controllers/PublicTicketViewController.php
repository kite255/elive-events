<?php

namespace App\Http\Controllers;

use App\Services\Tickets\Rendering\PublicTicketAccess;
use App\Services\Tickets\Rendering\TicketOutputService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Throwable;

class PublicTicketViewController extends Controller
{
    public function show(
        string $token,
        PublicTicketAccess $ticketAccess,
        TicketOutputService $ticketOutput
    ): Response {
        $ticket = $ticketAccess
            ->findEligible($token);

        try {
            $renderedPages = $ticketOutput
                ->renderPages($ticket);
        } catch (Throwable $exception) {
            Log::warning('Designed ticket rendering failed; using standard ticket.', [
                'ticket_number' => $ticket->ticket_number,
                'exception' => $exception::class,
            ]);

            $renderedPages = [];
        }

        $qrCode = null;

        if ($renderedPages === []) {
            $qrCode = QrCode::format('svg')
                ->size(320)
                ->margin(1)
                ->generate($ticket->qr_token_encrypted);
        }

        return response()->view(
            'public.tickets.show',
            [
                'ticket' => $ticket,
                'event' => $ticket->event,
                'order' => $ticket->order,
                'qrCode' => $qrCode,
                'renderedPages' => $renderedPages,
            ]
        )->header('Cache-Control', 'private, no-store');
    }
}
