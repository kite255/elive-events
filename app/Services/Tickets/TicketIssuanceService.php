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
                 * Partial issuance must never be silently
                 * continued.
                 *
                 * This could hide a failed previous
                 * fulfillment and create inconsistent
                 * ticket quantities.
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
                        /*
                         * This is the actual gate-entry
                         * credential.
                         *
                         * A different random value is generated
                         * for every issued ticket.
                         */
                        $rawQrToken =
                            Str::random(64);

                        /*
                         * Store:
                         *
                         * 1. encrypted raw token
                         *    -> allows QR regeneration later
                         *
                         * 2. SHA-256 hash
                         *    -> scanner/database lookup
                         *
                         * The encrypted model cast on Ticket
                         * ensures qr_token_encrypted is never
                         * stored as plaintext.
                         */
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

                                /*
                                 * Public page identifier.
                                 *
                                 * This is deliberately separate
                                 * from the QR scan credential.
                                 */
                                'public_token' =>
                                    $this->generatePublicToken(),

                                /*
                                 * Laravel encrypts this value
                                 * automatically through the
                                 * Ticket model encrypted cast.
                                 *
                                 * This lets eLive regenerate
                                 * the QR code later.
                                 */
                                'qr_token_encrypted' =>
                                    $rawQrToken,

                                /*
                                 * Scanner lookup uses a one-way
                                 * SHA-256 hash.
                                 *
                                 * Never scan against the
                                 * public_token.
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
                                        (string)
                                        $lockedOrder->currency
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
                                     * This only records that a
                                     * QR credential was generated.
                                     *
                                     * The raw QR credential is
                                     * never stored inside metadata.
                                     */
                                    'qr_token_generated' =>
                                        true,
                                ],
                            ]);

                        /*
                         * Do NOT attach the raw QR token as a
                         * normal Eloquent attribute.
                         *
                         * A custom attribute such as
                         * raw_qr_token would become dirty and
                         * Eloquent could later try to persist it
                         * to the tickets table.
                         *
                         * The recoverable credential is already
                         * safely available through the encrypted
                         * qr_token_encrypted attribute.
                         */
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

    /**
     * Generate a unique public token for a ticket.
     *
     * This token is used only for secure public
     * ticket-page access.
     *
     * It is NOT the gate-entry QR credential.
     */
    private function generatePublicToken(): string
    {
        do {
            $token =
                Str::random(40);
        } while (
            Ticket::query()
                ->where(
                    'public_token',
                    $token
                )
                ->exists()
        );

        return $token;
    }

    /**
     * Generate a unique human-readable ticket number.
     */
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
            $number =
                sprintf(
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