<?php

namespace Tests\Feature\Authorization;

use App\Filament\Pages\OrganizerFinanceOverview;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketOrganizerFinanceScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_organizer_only_sees_finance_events_from_own_organization(): void
    {
        $organizationA = Organization::query()->create([
            'name' => 'Organizer A',
            'email' => 'orga@example.com',
        ]);

        $organizationB = Organization::query()->create([
            'name' => 'Organizer B',
            'email' => 'orgb@example.com',
        ]);

        $organizer = User::query()->create([
            'name' => 'Ticket Organizer A',
            'email' => 'organizer-a@example.com',
            'password' => 'password',
            'is_super_admin' => false,
        ]);

        $organizer->organizations()->attach(
            $organizationA->id,
            [
                'role' => User::ORGANIZATION_ROLE_TICKET_ORGANIZER,
                'status' => User::ORGANIZATION_STATUS_ACTIVE,
                'is_owner' => false,
                'joined_at' => now(),
            ]
        );

        $eventA = Event::query()->create([
            'organization_id' => $organizationA->id,
            'name' => 'Event A',
            'venue' => 'Venue A',
            'starts_at' => now()->addDay(),
            'status' => Event::STATUS_ACTIVE,
            'registration_is_open' => true,
        ]);

        Event::query()->create([
            'organization_id' => $organizationB->id,
            'name' => 'Event B',
            'venue' => 'Venue B',
            'starts_at' => now()->addDays(2),
            'status' => Event::STATUS_ACTIVE,
            'registration_is_open' => true,
        ]);

        $this->actingAs($organizer);

        $page = new OrganizerFinanceOverview();

        $this->assertSame(
            [
                $eventA->id => 'Event A',
            ],
            $page->eventOptions()
        );
    }

    public function test_ticket_organizer_cannot_force_another_organizations_event_into_my_finance(): void
    {
        $organizationA = Organization::query()->create([
            'name' => 'Organizer A',
            'email' => 'orga@example.com',
        ]);

        $organizationB = Organization::query()->create([
            'name' => 'Organizer B',
            'email' => 'orgb@example.com',
        ]);

        $organizer = User::query()->create([
            'name' => 'Ticket Organizer A',
            'email' => 'organizer-a@example.com',
            'password' => 'password',
            'is_super_admin' => false,
        ]);

        $organizer->organizations()->attach(
            $organizationA->id,
            [
                'role' => User::ORGANIZATION_ROLE_TICKET_ORGANIZER,
                'status' => User::ORGANIZATION_STATUS_ACTIVE,
                'is_owner' => false,
                'joined_at' => now(),
            ]
        );

        Event::query()->create([
            'organization_id' => $organizationA->id,
            'name' => 'Event A',
            'venue' => 'Venue A',
            'starts_at' => now()->addDay(),
            'status' => Event::STATUS_ACTIVE,
            'registration_is_open' => true,
        ]);

        $eventB = Event::query()->create([
            'organization_id' => $organizationB->id,
            'name' => 'Event B',
            'venue' => 'Venue B',
            'starts_at' => now()->addDays(2),
            'status' => Event::STATUS_ACTIVE,
            'registration_is_open' => true,
        ]);

        $this->actingAs($organizer);

        $page = new OrganizerFinanceOverview();
        $page->selectedEventId = $eventB->id;

        $metrics = $page->financeMetrics();

        $this->assertSame(0.0, $metrics['gross_sales']);
        $this->assertSame(0.0, $metrics['total_charges']);
        $this->assertSame(0.0, $metrics['net_payable']);
        $this->assertSame(0, $metrics['paid_orders']);
        $this->assertSame('TZS', $metrics['currency']);
    }
}