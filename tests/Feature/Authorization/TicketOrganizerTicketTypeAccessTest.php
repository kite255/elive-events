<?php

namespace Tests\Feature\Authorization;

use App\Filament\Resources\TicketTypes\Pages\ListTicketTypes;
use App\Filament\Resources\TicketTypes\TicketTypeResource;
use App\Models\Event;
use App\Models\Organization;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TicketOrganizerTicketTypeAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_organizer_can_view_ticket_types_but_cannot_create_edit_or_delete_them(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Ticket Events Ltd',
            'email' => 'ticket-events@example.com',
        ]);

        $organizer = User::query()->create([
            'name' => 'Ticket Organizer',
            'email' => 'ticket.organizer@example.com',
            'password' => 'password',
            'is_super_admin' => false,
        ]);

        $organizer->organizations()->attach(
            $organization->id,
            [
                'role' => User::ORGANIZATION_ROLE_TICKET_ORGANIZER,
                'status' => User::ORGANIZATION_STATUS_ACTIVE,
                'is_owner' => false,
                'joined_at' => now(),
            ]
        );

        $this->actingAs($organizer);

        $this->assertTrue(
            TicketTypeResource::canViewAny()
        );

        $this->assertFalse(
            TicketTypeResource::canCreate()
        );

        $this->assertFalse(
            TicketTypeResource::canEdit(
                new TicketType()
            )
        );

        $this->assertFalse(
            TicketTypeResource::canDelete(
                new TicketType()
            )
        );
    }

    public function test_ticketing_manager_does_not_see_edit_action_in_ticket_types_table(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Ticket Events Ltd',
            'email' => 'ticket-events@example.com',
        ]);

        $organizer = User::query()->create([
            'name' => 'Ticketing Manager',
            'email' => 'ticketing.manager@example.com',
            'password' => 'password',
            'is_super_admin' => false,
        ]);

        $organizer->organizations()->attach(
            $organization->id,
            [
                'role' => User::ORGANIZATION_ROLE_TICKET_ORGANIZER,
                'status' => User::ORGANIZATION_STATUS_ACTIVE,
                'is_owner' => false,
                'joined_at' => now(),
            ]
        );

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Concert Event',
            'venue' => 'Main Hall',
            'starts_at' => now()->addDay(),
            'status' => Event::STATUS_ACTIVE,
            'registration_is_open' => true,
        ]);

        $ticketType = TicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'VIP',
            'code' => 'VIP',
            'price' => 50000,
            'currency' => 'TZS',
            'is_active' => true,
            'is_public' => true,
        ]);

        $this->actingAs($organizer);

        Livewire::test(ListTicketTypes::class)
            ->assertTableActionHidden(
                'edit',
                $ticketType
            );
    }

    public function test_super_admin_still_sees_edit_action_in_ticket_types_table(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Ticket Events Ltd',
            'email' => 'ticket-events@example.com',
        ]);

        $superAdmin = User::query()->create([
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
            'password' => 'password',
            'is_super_admin' => true,
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Concert Event',
            'venue' => 'Main Hall',
            'starts_at' => now()->addDay(),
            'status' => Event::STATUS_ACTIVE,
            'registration_is_open' => true,
        ]);

        $ticketType = TicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'Regular',
            'code' => 'REG',
            'price' => 25000,
            'currency' => 'TZS',
            'is_active' => true,
            'is_public' => true,
        ]);

        $this->actingAs($superAdmin);

        Livewire::test(ListTicketTypes::class)
            ->assertTableActionVisible(
                'edit',
                $ticketType
            );
    }
}