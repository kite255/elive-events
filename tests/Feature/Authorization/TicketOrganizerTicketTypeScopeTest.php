<?php

namespace Tests\Feature\Authorization;

use App\Filament\Resources\TicketTypes\TicketTypeResource;
use App\Models\Event;
use App\Models\Organization;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketOrganizerTicketTypeScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticketing_manager_only_sees_ticket_types_from_assigned_events(): void
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
                now()->addDay(),

            'status' =>
                Event::STATUS_ACTIVE,

            'registration_is_open' =>
                true,
        ]);

        $organizer->assignToEvent(
            $assignedEvent,
            User::ORGANIZATION_ROLE_TICKET_ORGANIZER
        );

        $assignedTicketType = TicketType::query()->create([
            'event_id' =>
                $assignedEvent->id,

            'name' =>
                'VIP',

            'code' =>
                'VIP-A',

            'price' =>
                50000,

            'currency' =>
                'TZS',

            'is_active' =>
                true,

            'is_public' =>
                true,
        ]);

        $unassignedTicketType = TicketType::query()->create([
            'event_id' =>
                $unassignedEvent->id,

            'name' =>
                'Regular',

            'code' =>
                'REG-B',

            'price' =>
                25000,

            'currency' =>
                'TZS',

            'is_active' =>
                true,

            'is_public' =>
                true,
        ]);

        $this->actingAs($organizer);

        $visibleIds = TicketTypeResource::getEloquentQuery()
            ->pluck('ticket_types.id')
            ->all();

        $this->assertContains(
            $assignedTicketType->id,
            $visibleIds
        );

        $this->assertNotContains(
            $unassignedTicketType->id,
            $visibleIds
        );

        $this->assertCount(
            1,
            $visibleIds
        );
    }
}
