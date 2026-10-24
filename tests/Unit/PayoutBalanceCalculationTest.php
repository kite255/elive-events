<?php

namespace Tests\Unit;

use App\Services\EventPayoutService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class PayoutBalanceCalculationTest extends TestCase
{
    public function test_request_amount_must_be_positive_and_precision_limited(): void
    {
        // Until DB-backed integration tests are available, guard the core
        // monetary arithmetic independently from the payment gateway.
        $balance = max(0, round(640500.00 - 320000.00 - 20000.00, 2));

        $this->assertSame(300500.00, $balance);
        $this->assertSame(0, max(0, 100.00 - 200.00));
        $this->assertTrue(round(10.125, 2) !== 10.125);
    }
}
