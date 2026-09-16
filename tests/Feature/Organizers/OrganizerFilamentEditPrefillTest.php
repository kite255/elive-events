<?php

namespace Tests\Feature\Organizers;

use App\Filament\Resources\Organizers\Pages\EditOrganizer;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrganizerFilamentEditPrefillTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_form_prefills_current_organization_status_and_assigned_events(): void
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

        $eventOne = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Concert One',
            'status' => Event::STATUS_ACTIVE,
        ]);

        $eventTwo = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Concert Two',
            'status' => Event::STATUS_ACTIVE,
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
            ->assertFormSet([
                'name' =>
                    'Ticket Organizer',

                'email' =>
                    'organizer@example.com',

                'organization_id' =>
                    $organization->id,

                'assigned_event_ids' => [
                    $eventOne->id,
                    $eventTwo->id,
                ],

                'status' =>
                    User::ORGANIZATION_STATUS_ACTIVE,

                'password' =>
                    null,
            ]);
    }

    public function test_edit_form_only_prefills_ticketing_manager_event_assignments(): void
    {
        $admin = User::query()->create([
            'name' => 'Super Admin Two',
            'email' => 'admin2@example.com',
            'password' => 'password',
            'is_super_admin' => true,
        ]);

        $organization = Organization::query()->create([
            'name' => 'Mixed Roles Ltd',
            'email' => 'mixed@example.com',
        ]);

        $ticketEvent = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Ticket Event',
            'status' => Event::STATUS_ACTIVE,
        ]);

        $registrationEvent = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Registration Event',
            'status' => Event::STATUS_ACTIVE,
        ]);

        $organizer = User::query()->create([
            'name' => 'Mixed Role Organizer',
            'email' => 'mixed.organizer@example.com',
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
            $ticketEvent->id,
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
            $registrationEvent->id,
            [
                'role' =>
                    User::ORGANIZATION_ROLE_REGISTRATION_OFFICER,

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
            ->assertFormSet([
                'assigned_event_ids' => [
                    $ticketEvent->id,
                ],
            ]);
    }
}