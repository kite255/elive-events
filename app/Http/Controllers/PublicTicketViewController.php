<?php

namespace App\Http\Controllers;

use App\Services\Tickets\Rendering\PublicTicketAccess;
use Illuminate\Contracts\View\View;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class PublicTicketViewController extends Controller
{
    public function show(
        string $token,
        PublicTicketAccess $ticketAccess
    ): View {
        $ticket = $ticketAccess
            ->findEligible($token);

        $rawQrToken =
            $ticket->qr_token_encrypted;

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
