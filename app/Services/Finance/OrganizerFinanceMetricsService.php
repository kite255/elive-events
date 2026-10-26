<?php

namespace App\Services\Finance;

use App\Models\Event;
use App\Models\Payment;
use App\Models\TicketOrder;

class OrganizerFinanceMetricsService
{
    public function forEvent(Event $event): array
    {
        $paidOrders = TicketOrder::query()
            ->where('event_id', $event->getKey())
            ->where('status', TicketOrder::STATUS_PAID);

        // Display merchandise receipts for information only. These are not
        // settlement-verified funds or an amount eligible for payout.
        $merchandisePayments = Payment::query()
            ->where('event_id', $event->getKey())
            ->whereNotNull('merchandise_order_id')
            ->where('status', Payment::STATUS_COMPLETED);

        return [
            'gross_sales' => (float) (clone $paidOrders)
                ->sum('gross_amount'),

            'total_charges' => (float) (clone $paidOrders)
                ->sum('total_charges'),

            'net_payable' => (float) (clone $paidOrders)
                ->sum('organizer_net_amount'),

            'merchandise_gross_sales' => (float) (clone $merchandisePayments)
                ->sum('amount'),

            'completed_merchandise_payments' => (clone $merchandisePayments)
                ->count(),

            'paid_orders' => (clone $paidOrders)
                ->count(),

            'currency' => (string) (
                (clone $paidOrders)
                    ->value('currency')
                ?? 'TZS'
            ),
        ];
    }
}
