<?php

namespace App\Services\Tickets;

use App\Models\Event;
use App\Models\TicketOrder;
use App\Models\TicketOrderItem;

class OrganizerSalesMetricsService
{
    public function forEvent(Event $event): array
    {
        $orders = TicketOrder::query()
            ->where('event_id', $event->getKey());

        $paidOrders = (clone $orders)
            ->where(
                'status',
                TicketOrder::STATUS_PAID
            );

        $pendingOrders = (clone $orders)
            ->whereIn('status', [
                TicketOrder::STATUS_PENDING,
                TicketOrder::STATUS_PROCESSING,
            ]);

        $expiredOrders = (clone $orders)
            ->where(
                'status',
                TicketOrder::STATUS_EXPIRED
            );

        return [
            'gross_sales' =>
                (float) (clone $paidOrders)
                    ->sum('total'),

            'paid_orders' =>
                (clone $paidOrders)->count(),

            'pending_orders' =>
                (clone $pendingOrders)->count(),

            'expired_orders' =>
                (clone $expiredOrders)->count(),

            'tickets_sold' =>
                (int) (clone $paidOrders)
                    ->sum('quantity'),

            'currency' => (string) (
                (clone $paidOrders)
                    ->value('currency')
                ?? (clone $orders)
                    ->value('currency')
                ?? 'TZS'
            ),

            'sales_by_ticket_type' =>
                $this->salesByTicketType($event),

            'recent_orders' =>
                $this->recentOrders($event),
        ];
    }

    private function salesByTicketType(
        Event $event
    ): array {
        return TicketOrderItem::query()
            ->join(
                'ticket_orders',
                'ticket_orders.id',
                '=',
                'ticket_order_items.ticket_order_id'
            )
            ->join(
                'ticket_types',
                'ticket_types.id',
                '=',
                'ticket_order_items.ticket_type_id'
            )
            ->where(
                'ticket_orders.event_id',
                $event->getKey()
            )
            ->where(
                'ticket_orders.status',
                TicketOrder::STATUS_PAID
            )
            ->groupBy(
                'ticket_types.id',
                'ticket_types.name',
                'ticket_types.code',
                'ticket_types.capacity'
            )
            ->orderBy('ticket_types.name')
            ->get([
                'ticket_types.id as ticket_type_id',
                'ticket_types.name',
                'ticket_types.code',
                'ticket_types.capacity',
            ])
            ->map(
                function ($row) use ($event): array {
                    $totals = TicketOrderItem::query()
                        ->join(
                            'ticket_orders',
                            'ticket_orders.id',
                            '=',
                            'ticket_order_items.ticket_order_id'
                        )
                        ->where(
                            'ticket_orders.event_id',
                            $event->getKey()
                        )
                        ->where(
                            'ticket_orders.status',
                            TicketOrder::STATUS_PAID
                        )
                        ->where(
                            'ticket_order_items.ticket_type_id',
                            $row->ticket_type_id
                        )
                        ->selectRaw(
                            'COALESCE(SUM(ticket_order_items.quantity), 0) as quantity'
                        )
                        ->selectRaw(
                            'COALESCE(SUM(ticket_order_items.total), 0) as revenue'
                        )
                        ->first();

                    $quantity =
                        (int) $totals->quantity;

                    $capacity =
                        $row->capacity !== null
                            ? (int) $row->capacity
                            : null;

                    return [
                        'ticket_type_id' =>
                            (int) $row->ticket_type_id,

                        'name' =>
                            (string) $row->name,

                        'code' =>
                            (string) $row->code,

                        'quantity' =>
                            $quantity,

                        'revenue' =>
                            (float) $totals->revenue,

                        'capacity' =>
                            $capacity,

                        'remaining' =>
                            $capacity !== null
                                ? max(
                                    0,
                                    $capacity - $quantity
                                )
                                : null,
                    ];
                }
            )
            ->values()
            ->all();
    }

    private function recentOrders(
        Event $event
    ): array {
        return TicketOrder::query()
            ->where(
                'event_id',
                $event->getKey()
            )
            ->latest('id')
            ->limit(10)
            ->get([
                'id',
                'order_number',
                'buyer_name',
                'buyer_email',
                'buyer_phone',
                'quantity',
                'total',
                'currency',
                'status',
                'paid_at',
                'created_at',
            ])
            ->map(
                fn (TicketOrder $order): array => [
                    'id' => $order->id,

                    'order_number' =>
                        $order->order_number,

                    'buyer_name' =>
                        $order->buyer_name,

                    'buyer_email' =>
                        $order->buyer_email,

                    'buyer_phone' =>
                        $order->buyer_phone,

                    'quantity' =>
                        (int) $order->quantity,

                    'total' =>
                        (float) $order->total,

                    'currency' =>
                        $order->currency,

                    'status' =>
                        $order->status,

                    'paid_at' =>
                        $order->paid_at,

                    'created_at' =>
                        $order->created_at,
                ]
            )
            ->all();
    }
}
