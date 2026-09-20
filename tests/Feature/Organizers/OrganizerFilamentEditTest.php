<?php

namespace Tests\Feature\Organizers;

use App\Filament\Resources\Organizers\Pages\EditOrganizer;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrganizerFilamentEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_suspend_ticket_organizer_from_filament(): void
    {
        $admin = User::query()->create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'is_super_admin' => true,
        ]);

        $organization = Organization::query()->create([
            'name' => 'Ticket Events Ltd',
            'email' => 'ticket-events@example.com',
        ]);

        $organizer = User::query()->create([
            'name' => 'Ticket Organizer',
            'email' => 'organizer@example.com',
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

        $this->actingAs($admin);

        Livewire::test(
            EditOrganizer::class,
            [
                'record' =>
                    $organizer->getRouteKey(),
            ]
        )
            ->fillForm([
                'name' =>
                    'Ticket Organizer',

                'email' =>
                    'organizer@example.com',

                'password' =>
                    null,

                'organization_id' =>
                    $organization->id,

                'assigned_event_ids' =>
                    [],

                'status' =>
                    User::ORGANIZATION_STATUS_SUSPENDED,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(
            'organization_user',
            [
                'organization_id' =>
                    $organization->id,

                'user_id' =>
                    $organizer->id,

                'role' =>
                    User::ORGANIZATION_ROLE_TICKET_ORGANIZER,

                'status' =>
                    User::ORGANIZATION_STATUS_SUSPENDED,

                'is_owner' =>
                    false,
            ]
        );

        $this->assertSame(
            1,
            User::query()
                ->where(
                    'email',
                    'organizer@example.com'
                )
                ->count()
        );
    }

    public function test_super_admin_can_sync_ticketing_manager_assigned_events_from_filament(): void
    {
        $admin = User::query()->create([
            'name' => 'Super Admin',
            'email' => 'admin.events@example.com',
            'password' => 'password',
            'is_super_admin' => true,
        ]);

        $organization = Organization::query()->create([
            'name' => 'Event Assignment Ltd',
            'email' => 'assignment@example.com',
        ]);

        $eventOne = Event::query()->create([
            'organization_id' =>
                $organization->id,

            'name' =>
                'Event A',

            'status' =>
                Event::STATUS_ACTIVE,
        ]);

        $eventTwo = Event::query()->create([
            'organization_id' =>
                $organization->id,

            'name' =>
                'Event B',

            'status' =>
                Event::STATUS_ACTIVE,
        ]);

        $eventThree = Event::query()->create([
            'organization_id' =>
                $organization->id,

            'name' =>
                'Event C',

            'status' =>
                Event::STATUS_ACTIVE,
        ]);

        $organizer = User::query()->create([
            'name' => 'Event Ticket Manager',
            'email' => 'event.manager@example.com',
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

        $organizer->assignedEvents()->attach(
            $eventOne->id,
            [
                'role' =>
                    User::ORGANIZATION_ROLE_TICKET_ORGANIZER,

                'status' =>
                    Event::STAFF_STATUS_ACTIVE,

                'assigned_at' =>
                    now(),
            ]
        );

        $organizer->assignedEvents()->attach(
            $eventTwo->id,
            [
                'role' =>
                    User::ORGANIZATION_ROLE_TICKET_ORGANIZER,

                'status' =>
                    Event::STAFF_STATUS_ACTIVE,

                'assigned_at' =>
                    now(),
            ]
        );

        $this->actingAs($admin);

        Livewire::test(
            EditOrganizer::class,
            [
                'record' =>
                    $organizer->getRouteKey(),
            ]
        )
            ->fillForm([
                'name' =>
                    'Event Ticket Manager',

                'email' =>
                    'event.manager@example.com',

                'password' =>
                    null,

                'organization_id' =>
                    $organization->id,

                'assigned_event_ids' => [
                    $eventTwo->id,
                    $eventThree->id,
                ],

                'status' =>
                    User::ORGANIZATION_STATUS_ACTIVE,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $organizer = $organizer->fresh();

        $this->assertEqualsCanonicalizing(
            [
                $eventTwo->id,
                $eventThree->id,
            ],
            $organizer
                ->assignedTicketingEventIds()
                ->all()
        );

        $this->assertDatabaseMissing(
            'event_user',
            [
                'event_id' =>
                    $eventOne->id,

                'user_id' =>
                    $organizer->id,

                'role' =>
                    User::ORGANIZATION_ROLE_TICKET_ORGANIZER,
            ]
        );

        $this->assertDatabaseHas(
            'event_user',
            [
                'event_id' =>
                    $eventTwo->id,

                'user_id' =>
                    $organizer->id,

                'role' =>
                    User::ORGANIZATION_ROLE_TICKET_ORGANIZER,

                'status' =>
                    Event::STAFF_STATUS_ACTIVE,
            ]
        );

        $this->assertDatabaseHas(
            'event_user',
            [
                'event_id' =>
                    $eventThree->id,

                'user_id' =>
                    $organizer->id,

                'role' =>
                    User::ORGANIZATION_ROLE_TICKET_ORGANIZER,

                'status' =>
                    Event::STAFF_STATUS_ACTIVE,
            ]
        );
    }
}