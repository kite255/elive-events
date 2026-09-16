<?php

namespace Tests\Feature\Finance;

use App\Filament\Pages\OrganizerFinanceOverview;
use App\Models\Event;
use App\Models\Organization;
use App\Models\TicketOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizerFinanceOverviewPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_only_sees_events_from_owned_organizations(): void
    {
        $ownedOrganization = Organization::query()->create([
            'name' => 'Owned Organization',
            'email' => 'owned@example.com',
        ]);

        $foreignOrganization = Organization::query()->create([
            'name' => 'Foreign Organization',
            'email' => 'foreign@example.com',
        ]);

        $owner = User::query()->create([
            'name' => 'Finance Owner',
            'email' => 'finance-owner@example.com',
            'password' => 'password',
            'is_super_admin' => false,
        ]);

        $owner->organizations()->attach(
            $ownedOrganization->id,
            [
                'role' => User::ORGANIZATION_ROLE_OWNER,
                'status' => User::ORGANIZATION_STATUS_ACTIVE,
                'is_owner' => true,
                'joined_at' => now(),
            ]
        );

        $ownedEvent = Event::query()->create([
            'organization_id' => $ownedOrganization->id,
            'name' => 'Owned Event',
            'venue' => 'Owned Venue',
            'starts_at' => now()->addDay(),
            'status' => Event::STATUS_ACTIVE,
            'registration_is_open' => true,
        ]);

        $foreignEvent = Event::query()->create([
            'organization_id' => $foreignOrganization->id,
            'name' => 'Foreign Event',
            'venue' => 'Foreign Venue',
            'starts_at' => now()->addDay(),
            'status' => Event::STATUS_ACTIVE,
            'registration_is_open' => true,
        ]);

        $this->actingAs($owner);

        $page = new OrganizerFinanceOverview();

        $options = $page->eventOptions();

        $this->assertArrayHasKey(
            $ownedEvent->id,
            $options
        );

        $this->assertArrayNotHasKey(
            $foreignEvent->id,
            $options
        );
    }

    public function test_owner_finance_metrics_use_selected_owned_event(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Finance Organization',
            'email' => 'owner-finance@example.com',
        ]);

        $owner = User::query()->create([
            'name' => 'Finance Owner',
            'email' => 'owner-finance-user@example.com',
            'password' => 'password',
            'is_super_admin' => false,
        ]);

        $owner->organizations()->attach(
            $organization->id,
            [
                'role' => User::ORGANIZATION_ROLE_OWNER,
                'status' => User::ORGANIZATION_STATUS_ACTIVE,
                'is_owner' => true,
                'joined_at' => now(),
            ]
        );

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Finance Event',
            'venue' => 'Finance Venue',
            'starts_at' => now()->addDay(),
            'status' => Event::STATUS_ACTIVE,
            'registration_is_open' => true,
        ]);

        $order = TicketOrder::query()->create([
            'event_id' => $event->id,
            'order_number' => 'PAGE-FIN-001',
            'buyer_name' => 'Finance Buyer',
            'quantity' => 1,
            'subtotal' => 100000,
            'discount_amount' => 0,
            'total' => 100000,
            'currency' => 'TZS',
            'status' => TicketOrder::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $order->forceFill([
            'gross_amount' => 100000,
            'platform_commission_rate' => 7.00,
            'platform_commission_amount' => 7000,
            'gateway_fee_rate' => 3.50,
            'gateway_fee_amount' => 3500,
            'total_charges' => 10500,
            'organizer_net_amount' => 89500,
            'financial_snapshot_at' => now(),
        ])->save();

        $this->actingAs($owner);

        $page = new OrganizerFinanceOverview();
        $page->selectedEventId = $event->id;

        $metrics = $page->financeMetrics();

        $this->assertSame(
            100000.0,
            $metrics['gross_sales']
        );

        $this->assertSame(
            10500.0,
            $metrics['total_charges']
        );

        $this->assertSame(
            89500.0,
            $metrics['net_payable']
        );

        $this->assertSame(
            1,
            $metrics['paid_orders']
        );

        $this->assertArrayNotHasKey(
            'platform_commission_amount',
            $metrics
        );

        $this->assertArrayNotHasKey(
            'gateway_fee_amount',
            $metrics
        );
    }
}
