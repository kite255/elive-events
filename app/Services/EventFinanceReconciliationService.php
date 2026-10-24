<?php

namespace App\Services;

use App\Models\EventPayoutRequest;
use App\Models\Payment;
use App\Models\TicketOrder;

/**
 * Read-only event finance diagnostics.
 *
 * Paid orders are not proof of settled cash. A mismatch or refund requires
 * investigation, and this report NEVER authorizes a payout.
 */
class EventFinanceReconciliationService
{
    public function inspect(int $eventId): array
    {
        $orders = TicketOrder::query()->where('event_id', $eventId);
        $paid = (clone $orders)->where('status', TicketOrder::STATUS_PAID);
        $exceptions = (clone $orders)->whereIn('status', [
            TicketOrder::STATUS_REFUNDED,
            TicketOrder::STATUS_PARTIALLY_REFUNDED,
        ])->count();

        $net = (float) (clone $paid)->sum('organizer_net_amount');

        $payments = Payment::query()->where('event_id', $eventId)
            ->whereNotNull('ticket_order_id');

        // Counts are intentionally diagnostics, not amounts to add to orders:
        // multiple attempts may belong to a single ticket order.
        $completedPayments = (clone $payments)
            ->where('status', Payment::STATUS_COMPLETED)->count();
        $refundedPayments = (clone $payments)->whereIn('status', [
            Payment::STATUS_REFUNDED, Payment::STATUS_PARTIALLY_REFUNDED,
        ])->count();
        $paidOut = (float) EventPayoutRequest::query()
            ->where('event_id', $eventId)
            ->where('status', EventPayoutRequest::PAID)
            ->sum('amount');
        $reserved = (float) EventPayoutRequest::query()
            ->where('event_id', $eventId)
            ->whereIn('status', [EventPayoutRequest::REQUESTED, EventPayoutRequest::APPROVED])
            ->sum('amount');

        return [
            'paid_orders' => (clone $paid)->count(),
            'completed_payment_attempts' => $completedPayments,
            'refunded_or_partially_refunded_orders' => $exceptions,
            'refunded_or_partially_refunded_payments' => $refundedPayments,
            'net_ticket_earnings' => round($net, 2),
            'recorded_payouts' => round($paidOut, 2),
            'reserved_requests' => round($reserved, 2),
            'indicative_unallocated_balance' => round(max(0, $net - $paidOut - $reserved), 2),
            'cleared_funds_verified' => false,
            'payouts_enabled' => false,
            'requires_manual_reconciliation' => true,
        ];
    }
}
