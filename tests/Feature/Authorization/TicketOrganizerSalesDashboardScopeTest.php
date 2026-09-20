<?php

namespace Tests\Feature\Authorization;

use App\Filament\Pages\OrganizerSalesDashboard;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketOrganizerSalesDashboardScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticketing_manager_only_sees_assigned_events_on_sales_dashboard(): void
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

        $page = new OrganizerSalesDashboard();

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
}