<?php

namespace Tests\Unit;

use App\Models\EventPaymentSetting;
use App\Models\TicketOrder;
use App\Services\Payments\OrderFinancialCalculator;
use InvalidArgumentException;
use Tests\TestCase;

class OrderFinancialCalculatorTest extends TestCase
{
    public function test_it_calculates_platform_and_gateway_charges_for_organizer(): void
    {
        $order = new TicketOrder([
            'total' => 100000,
        ]);

        $settings = new EventPaymentSetting([
            'platform_commission_rate' => 7,
            'gateway_fee_rate' => 3.5,
            'gateway_fee_bearer' => 'organizer',
        ]);

        $result = app(
            OrderFinancialCalculator::class
        )->calculate(
            $order,
            $settings
        );

        $this->assertSame(
            100000.00,
            $result['gross_amount']
        );

        $this->assertSame(
            7.00,
            $result['platform_commission_rate']
        );

        $this->assertSame(
            7000.00,
            $result['platform_commission_amount']
        );

        $this->assertSame(
            3.50,
            $result['gateway_fee_rate']
        );

        $this->assertSame(
            3500.00,
            $result['gateway_fee_amount']
        );

        $this->assertSame(
            10500.00,
            $result['total_charges']
        );

        $this->assertSame(
            89500.00,
            $result['organizer_net_amount']
        );
    }

    public function test_gateway_fee_does_not_reduce_organizer_net_when_elive_bears_it(): void
    {
        $order = new TicketOrder([
            'total' => 100000,
        ]);

        $settings = new EventPaymentSetting([
            'platform_commission_rate' => 7,
            'gateway_fee_rate' => 3.5,
            'gateway_fee_bearer' => 'elive',
        ]);

        $result = app(
            OrderFinancialCalculator::class
        )->calculate(
            $order,
            $settings
        );

        $this->assertSame(
            7000.00,
            $result['total_charges']
        );

        $this->assertSame(
            93000.00,
            $result['organizer_net_amount']
        );

        $this->assertSame(
            3500.00,
            $result['gateway_fee_amount']
        );
    }

    public function test_gateway_fee_does_not_reduce_organizer_net_when_customer_bears_it(): void
    {
        $order = new TicketOrder([
            'total' => 100000,
        ]);

        $settings = new EventPaymentSetting([
            'platform_commission_rate' => 7,
            'gateway_fee_rate' => 3.5,
            'gateway_fee_bearer' => 'customer',
        ]);

        $result = app(
            OrderFinancialCalculator::class
        )->calculate(
            $order,
            $settings
        );

        $this->assertSame(
            7000.00,
            $result['total_charges']
        );

        $this->assertSame(
            93000.00,
            $result['organizer_net_amount']
        );
    }

    public function test_it_rejects_invalid_platform_commission_rate(): void
    {
        $this->expectException(
            InvalidArgumentException::class
        );

        $order = new TicketOrder([
            'total' => 100000,
        ]);

        $settings = new EventPaymentSetting([
            'platform_commission_rate' => 120,
            'gateway_fee_rate' => 3.5,
            'gateway_fee_bearer' => 'organizer',
        ]);

        app(
            OrderFinancialCalculator::class
        )->calculate(
            $order,
            $settings
        );
    }
}