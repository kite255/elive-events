<?php

namespace App\Services\Finance;

use App\Models\TicketOrder;

class AdminFinanceMetricsService
{
    public function platformTotals(): array
    {
        $paidOrders = TicketOrder::query()
            ->where(
                'status',
                TicketOrder::STATUS_PAID
            );

        return [
            'gross_sales' => (float) (clone $paidOrders)
                ->sum('gross_amount'),

            'platform_commission_amount' =>
                (float) (clone $paidOrders)
                    ->sum('platform_commission_amount'),

            'gateway_fee_amount' =>
                (float) (clone $paidOrders)
                    ->sum('gateway_fee_amount'),

            'total_charges' =>
                (float) (clone $paidOrders)
                    ->sum('total_charges'),

            'organizer_net_amount' =>
                (float) (clone $paidOrders)
                    ->sum('organizer_net_amount'),

            'paid_orders' =>
                (clone $paidOrders)->count(),

            'currency' => (string) (
                (clone $paidOrders)
                    ->value('currency')
                ?? 'TZS'
            ),
        ];
    }
}
