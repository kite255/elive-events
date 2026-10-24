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

        $net = (string) (clone $paid)->sum('organizer_net_amount');

        $payments = Payment::query()->where('event_id', $eventId)
            ->whereNotNull('ticket_order_id');

        // Counts are intentionally diagnostics, not amounts to add to orders:
        // multiple attempts may belong to a single ticket order.
        $completedPayments = (clone $payments)
            ->where('status', Payment::STATUS_COMPLETED)->count();
        $refundedPayments = (clone $payments)->whereIn('status', [
            Payment::STATUS_REFUNDED, Payment::STATUS_PARTIALLY_REFUNDED,
        ])->count();
        $paidOut = (string) EventPayoutRequest::query()
            ->where('event_id', $eventId)
            ->where('status', EventPayoutRequest::PAID)
            ->sum('amount');
        $reserved = (string) EventPayoutRequest::query()
            ->where('event_id', $eventId)
            ->whereIn('status', [EventPayoutRequest::REQUESTED, EventPayoutRequest::APPROVED])
            ->sum('amount');

        $balance = FinanceBalanceCalculator::summarize($net, $paidOut, $reserved);

        return [
            'paid_orders' => (clone $paid)->count(),
            'completed_payment_attempts' => $completedPayments,
            'refunded_or_partially_refunded_orders' => $exceptions,
            'refunded_or_partially_refunded_payments' => $refundedPayments,
            ...$balance,
            'cleared_funds_verified' => false,
            'payouts_enabled' => false,
            'requires_manual_reconciliation' => true,
        ];
    }
}
