<?php

namespace App\Services;

/**
 * Pure arithmetic for read-only financial reporting, not payout authorization.
 */
final class FinanceBalanceCalculator
{
    public static function summarize(string|float|int $earned, string|float|int $paid, string|float|int $reserved): array
    {
        $net = (int) round((float) $earned * 100);
        $transferred = (int) round((float) $paid * 100);
        $pending = (int) round((float) $reserved * 100);

        $difference = $net - $transferred - $pending;

        return [
            'net_ticket_earnings' => $net / 100,
            'recorded_payouts' => $transferred / 100,
            'reserved_requests' => $pending / 100,
            'indicative_unallocated_balance' => max(0, $difference) / 100,
            'overallocated' => $difference < 0,
            'overallocated_amount' => max(0, -$difference) / 100,
        ];
    }
}
