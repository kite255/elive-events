<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Illuminate\Contracts\View\View;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class PublicTicketViewController extends Controller
{
    public function show(
        string $token
    ): View {
        $ticket =
            Ticket::query()
                ->with([
                    'event.organization',
                    'ticketType',
                    'order',
                ])
                ->where(
                    'public_token',
                    $token
                )
                ->firstOrFail();

        /*
         * Cancelled and refunded tickets must never
         * be publicly usable.
         */
        abort_if(
            in_array(
                $ticket->status,
                [
                    Ticket::STATUS_CANCELLED,
                    Ticket::STATUS_REFUNDED,
                ],
                true
            ),
            404
        );

        /*
         * Ticket access is only valid when its
         * parent ticket order is paid.
         */
        abort_unless(
            $ticket->order
                && $ticket->order->isPaid(),
            404
        );

        $rawQrToken =
            $ticket->qr_token_encrypted;

        abort_if(
            blank($rawQrToken),
            404
        );

        /*
         * IMPORTANT:
         * The QR contains only the secure scan
         * credential.
         *
         * It does not expose:
         * - ticket database ID
         * - ticket public token
         * - qr_token_hash
         */
        $qrCode =
            QrCode::format('svg')
                ->size(320)
                ->margin(1)
                ->generate(
                    $rawQrToken
                );

        return view(
            'public.tickets.show',
            [
                'ticket' =>
                    $ticket,

                'event' =>
                    $ticket->event,

                'order' =>
                    $ticket->order,

                'qrCode' =>
                    $qrCode,
            ]
        );
    }
}