<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Read-only finance arithmetic. Values are represented as integer cents
 * internally; results are not evidence of settled funds.
 */
final class FinanceBalanceCalculator
{
    private static function cents(string|int|float $amount): int
    {
        $raw = trim((string) $amount);

        if (! preg_match('/^(0|[1-9][0-9]*)(\\.[0-9]{1,2})?$/D', $raw)) {
            throw new InvalidArgumentException('Amount must be a non-negative decimal with at most two places.');
        }

        [$whole, $fraction] = array_pad(explode('.', $raw, 2), 2, '0');
        $fraction = str_pad($fraction, 2, '0');

        if (strlen($whole) > 14) {
            throw new InvalidArgumentException('Amount exceeds the supported range.');
        }

        return ((int) $whole * 100) + (int) $fraction;
    }

    public static function summarize(string|int|float $earned, string|int|float $paid, string|int|float $reserved): array
    {
        $net = self::cents($earned);
        $transferred = self::cents($paid);
        $pending = self::cents($reserved);
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
