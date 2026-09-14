<?php

namespace App\Services\Tickets;

use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketOrderItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class TicketIssuanceService
{
    /**
     * Issue all tickets for a paid ticket order.
     *
     * This method is idempotent:
     * calling it more than once will not create duplicates.
     *
     * @return Collection<int, Ticket>
     */
    public function issueForOrder(
        TicketOrder $order
    ): Collection {
        return DB::transaction(
            function () use ($order): Collection {
                $lockedOrder =
                    TicketOrder::query()
                        ->with([
                            'items.ticketType',
                            'tickets',
                        ])
                        ->lockForUpdate()
                        ->findOrFail(
                            $order->getKey()
                        );

                if (
                    $lockedOrder->status
                    !== TicketOrder::STATUS_PAID
                ) {
                    throw new RuntimeException(
                        'Tickets can only be issued for a paid ticket order.'
                    );
                }

                /*
                 * If the expected number of tickets already
                 * exists, return them instead of issuing again.
                 */
                $existingTickets =
                    Ticket::query()
                        ->where(
                            'ticket_order_id',
                            $lockedOrder->getKey()
                        )
                        ->orderBy('id')
                        ->get();

                if (
                    $existingTickets->count()
                    === (int) $lockedOrder->quantity
                ) {
                    return $existingTickets;
                }

                /*
                 * Partial issuance should never be silently
                 * continued because that could hide a previous
                 * failed fulfillment.
                 */
                if ($existingTickets->isNotEmpty()) {
                    throw new RuntimeException(
                        'Ticket order has been partially issued and requires review.'
                    );
                }

                $issuedTickets =
                    collect();

                /** @var TicketOrderItem $item */
                foreach ($lockedOrder->items as $item) {
                    if (! $item->ticketType) {
                        throw new RuntimeException(
                            'Ticket order item is missing its ticket type.'
                        );
                    }

                    for (
                        $position = 1;
                        $position <= (int) $item->quantity;
                        $position++
                    ) {
                        $rawQrToken =
                            Str::random(64);

                        $ticket =
                            Ticket::query()->create([
                                'event_id' =>
                                    $lockedOrder->event_id,

                                'ticket_order_id' =>
                                    $lockedOrder->getKey(),

                                'ticket_order_item_id' =>
                                    $item->getKey(),

                                'ticket_type_id' =>
                                    $item->ticket_type_id,

                                'attendee_id' =>
                                    null,

                                'ticket_number' =>
                                    $this->generateTicketNumber(
                                        $lockedOrder,
                                        $item,
                                        $position
                                    ),

                                'public_token' =>
                                    Str::random(40),

                                /*
                                 * Never store the raw QR secret.
                                 */
                                'qr_token_hash' =>
                                    hash(
                                        'sha256',
                                        $rawQrToken
                                    ),

                                'holder_name' =>
                                    $lockedOrder->buyer_name,

                                'holder_phone' =>
                                    $lockedOrder->buyer_phone,

                                'holder_email' =>
                                    $lockedOrder->buyer_email,

                                'price' =>
                                    $item->unit_price,

                                'currency' =>
                                    strtoupper(
                                        (string) $lockedOrder->currency
                                    ),

                                'status' =>
                                    Ticket::STATUS_ISSUED,

                                'issued_at' =>
                                    now(),

                                'metadata' => [
                                    'order_number' =>
                                        $lockedOrder->order_number,

                                    'item_position' =>
                                        $position,

                                    /*
                                     * Temporary source token for later
                                     * QR rendering/delivery design.
                                     *
                                     * Do not persist raw token here.
                                     */
                                    'qr_token_generated' =>
                                        true,
                                ],
                            ]);

                        /*
                         * Keep raw QR token only in memory.
                         *
                         * Later, when we build ticket rendering,
                         * this value can be passed directly to the
                         * QR generator before being discarded.
                         */
                        $ticket->setAttribute(
                            'raw_qr_token',
                            $rawQrToken
                        );

                        $issuedTickets->push(
                            $ticket
                        );
                    }
                }

                if (
                    $issuedTickets->count()
                    !== (int) $lockedOrder->quantity
                ) {
                    throw new RuntimeException(
                        'Issued ticket quantity does not match order quantity.'
                    );
                }

                return $issuedTickets;
            },
            attempts: 3
        );
    }

    private function generateTicketNumber(
        TicketOrder $order,
        TicketOrderItem $item,
        int $position
    ): string {
        $typeCode =
            strtoupper(
                (string) (
                    $item->ticketType?->code
                    ?: 'TKT'
                )
            );

        do {
            $number = sprintf(
                'ELV-%s-%s-%02d-%s',
                $typeCode,
                $order->getKey(),
                $position,
                Str::upper(
                    Str::random(6)
                )
            );
        } while (
            Ticket::query()
                ->where(
                    'ticket_number',
                    $number
                )
                ->exists()
        );

        return $number;
    }
}
