<?php

namespace App\Services\Tickets;

use App\Models\Event;
use App\Models\EventTicketSetting;
use App\Models\TicketOrder;
use App\Models\TicketOrderItem;
use App\Models\TicketType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TicketOrderService
{
    public function __construct(
        private readonly TicketAvailabilityService $availability
    ) {
    }

    /**
     * Create a temporary ticket reservation/order.
     *
     * @param array{
     *     name: string,
     *     phone?: string|null,
     *     email?: string|null
     * } $buyer
     * @param array<int, array{
     *     ticket_type_id: int,
     *     quantity: int
     * }> $items
     */
    public function createOrder(
        Event $event,
        array $buyer,
        array $items
    ): TicketOrder {
        if (empty($items)) {
            throw ValidationException::withMessages([
                'tickets' => 'Select at least one ticket.',
            ]);
        }

        return DB::transaction(
            function () use (
                $event,
                $buyer,
                $items
            ): TicketOrder {
                $settings = EventTicketSetting::query()
                    ->where(
                        'event_id',
                        $event->getKey()
                    )
                    ->first();

                if (
                    $settings !== null
                    && ! $settings->salesAreOpen()
                ) {
                    throw ValidationException::withMessages([
                        'tickets' =>
                            'Ticket sales are currently closed for this event.',
                    ]);
                }

                $reservationMinutes =
                    $settings?->reservationMinutes()
                    ?? EventTicketSetting::DEFAULT_RESERVATION_MINUTES;

                $maxTicketsPerOrder =
                    $settings?->maxTicketsPerOrder()
                    ?? EventTicketSetting::DEFAULT_MAX_TICKETS_PER_ORDER;

                /*
                |--------------------------------------------------------------------------
                | Normalize & Validate Requested Items
                |--------------------------------------------------------------------------
                */

                $normalizedItems = collect($items)
                    ->map(
                        fn (array $item): array => [
                            'ticket_type_id' =>
                                (int) (
                                    $item['ticket_type_id']
                                    ?? 0
                                ),

                            'quantity' =>
                                (int) (
                                    $item['quantity']
                                    ?? 0
                                ),
                        ]
                    )
                    ->filter(
                        fn (array $item): bool =>
                            $item['ticket_type_id'] > 0
                            && $item['quantity'] > 0
                    )
                    ->groupBy('ticket_type_id')
                    ->map(
                        fn ($group): array => [
                            'ticket_type_id' =>
                                (int) $group
                                    ->first()[
                                        'ticket_type_id'
                                    ],

                            'quantity' =>
                                (int) $group
                                    ->sum('quantity'),
                        ]
                    )
                    ->values();

                if ($normalizedItems->isEmpty()) {
                    throw ValidationException::withMessages([
                        'tickets' =>
                            'Select at least one valid ticket.',
                    ]);
                }

                $totalQuantity = (int)
                    $normalizedItems->sum('quantity');

                if (
                    $totalQuantity >
                    $maxTicketsPerOrder
                ) {
                    throw ValidationException::withMessages([
                        'tickets' =>
                            "A maximum of {$maxTicketsPerOrder} tickets may be purchased in one order.",
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Lock Ticket Types
                |--------------------------------------------------------------------------
                |
                | Lock in a deterministic ID order to reduce deadlock risk when
                | several customers attempt to buy the same ticket types.
                |
                */

                $ticketTypeIds = $normalizedItems
                    ->pluck('ticket_type_id')
                    ->sort()
                    ->values();

                $ticketTypes = TicketType::query()
                    ->whereIn(
                        'id',
                        $ticketTypeIds
                    )
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                if (
                    $ticketTypes->count()
                    !== $ticketTypeIds->count()
                ) {
                    throw ValidationException::withMessages([
                        'tickets' =>
                            'One or more selected ticket types no longer exist.',
                    ]);
                }

                $subtotal = 0.0;
                $preparedItems = [];

                foreach (
                    $normalizedItems as $requestedItem
                ) {
                    /** @var TicketType $ticketType */
                    $ticketType = $ticketTypes->get(
                        $requestedItem[
                            'ticket_type_id'
                        ]
                    );

                    $quantity =
                        $requestedItem['quantity'];

                    if (
                        (int) $ticketType->event_id
                        !== (int) $event->getKey()
                    ) {
                        throw ValidationException::withMessages([
                            'tickets' =>
                                'A selected ticket type does not belong to this event.',
                        ]);
                    }

                    if (
                        ! $ticketType->is_active
                        || ! $ticketType->is_public
                    ) {
                        throw ValidationException::withMessages([
                            'tickets' =>
                                "{$ticketType->name} is not currently available.",
                        ]);
                    }

                    if (
                        $ticketType->sales_start_at !== null
                        && $ticketType
                            ->sales_start_at
                            ->isFuture()
                    ) {
                        throw ValidationException::withMessages([
                            'tickets' =>
                                "{$ticketType->name} sales have not started yet.",
                        ]);
                    }

                    if (
                        $ticketType->sales_end_at !== null
                        && $ticketType
                            ->sales_end_at
                            ->isPast()
                    ) {
                        throw ValidationException::withMessages([
                            'tickets' =>
                                "{$ticketType->name} sales have ended.",
                        ]);
                    }

                    if (
                        $quantity <
                        (int) $ticketType->min_per_order
                    ) {
                        throw ValidationException::withMessages([
                            'tickets' =>
                                "{$ticketType->name} requires at least {$ticketType->min_per_order} ticket(s) per order.",
                        ]);
                    }

                    if (
                        $quantity >
                        (int) $ticketType->max_per_order
                    ) {
                        throw ValidationException::withMessages([
                            'tickets' =>
                                "{$ticketType->name} allows a maximum of {$ticketType->max_per_order} ticket(s) per order.",
                        ]);
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Capacity Check While Row Is Locked
                    |--------------------------------------------------------------------------
                    */

                    if (
                        ! $this->availability->canReserve(
                            $ticketType,
                            $quantity
                        )
                    ) {
                        $available =
                            $this->availability
                                ->availableQuantity(
                                    $ticketType
                                );

                        throw ValidationException::withMessages([
                            'tickets' =>
                                $available === null
                                    ? "{$ticketType->name} is not available."
                                    : "Only {$available} {$ticketType->name} ticket(s) remain.",
                        ]);
                    }

                    $unitPrice = (float)
                        $ticketType->price;

                    $lineSubtotal =
                        $unitPrice * $quantity;

                    $subtotal += $lineSubtotal;

                    $preparedItems[] = [
                        'ticket_type_id' =>
                            $ticketType->getKey(),

                        'quantity' =>
                            $quantity,

                        'unit_price' =>
                            $unitPrice,

                        'subtotal' =>
                            $lineSubtotal,

                        'discount_amount' =>
                            0,

                        'total' =>
                            $lineSubtotal,
                    ];
                }

                /*
                |--------------------------------------------------------------------------
                | Create Order
                |--------------------------------------------------------------------------
                */

                $currency = (string)
                    $ticketTypes
                        ->first()
                        ->currency;

                foreach ($ticketTypes as $ticketType) {
                    if (
                        strtoupper(
                            (string) $ticketType->currency
                        )
                        !== strtoupper($currency)
                    ) {
                        throw ValidationException::withMessages([
                            'tickets' =>
                                'All ticket types in one order must use the same currency.',
                        ]);
                    }
                }

                $order = TicketOrder::create([
                    'event_id' =>
                        $event->getKey(),

                    'order_number' =>
                        $this->generateOrderNumber(
                            $event
                        ),

                    'buyer_name' =>
                        trim(
                            (string) (
                                $buyer['name']
                                ?? ''
                            )
                        ),

                    'buyer_phone' =>
                        filled(
                            $buyer['phone']
                            ?? null
                        )
                            ? trim(
                                (string) $buyer['phone']
                            )
                            : null,

                    'buyer_email' =>
                        filled(
                            $buyer['email']
                            ?? null
                        )
                            ? strtolower(
                                trim(
                                    (string) $buyer[
                                        'email'
                                    ]
                                )
                            )
                            : null,

                    'quantity' =>
                        $totalQuantity,

                    'subtotal' =>
                        $subtotal,

                    'discount_amount' =>
                        0,

                    'total' =>
                        $subtotal,

                    'currency' =>
                        strtoupper($currency),

                    'status' =>
                        TicketOrder::STATUS_PENDING,

                    'expires_at' =>
                        now()->addMinutes(
                            $reservationMinutes
                        ),
                ]);

                foreach (
                    $preparedItems as $preparedItem
                ) {
                    TicketOrderItem::create([
                        'ticket_order_id' =>
                            $order->getKey(),

                        ...$preparedItem,
                    ]);
                }

                return $order->load([
                    'items.ticketType',
                ]);
            },
            attempts: 3
        );
    }

    private function generateOrderNumber(
        Event $event
    ): string {
        $eventCode = filled(
            $event->event_code
        )
            ? strtoupper(
                (string) $event->event_code
            )
            : 'EVT' . $event->getKey();

        do {
            $orderNumber = sprintf(
                'ELV-TKT-%s-%s-%s',
                $eventCode,
                now()->format('ymdHis'),
                Str::upper(
                    Str::random(6)
                )
            );
        } while (
            TicketOrder::query()
                ->where(
                    'order_number',
                    $orderNumber
                )
                ->exists()
        );

        return $orderNumber;
    }
}
