<?php

namespace App\Services;

use App\Models\EventPayoutRequest;
use App\Models\Payment;
use App\Models\TicketOrder;

final class EventFinanceWorkspaceSummary
{
    public function summarize(int $eventId): array
    {
        $orders = TicketOrder::query()->where('event_id', $eventId);
        $payments = Payment::query()->where('event_id', $eventId);
        $payouts = EventPayoutRequest::query()->where('event_id', $eventId);

        return [
            'ticket_sales' => (clone $orders)->where('status', TicketOrder::STATUS_PAID)
                ->selectRaw('currency, COUNT(*) as paid_orders, SUM(gross_amount) as gross, SUM(platform_commission_amount) as commission, SUM(gateway_fee_amount) as gateway_fees, SUM(total_charges) as charges, SUM(organizer_net_amount) as net')
                ->groupBy('currency')->orderBy('currency')->get()->toArray(),
            'payments' => (clone $payments)->where('status', Payment::STATUS_COMPLETED)
                ->selectRaw('currency, COUNT(*) as completed_attempts, SUM(amount) as recorded_amount')
                ->groupBy('currency')->orderBy('currency')->get()->toArray(),
            'payouts' => (clone $payouts)->selectRaw('currency, status, COUNT(*) as requests, SUM(amount) as amount')
                ->groupBy('currency', 'status')->orderBy('currency')->orderBy('status')->get()->toArray(),
            'exceptions' => [
                'Paid orders without completed payment' => (clone $orders)->where('status', TicketOrder::STATUS_PAID)->where('total', '>', 0)
                    ->whereDoesntHave('payments', fn ($query) => $query->where('status', Payment::STATUS_COMPLETED))->count(),
                'Refunded or partially refunded orders' => (clone $orders)->whereIn('status', [TicketOrder::STATUS_REFUNDED, TicketOrder::STATUS_PARTIALLY_REFUNDED])->count(),
                'Refunded or partially refunded payments' => (clone $payments)->whereIn('status', [Payment::STATUS_REFUNDED, Payment::STATUS_PARTIALLY_REFUNDED])->count(),
                'Unclassified completed payments' => (clone $payments)->where('status', Payment::STATUS_COMPLETED)
                    ->whereNull('ticket_order_id')->whereNull('merchandise_order_id')->whereNull('donation_id')
                    ->whereNull('ticket_upgrade_id')->whereNull('attendee_id')->count(),
            ],
        ];
    }
}
