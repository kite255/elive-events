<?php

namespace App\Services;

use App\Models\EventPayoutRequest;
use App\Models\Payment;
use App\Models\TicketOrder;
use Illuminate\Support\Facades\DB;

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
        // Merchandise is reported separately from ticket earnings.
        // Count payment transactions rather than order totals to avoid counting
        // unpaid merchandise orders. Provider settlement remains unverified.
        $merchandisePayments = Payment::query()->where('event_id', $eventId)
            ->whereNotNull('merchandise_order_id');
        $completedMerchandisePayments = (clone $merchandisePayments)
            ->where('status', Payment::STATUS_COMPLETED);
        $merchandiseCompletedCount = (clone $completedMerchandisePayments)->count();
        $merchandiseGross = (string) (clone $completedMerchandisePayments)->sum('amount');
        $merchandiseRefundExceptions = (clone $merchandisePayments)->whereIn('status', [
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
        $settled = (float) DB::table('event_finance_clearance_entries')
            ->where('event_id', $eventId)->where('entry_type', 'settlement')->sum('amount');
        $reversals = (float) DB::table('event_finance_clearance_entries')
            ->where('event_id', $eventId)->whereIn('entry_type', ['refund', 'chargeback'])->sum('amount');
        $clearedBalance = max(0, min((float) $net, $settled - $reversals) - (float) $paidOut - (float) $reserved);

        return [
            'paid_orders' => (clone $paid)->count(),
            'completed_merchandise_payments' => $merchandiseCompletedCount,
            'completed_merchandise_payment_gross' => round((float) $merchandiseGross, 2),
            'merchandise_refund_exceptions' => $merchandiseRefundExceptions,
            'completed_payment_attempts' => $completedPayments,
            'refunded_or_partially_refunded_orders' => $exceptions,
            'refunded_or_partially_refunded_payments' => $refundedPayments,
            ...$balance,
            'manually_attested_cleared_funds' => round($settled, 2),
            'manually_attested_reversals' => round($reversals, 2),
            'indicative_clearance_limited_balance' => round($clearedBalance, 2),
            'cleared_funds_verified' => false,
            'payouts_enabled' => false,
            'requires_manual_reconciliation' => true,
        ];
    }
}
