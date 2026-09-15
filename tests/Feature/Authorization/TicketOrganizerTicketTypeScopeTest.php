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

    public function test_ticket_organizer_only_sees_ticket_types_from_own_organization(): void
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

        $eventA = Event::query()->create([
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
            'starts_at' => now()->addDay(),
            'status' => Event::STATUS_ACTIVE,
            'registration_is_open' => true,
        ]);

        $ticketTypeA = TicketType::query()->create([
            'event_id' => $eventA->id,
            'name' => 'VIP',
            'code' => 'VIP-A',
            'price' => 50000,
            'currency' => 'TZS',
            'is_active' => true,
            'is_public' => true,
        ]);

        TicketType::query()->create([
            'event_id' => $eventB->id,
            'name' => 'Regular',
            'code' => 'REG-B',
            'price' => 25000,
            'currency' => 'TZS',
            'is_active' => true,
            'is_public' => true,
        ]);

        $this->actingAs($organizer);

        $visibleIds = TicketTypeResource::getEloquentQuery()
            ->pluck('ticket_types.id')
            ->all();

        $this->assertSame(
            [$ticketTypeA->id],
            $visibleIds
        );
    }
}
