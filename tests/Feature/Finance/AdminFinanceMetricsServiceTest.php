<?php

namespace Tests\Feature\Finance;

use App\Models\Event;
use App\Models\Organization;
use App\Models\TicketOrder;
use App\Services\Finance\AdminFinanceMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFinanceMetricsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_platform_finance_totals_for_paid_orders(): void
    {
        $organizationOne = Organization::query()->create([
            'name' => 'Organization One',
            'email' => 'org-one@example.com',
        ]);

        $organizationTwo = Organization::query()->create([
            'name' => 'Organization Two',
            'email' => 'org-two@example.com',
        ]);

        $eventOne = Event::query()->create([
            'organization_id' => $organizationOne->id,
            'name' => 'Event One',
            'venue' => 'Venue One',
            'starts_at' => now()->addDay(),
            'status' => Event::STATUS_ACTIVE,
            'registration_is_open' => true,
        ]);

        $eventTwo = Event::query()->create([
            'organization_id' => $organizationTwo->id,
            'name' => 'Event Two',
            'venue' => 'Venue Two',
            'starts_at' => now()->addDays(2),
            'status' => Event::STATUS_ACTIVE,
            'registration_is_open' => true,
        ]);

        $firstPaidOrder = TicketOrder::query()->create([
            'event_id' => $eventOne->id,
            'order_number' => 'ADMIN-FIN-001',
            'buyer_name' => 'Buyer One',
            'quantity' => 1,
            'subtotal' => 100000,
            'discount_amount' => 0,
            'total' => 100000,
            'currency' => 'TZS',
            'status' => TicketOrder::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $firstPaidOrder->forceFill([
            'gross_amount' => 100000,
            'platform_commission_rate' => 7.00,
            'platform_commission_amount' => 7000,
            'gateway_fee_rate' => 3.50,
            'gateway_fee_amount' => 3500,
            'total_charges' => 10500,
            'organizer_net_amount' => 89500,
            'financial_snapshot_at' => now(),
        ])->save();

        $secondPaidOrder = TicketOrder::query()->create([
            'event_id' => $eventTwo->id,
            'order_number' => 'ADMIN-FIN-002',
            'buyer_name' => 'Buyer Two',
            'quantity' => 1,
            'subtotal' => 200000,
            'discount_amount' => 0,
            'total' => 200000,
            'currency' => 'TZS',
            'status' => TicketOrder::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $secondPaidOrder->forceFill([
            'gross_amount' => 200000,
            'platform_commission_rate' => 5.00,
            'platform_commission_amount' => 10000,
            'gateway_fee_rate' => 3.00,
            'gateway_fee_amount' => 6000,
            'total_charges' => 16000,
            'organizer_net_amount' => 184000,
            'financial_snapshot_at' => now(),
        ])->save();

        $pendingOrder = TicketOrder::query()->create([
            'event_id' => $eventOne->id,
            'order_number' => 'ADMIN-FIN-PENDING',
            'buyer_name' => 'Pending Buyer',
            'quantity' => 1,
            'subtotal' => 500000,
            'discount_amount' => 0,
            'total' => 500000,
            'currency' => 'TZS',
            'status' => TicketOrder::STATUS_PENDING,
        ]);

        $pendingOrder->forceFill([
            'gross_amount' => 500000,
            'platform_commission_rate' => 7.00,
            'platform_commission_amount' => 35000,
            'gateway_fee_rate' => 3.50,
            'gateway_fee_amount' => 17500,
            'total_charges' => 52500,
            'organizer_net_amount' => 447500,
            'financial_snapshot_at' => now(),
        ])->save();

        $metrics = app(
            AdminFinanceMetricsService::class
        )->platformTotals();

        $this->assertSame(
            300000.0,
            $metrics['gross_sales']
        );

        $this->assertSame(
            17000.0,
            $metrics['platform_commission_amount']
        );

        $this->assertSame(
            9500.0,
            $metrics['gateway_fee_amount']
        );

        $this->assertSame(
            26500.0,
            $metrics['total_charges']
        );

        $this->assertSame(
            273500.0,
            $metrics['organizer_net_amount']
        );

        $this->assertSame(
            2,
            $metrics['paid_orders']
        );

        $this->assertSame(
            'TZS',
            $metrics['currency']
        );
    }
}
