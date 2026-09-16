<?php

namespace Tests\Feature\Finance;

use App\Filament\Pages\AdminFinanceOverview;
use App\Models\Event;
use App\Models\Organization;
use App\Models\TicketOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFinanceOverviewPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_finance_page_returns_platform_totals(): void
    {
        $superAdmin = User::query()->create([
            'name' => 'Super Admin',
            'email' => 'admin-finance@example.com',
            'password' => 'password',
            'is_super_admin' => true,
        ]);

        $organizationOne = Organization::query()->create([
            'name' => 'Organization One',
            'email' => 'admin-org-one@example.com',
        ]);

        $organizationTwo = Organization::query()->create([
            'name' => 'Organization Two',
            'email' => 'admin-org-two@example.com',
        ]);

        $eventOne = Event::query()->create([
            'organization_id' => $organizationOne->id,
            'name' => 'Admin Event One',
            'venue' => 'Venue One',
            'starts_at' => now()->addDay(),
            'status' => Event::STATUS_ACTIVE,
            'registration_is_open' => true,
        ]);

        $eventTwo = Event::query()->create([
            'organization_id' => $organizationTwo->id,
            'name' => 'Admin Event Two',
            'venue' => 'Venue Two',
            'starts_at' => now()->addDays(2),
            'status' => Event::STATUS_ACTIVE,
            'registration_is_open' => true,
        ]);

        $firstOrder = TicketOrder::query()->create([
            'event_id' => $eventOne->id,
            'order_number' => 'ADMIN-PAGE-001',
            'buyer_name' => 'Buyer One',
            'quantity' => 1,
            'subtotal' => 100000,
            'discount_amount' => 0,
            'total' => 100000,
            'currency' => 'TZS',
            'status' => TicketOrder::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $firstOrder->forceFill([
            'gross_amount' => 100000,
            'platform_commission_rate' => 7.00,
            'platform_commission_amount' => 7000,
            'gateway_fee_rate' => 3.50,
            'gateway_fee_amount' => 3500,
            'total_charges' => 10500,
            'organizer_net_amount' => 89500,
            'financial_snapshot_at' => now(),
        ])->save();

        $secondOrder = TicketOrder::query()->create([
            'event_id' => $eventTwo->id,
            'order_number' => 'ADMIN-PAGE-002',
            'buyer_name' => 'Buyer Two',
            'quantity' => 1,
            'subtotal' => 50000,
            'discount_amount' => 0,
            'total' => 50000,
            'currency' => 'TZS',
            'status' => TicketOrder::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $secondOrder->forceFill([
            'gross_amount' => 50000,
            'platform_commission_rate' => 5.00,
            'platform_commission_amount' => 2500,
            'gateway_fee_rate' => 3.00,
            'gateway_fee_amount' => 1500,
            'total_charges' => 4000,
            'organizer_net_amount' => 46000,
            'financial_snapshot_at' => now(),
        ])->save();

        $this->actingAs($superAdmin);

        $page = new AdminFinanceOverview();

        $metrics = $page->financeMetrics();

        $this->assertSame(
            150000.0,
            $metrics['gross_sales']
        );

        $this->assertSame(
            9500.0,
            $metrics['platform_commission_amount']
        );

        $this->assertSame(
            5000.0,
            $metrics['gateway_fee_amount']
        );

        $this->assertSame(
            14500.0,
            $metrics['total_charges']
        );

        $this->assertSame(
            135500.0,
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
