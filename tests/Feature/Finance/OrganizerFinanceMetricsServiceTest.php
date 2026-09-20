<?php

namespace Tests\Feature\Finance;

use App\Models\Event;
use App\Models\Organization;
use App\Models\TicketOrder;
use App\Services\Finance\OrganizerFinanceMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizerFinanceMetricsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_organizer_safe_finance_totals_for_paid_orders(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Finance Organization',
            'email' => 'finance@example.com',
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Finance Event',
            'venue' => 'Finance Venue',
            'starts_at' => now()->addDay(),
            'status' => Event::STATUS_ACTIVE,
            'registration_is_open' => true,
        ]);

        /*
        |--------------------------------------------------------------------------
        | First paid order
        |--------------------------------------------------------------------------
        */

        $firstPaidOrder = TicketOrder::query()->create([
            'event_id' => $event->id,
            'order_number' => 'FIN-001',
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

        /*
        |--------------------------------------------------------------------------
        | Second paid order
        |--------------------------------------------------------------------------
        */

        $secondPaidOrder = TicketOrder::query()->create([
            'event_id' => $event->id,
            'order_number' => 'FIN-002',
            'buyer_name' => 'Buyer Two',
            'quantity' => 1,
            'subtotal' => 50000,
            'discount_amount' => 0,
            'total' => 50000,
            'currency' => 'TZS',
            'status' => TicketOrder::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $secondPaidOrder->forceFill([
            'gross_amount' => 50000,
            'platform_commission_rate' => 7.00,
            'platform_commission_amount' => 3500,
            'gateway_fee_rate' => 3.50,
            'gateway_fee_amount' => 1750,
            'total_charges' => 5250,
            'organizer_net_amount' => 44750,
            'financial_snapshot_at' => now(),
        ])->save();

        /*
        |--------------------------------------------------------------------------
        | Pending order
        |--------------------------------------------------------------------------
        |
        | This order must NOT be included in organizer finance totals.
        |
        */

        $pendingOrder = TicketOrder::query()->create([
            'event_id' => $event->id,
            'order_number' => 'FIN-PENDING',
            'buyer_name' => 'Pending Buyer',
            'quantity' => 1,
            'subtotal' => 200000,
            'discount_amount' => 0,
            'total' => 200000,
            'currency' => 'TZS',
            'status' => TicketOrder::STATUS_PENDING,
        ]);

        $pendingOrder->forceFill([
            'gross_amount' => 200000,
            'platform_commission_rate' => 7.00,
            'platform_commission_amount' => 14000,
            'gateway_fee_rate' => 3.50,
            'gateway_fee_amount' => 7000,
            'total_charges' => 21000,
            'organizer_net_amount' => 179000,
            'financial_snapshot_at' => now(),
        ])->save();

        /*
        |--------------------------------------------------------------------------
        | Calculate organizer finance metrics
        |--------------------------------------------------------------------------
        */

        $metrics = app(
            OrganizerFinanceMetricsService::class
        )->forEvent($event);

        /*
        |--------------------------------------------------------------------------
        | Organizer-visible totals
        |--------------------------------------------------------------------------
        */

        $this->assertSame(
            150000.0,
            $metrics['gross_sales']
        );

        $this->assertSame(
            15750.0,
            $metrics['total_charges']
        );

        $this->assertSame(
            134250.0,
            $metrics['net_payable']
        );

        $this->assertSame(
            2,
            $metrics['paid_orders']
        );

        $this->assertSame(
            'TZS',
            $metrics['currency']
        );

        /*
        |--------------------------------------------------------------------------
        | Internal finance details must remain hidden
        |--------------------------------------------------------------------------
        */

        $this->assertArrayNotHasKey(
            'platform_commission_rate',
            $metrics
        );

        $this->assertArrayNotHasKey(
            'platform_commission_amount',
            $metrics
        );

        $this->assertArrayNotHasKey(
            'gateway_fee_rate',
            $metrics
        );

        $this->assertArrayNotHasKey(
            'gateway_fee_amount',
            $metrics
        );

        $this->assertArrayNotHasKey(
            'gateway_fee_bearer',
            $metrics
        );
    }
}