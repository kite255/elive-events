<?php

namespace App\Services\Finance;

use App\Models\Event;
use App\Models\TicketOrder;

class OrganizerFinanceMetricsService
{
    public function forEvent(Event $event): array
    {
        $paidOrders = TicketOrder::query()
            ->where('event_id', $event->getKey())
            ->where('status', TicketOrder::STATUS_PAID);

        return [
            'gross_sales' => (float) (clone $paidOrders)
                ->sum('gross_amount'),

            'total_charges' => (float) (clone $paidOrders)
                ->sum('total_charges'),

            'net_payable' => (float) (clone $paidOrders)
                ->sum('organizer_net_amount'),

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
