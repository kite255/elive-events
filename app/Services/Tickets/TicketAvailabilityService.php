<?php

namespace App\Services\Tickets;

use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketType;

class TicketAvailabilityService
{
    /**
     * Quantity currently held by unpaid, non-expired orders.
     */
    public function reservedQuantity(
        TicketType $ticketType
    ): int {
        return (int) $ticketType
            ->orderItems()
            ->whereHas(
                'order',
                function ($query): void {
                    $query
                        ->whereIn(
                            'status',
                            [
                                TicketOrder::STATUS_PENDING,
                                TicketOrder::STATUS_PROCESSING,
                            ]
                        )
                        ->whereNotNull('expires_at')
                        ->where(
                            'expires_at',
                            '>',
                            now()
                        );
                }
            )
            ->sum('quantity');
    }

    /**
     * Quantity already issued/sold.
     */
    public function soldQuantity(
        TicketType $ticketType
    ): int {
        return (int) $ticketType
            ->tickets()
            ->whereNotIn(
                'status',
                [
                    Ticket::STATUS_CANCELLED,
                    Ticket::STATUS_REFUNDED,
                ]
            )
            ->count();
    }

    /**
     * Remaining quantity available for sale.
     *
     * Null means unlimited capacity.
     */
    public function availableQuantity(
        TicketType $ticketType
    ): ?int {
        if (
            $ticketType->capacity === null
            || (int) $ticketType->capacity <= 0
        ) {
            return null;
        }

        return max(
            0,
            (int) $ticketType->capacity
                - $this->soldQuantity($ticketType)
                - $this->reservedQuantity($ticketType)
        );
    }

    public function canReserve(
        TicketType $ticketType,
        int $quantity
    ): bool {
        if ($quantity <= 0) {
            return false;
        }

        if (
            $ticketType->capacity === null
            || (int) $ticketType->capacity <= 0
        ) {
            return true;
        }

        return $quantity <=
            ($this->availableQuantity($ticketType) ?? 0);
    }
}
