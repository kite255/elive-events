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

    public function test_ticket_organizer_only_sees_own_organization_events_on_sales_dashboard(): void
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

        $page = new OrganizerSalesDashboard();

        $options = $page->eventOptions();

        $this->assertSame(
            [
                $eventA->id => 'Event A',
            ],
            $options
        );
    }
}