<?php

namespace App\Services\Tickets;

use App\Models\Event;
use App\Models\TicketOrder;
use App\Models\TicketOrderItem;
use Illuminate\Database\Eloquent\Builder;

class OrganizerSalesMetricsService
{
    public function forEvent(Event $event): array
    {
        $event->loadMissing([
            'paymentSetting',
        ]);

        $orders = TicketOrder::query()
            ->where(
                'event_id',
                $event->getKey()
            );

        $paidOrders = (clone $orders)
            ->where(
                'status',
                TicketOrder::STATUS_PAID
            );

        $pendingOrders = (clone $orders)
            ->whereIn(
                'status',
                [
                    TicketOrder::STATUS_PENDING,
                    TicketOrder::STATUS_PROCESSING,
                ]
            );

        $expiredOrders = (clone $orders)
            ->where(
                'status',
                TicketOrder::STATUS_EXPIRED
            );

        /*
        |--------------------------------------------------------------------------
        | Financial Snapshot Queries
        |--------------------------------------------------------------------------
        |
        | Only orders with financial_snapshot_at are allowed to contribute
        | commission, gateway fee, charges, and organizer net values.
        |
        | Historical orders created before financial snapshots existed must
        | never be recalculated using today's event commission settings.
        |
        */

        $snapshottedPaidOrders =
            (clone $paidOrders)
                ->whereNotNull(
                    'financial_snapshot_at'
                );

        $legacyPaidOrders =
            (clone $paidOrders)
                ->whereNull(
                    'financial_snapshot_at'
                );

        /*
        |--------------------------------------------------------------------------
        | Gross Sales
        |--------------------------------------------------------------------------
        |
        | ticket_orders.total remains the original paid order total.
        |
        | This lets Gross Sales include every paid order, including orders
        | created before the financial snapshot feature was introduced.
        |
        */

        $grossSales =
            (float) (clone $paidOrders)
                ->sum('total');

        /*
        |--------------------------------------------------------------------------
        | Financially Accounted Gross Sales
        |--------------------------------------------------------------------------
        |
        | This represents only paid orders that have a frozen financial
        | snapshot and therefore have reliable commission/net accounting.
        |
        */

        $accountedGrossSales =
            (float) (clone $snapshottedPaidOrders)
                ->sum('gross_amount');

        $platformCommissionAmount =
            (float) (clone $snapshottedPaidOrders)
                ->sum(
                    'platform_commission_amount'
                );

        $gatewayFeeAmount =
            (float) (clone $snapshottedPaidOrders)
                ->sum(
                    'gateway_fee_amount'
                );

        $totalCharges =
            (float) (clone $snapshottedPaidOrders)
                ->sum(
                    'total_charges'
                );

        $organizerNetAmount =
            (float) (clone $snapshottedPaidOrders)
                ->sum(
                    'organizer_net_amount'
                );

        /*
        |--------------------------------------------------------------------------
        | Effective Historical Rates
        |--------------------------------------------------------------------------
        |
        | An event's commission may change over time.
        |
        | Therefore, summing financial amounts and calculating an effective
        | rate is safer than pretending every historical order used the
        | event's current rate.
        |
        */

        $effectivePlatformCommissionRate =
            $accountedGrossSales > 0
                ? round(
                    (
                        $platformCommissionAmount
                        / $accountedGrossSales
                    ) * 100,
                    2
                )
                : 0.00;

        $effectiveGatewayFeeRate =
            $accountedGrossSales > 0
                ? round(
                    (
                        $gatewayFeeAmount
                        / $accountedGrossSales
                    ) * 100,
                    2
                )
                : 0.00;

        $legacyPaidOrdersCount =
            (clone $legacyPaidOrders)
                ->count();

        $legacyGrossSales =
            (float) (clone $legacyPaidOrders)
                ->sum('total');

        $paidOrdersCount =
            (clone $paidOrders)
                ->count();

        $snapshottedPaidOrdersCount =
            (clone $snapshottedPaidOrders)
                ->count();

        $financialSnapshotComplete =
            $legacyPaidOrdersCount === 0;

        $settings =
            $event->paymentSetting;

        return [
            /*
            |--------------------------------------------------------------------------
            | Main Organizer Financial KPIs
            |--------------------------------------------------------------------------
            */

            'gross_sales' =>
                $grossSales,

            'total_charges' =>
                $totalCharges,

            'platform_commission_amount' =>
                $platformCommissionAmount,

            'gateway_fee_amount' =>
                $gatewayFeeAmount,

            'net_payable' =>
                $organizerNetAmount,

            /*
            |--------------------------------------------------------------------------
            | Current Commercial Terms
            |--------------------------------------------------------------------------
            |
            | These are the rates that will apply to future orders.
            |
            | They must not be used to recalculate historical orders.
            |
            */

            'current_platform_commission_rate' =>
                (float) (
                    $settings
                        ?->platform_commission_rate
                    ?? 0
                ),

            'current_gateway_fee_rate' =>
                (float) (
                    $settings
                        ?->gateway_fee_rate
                    ?? 0
                ),

            'gateway_fee_bearer' =>
                (string) (
                    $settings
                        ?->gateway_fee_bearer
                    ?? 'organizer'
                ),

            /*
            |--------------------------------------------------------------------------
            | Effective Historical Rates
            |--------------------------------------------------------------------------
            */

            'effective_platform_commission_rate' =>
                $effectivePlatformCommissionRate,

            'effective_gateway_fee_rate' =>
                $effectiveGatewayFeeRate,

            /*
            |--------------------------------------------------------------------------
            | Financial Snapshot Coverage
            |--------------------------------------------------------------------------
            */

            'accounted_gross_sales' =>
                $accountedGrossSales,

            'snapshotted_paid_orders' =>
                $snapshottedPaidOrdersCount,

            'legacy_paid_orders' =>
                $legacyPaidOrdersCount,

            'legacy_gross_sales' =>
                $legacyGrossSales,

            'financial_snapshot_complete' =>
                $financialSnapshotComplete,

            /*
            |--------------------------------------------------------------------------
            | Existing Sales Metrics
            |--------------------------------------------------------------------------
            */

            'paid_orders' =>
                $paidOrdersCount,

            'pending_orders' =>
                (clone $pendingOrders)
                    ->count(),

            'expired_orders' =>
                (clone $expiredOrders)
                    ->count(),

            'tickets_sold' =>
                (int) (clone $paidOrders)
                    ->sum('quantity'),

            'currency' =>
                (string) (
                    (clone $paidOrders)
                        ->value('currency')
                    ?? (clone $orders)
                        ->value('currency')
                    ?? $settings?->currency
                    ?? 'TZS'
                ),

            'sales_by_ticket_type' =>
                $this->salesByTicketType(
                    $event
                ),

            'recent_orders' =>
                $this->recentOrders(
                    $event
                ),
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
            ->orderBy(
                'ticket_types.name'
            )
            ->get([
                'ticket_types.id as ticket_type_id',
                'ticket_types.name',
                'ticket_types.code',
                'ticket_types.capacity',
            ])
            ->map(
                function ($row) use ($event): array {
                    $totals =
                        TicketOrderItem::query()
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
                        (int) (
                            $totals?->quantity
                            ?? 0
                        );

                    $revenue =
                        (float) (
                            $totals?->revenue
                            ?? 0
                        );

                    $capacity =
                        $row->capacity !== null
                            ? (int) $row->capacity
                            : null;

                    return [
                        'ticket_type_id' =>
                            (int) $row
                                ->ticket_type_id,

                        'name' =>
                            (string) $row->name,

                        'code' =>
                            (string) $row->code,

                        'quantity' =>
                            $quantity,

                        'revenue' =>
                            $revenue,

                        'capacity' =>
                            $capacity,

                        'remaining' =>
                            $capacity !== null
                                ? max(
                                    0,
                                    $capacity
                                    - $quantity
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

                'subtotal',
                'discount_amount',
                'total',

                'gross_amount',
                'platform_commission_rate',
                'platform_commission_amount',
                'gateway_fee_rate',
                'gateway_fee_amount',
                'total_charges',
                'organizer_net_amount',
                'financial_snapshot_at',

                'currency',
                'status',
                'paid_at',
                'created_at',
            ])
            ->map(
                fn (
                    TicketOrder $order
                ): array => [
                    'id' =>
                        $order->id,

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

                    /*
                     * Keep total for compatibility with the
                     * existing dashboard table.
                     */
                    'total' =>
                        (float) $order->total,

                    'gross_amount' =>
                        $order
                            ->gross_amount !== null
                            ? (float) $order
                                ->gross_amount
                            : (float) $order
                                ->total,

                    'platform_commission_rate' =>
                        $order
                            ->platform_commission_rate !== null
                            ? (float) $order
                                ->platform_commission_rate
                            : null,

                    'platform_commission_amount' =>
                        $order
                            ->platform_commission_amount !== null
                            ? (float) $order
                                ->platform_commission_amount
                            : null,

                    'gateway_fee_rate' =>
                        $order
                            ->gateway_fee_rate !== null
                            ? (float) $order
                                ->gateway_fee_rate
                            : null,

                    'gateway_fee_amount' =>
                        $order
                            ->gateway_fee_amount !== null
                            ? (float) $order
                                ->gateway_fee_amount
                            : null,

                    'total_charges' =>
                        $order
                            ->total_charges !== null
                            ? (float) $order
                                ->total_charges
                            : null,

                    'organizer_net_amount' =>
                        $order
                            ->organizer_net_amount !== null
                            ? (float) $order
                                ->organizer_net_amount
                            : null,

                    'has_financial_snapshot' =>
                        $order
                            ->hasFinancialSnapshot(),

                    'financial_snapshot_at' =>
                        $order
                            ->financial_snapshot_at,

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