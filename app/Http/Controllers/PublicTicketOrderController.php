<?php

namespace App\Http\Controllers;

use App\Models\TicketOrder;
use Illuminate\Contracts\View\View;

class PublicTicketOrderController extends Controller
{
    public function show(
        string $token
    ): View {
        $order =
            TicketOrder::query()
                ->with([
                    'event.organization',
                    'items.ticketType',
                    'tickets.ticketType',
                ])
                ->where(
                    'public_token',
                    $token
                )
                ->firstOrFail();

        /*
         * Never expose tickets for unpaid,
         * expired, cancelled, or otherwise
         * unresolved orders.
         */
        abort_unless(
            $order->isPaid(),
            404
        );

        /*
         * Only tickets belonging to this order
         * are passed to the public view.
         *
         * qr_token_hash and qr_token_encrypted
         * remain hidden model attributes and are
         * not required by this page.
         */
        $tickets =
            $order->tickets
                ->sortBy('id')
                ->values();

        return view(
            'public.tickets.order',
            [
                'order' =>
                    $order,

                'event' =>
                    $order->event,

                'tickets' =>
                    $tickets,
            ]
        );
    }
}