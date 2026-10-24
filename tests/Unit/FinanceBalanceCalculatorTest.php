<?php

namespace Tests\Unit;

use App\Services\FinanceBalanceCalculator;
use PHPUnit\Framework\TestCase;

class FinanceBalanceCalculatorTest extends TestCase
{
    public function test_balance_after_recorded_and_reserved_payouts(): void
    {
        $result = FinanceBalanceCalculator::summarize('640500.00', '320000.00', '20000.00');
        $this->assertSame(300500.0, $result['indicative_unallocated_balance']);
        $this->assertFalse($result['overallocated']);
    }

    public function test_overallocated_balance_is_flagged_instead_of_hidden(): void
    {
        $result = FinanceBalanceCalculator::summarize('100.00', '90.00', '20.00');
        $this->assertSame(0.0, $result['indicative_unallocated_balance']);
        $this->assertTrue($result['overallocated']);
        $this->assertSame(10.0, $result['overallocated_amount']);
    }

    public function test_invalid_precision_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        FinanceBalanceCalculator::summarize('10.001', '0', '0');
    }

    public function test_negative_amount_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        FinanceBalanceCalculator::summarize('-1.00', '0', '0');
    }

    public function test_fractional_amounts_are_processed_in_cents(): void
    {
        $result = FinanceBalanceCalculator::summarize('0.30', '0.10', '0.20');
        $this->assertSame(0.0, $result['indicative_unallocated_balance']);
        $this->assertFalse($result['overallocated']);
    }
}
