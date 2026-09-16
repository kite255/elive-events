<?php

namespace Tests\Feature\Authorization;

use App\Filament\Pages\OrganizerFinanceOverview;
use App\Models\Event;
use App\Models\Organization;
use App\Models\TicketOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketOrganizerFinanceScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticketing_manager_only_sees_finance_events_from_assigned_events(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Organizer A',
            'email' => 'orga@example.com',
        ]);

        $organizer = User::query()->create([
            'name' => 'Ticket Manager A',
            'email' => 'organizer-a@example.com',
            'password' => 'password',
            'is_super_admin' => false,
        ]);

        $organizer->organizations()->attach(
            $organization->id,
            [
                'role' =>
                    User::ORGANIZATION_ROLE_TICKET_ORGANIZER,

                'status' =>
                    User::ORGANIZATION_STATUS_ACTIVE,

                'is_owner' =>
                    false,

                'joined_at' =>
                    now(),
            ]
        );

        $assignedEvent = Event::query()->create([
            'organization_id' =>
                $organization->id,

            'name' =>
                'Assigned Event',

            'venue' =>
                'Venue A',

            'starts_at' =>
                now()->addDay(),

            'status' =>
                Event::STATUS_ACTIVE,

            'registration_is_open' =>
                true,
        ]);

        $unassignedEvent = Event::query()->create([
            'organization_id' =>
                $organization->id,

            'name' =>
                'Unassigned Event',

            'venue' =>
                'Venue B',

            'starts_at' =>
                now()->addDays(2),

            'status' =>
                Event::STATUS_ACTIVE,

            'registration_is_open' =>
                true,
        ]);

        $organizer->assignToEvent(
            $assignedEvent,
            User::ORGANIZATION_ROLE_TICKET_ORGANIZER
        );

        $this->actingAs($organizer);

        $page = new OrganizerFinanceOverview();

        $options = $page->eventOptions();

        $this->assertArrayHasKey(
            $assignedEvent->id,
            $options
        );

        $this->assertSame(
            'Assigned Event',
            $options[$assignedEvent->id]
        );

        $this->assertArrayNotHasKey(
            $unassignedEvent->id,
            $options
        );

        $this->assertCount(
            1,
            $options
        );
    }

    public function test_ticketing_manager_cannot_force_unassigned_event_into_my_finance(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Organizer A',
            'email' => 'orga@example.com',
        ]);

        $organizer = User::query()->create([
            'name' => 'Ticket Manager A',
            'email' => 'organizer-a@example.com',
            'password' => 'password',
            'is_super_admin' => false,
        ]);

        $organizer->organizations()->attach(
            $organization->id,
            [
                'role' =>
                    User::ORGANIZATION_ROLE_TICKET_ORGANIZER,

                'status' =>
                    User::ORGANIZATION_STATUS_ACTIVE,

                'is_owner' =>
                    false,

                'joined_at' =>
                    now(),
            ]
        );

        $assignedEvent = Event::query()->create([
            'organization_id' =>
                $organization->id,

            'name' =>
                'Assigned Event',

            'venue' =>
                'Venue A',

            'starts_at' =>
                now()->addDay(),

            'status' =>
                Event::STATUS_ACTIVE,

            'registration_is_open' =>
                true,
        ]);

        $unassignedEvent = Event::query()->create([
            'organization_id' =>
                $organization->id,

            'name' =>
                'Unassigned Event',

            'venue' =>
                'Venue B',

            'starts_at' =>
                now()->addDays(2),

            'status' =>
                Event::STATUS_ACTIVE,

            'registration_is_open' =>
                true,
        ]);

        $organizer->assignToEvent(
            $assignedEvent,
            User::ORGANIZATION_ROLE_TICKET_ORGANIZER
        );

        TicketOrder::query()->create([
            'event_id' =>
                $unassignedEvent->id,

            'order_number' =>
                'ORD-UNASSIGNED-001',

            'buyer_name' =>
                'Unassigned Buyer',

            'buyer_phone' =>
                '0712345678',

            'buyer_email' =>
                'unassigned@example.com',

            'quantity' =>
                1,

            'subtotal' =>
                100000,

            'discount_amount' =>
                0,

            'total' =>
                100000,

            'gross_amount' =>
                100000,

            'platform_commission_rate' =>
                5,

            'platform_commission_amount' =>
                5000,

            'gateway_fee_rate' =>
                3,

            'gateway_fee_amount' =>
                3000,

            'total_charges' =>
                8000,

            'organizer_net_amount' =>
                92000,

            'financial_snapshot_at' =>
                now(),

            'currency' =>
                'TZS',

            'status' =>
                TicketOrder::STATUS_PAID,

            'paid_at' =>
                now(),
        ]);

        $this->actingAs($organizer);

        $page = new OrganizerFinanceOverview();

        $page->selectedEventId =
            $unassignedEvent->id;

        $metrics =
            $page->financeMetrics();

        $this->assertSame(
            0.0,
            $metrics['gross_sales']
        );

        $this->assertSame(
            0.0,
            $metrics['total_charges']
        );

        $this->assertSame(
            0.0,
            $metrics['net_payable']
        );

        $this->assertSame(
            0,
            $metrics['paid_orders']
        );

        $this->assertSame(
            'TZS',
            $metrics['currency']
        );
    }
}