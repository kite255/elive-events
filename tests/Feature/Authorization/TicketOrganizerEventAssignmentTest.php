<?php

namespace Tests\Feature\Authorization;

use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketOrganizerEventAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticketing_manager_can_be_assigned_to_specific_events(): void
    {
        [
            $organizer,
            $organization,
        ] = $this->createTicketOrganizerContext();

        $assignedEvent = $this->createEvent(
            $organization,
            'Assigned Concert'
        );

        $unassignedEvent = $this->createEvent(
            $organization,
            'Unassigned Concert'
        );

        $organizer->assignedEvents()->attach(
            $assignedEvent->id,
            [
                'role' =>
                    User::ORGANIZATION_ROLE_TICKET_ORGANIZER,

                'status' =>
                    Event::STAFF_STATUS_ACTIVE,

                'assigned_at' =>
                    now(),
            ]
        );

        $assignedEventIds = $organizer
            ->fresh()
            ->assignedTicketingEvents()
            ->pluck('events.id');

        $this->assertTrue(
            $assignedEventIds->contains(
                $assignedEvent->id
            )
        );

        $this->assertFalse(
            $assignedEventIds->contains(
                $unassignedEvent->id
            )
        );
    }

    public function test_event_can_have_ticketing_managers(): void
    {
        [
            $organizer,
            $organization,
        ] = $this->createTicketOrganizerContext();

        $event = $this->createEvent(
            $organization,
            'Organizer Event'
        );

        $event->users()->attach(
            $organizer->id,
            [
                'role' =>
                    User::ORGANIZATION_ROLE_TICKET_ORGANIZER,

                'status' =>
                    Event::STAFF_STATUS_ACTIVE,

                'assigned_at' =>
                    now(),
            ]
        );

        $managerIds = $event
            ->fresh()
            ->ticketingManagers()
            ->pluck('users.id');

        $this->assertTrue(
            $managerIds->contains(
                $organizer->id
            )
        );
    }

    public function test_ticketing_manager_assigned_event_ids_helper_returns_only_assigned_events(): void
    {
        [
            $organizer,
            $organization,
        ] = $this->createTicketOrganizerContext();

        $assignedEvent = $this->createEvent(
            $organization,
            'Assigned Event'
        );

        $unassignedEvent = $this->createEvent(
            $organization,
            'Another Event'
        );

        $organizer->assignedEvents()->attach(
            $assignedEvent->id,
            [
                'role' =>
                    User::ORGANIZATION_ROLE_TICKET_ORGANIZER,

                'status' =>
                    Event::STAFF_STATUS_ACTIVE,

                'assigned_at' =>
                    now(),
            ]
        );

        $assignedEventIds =
            $organizer->assignedTicketingEventIds();

        $this->assertTrue(
            $assignedEventIds->contains(
                $assignedEvent->id
            )
        );

        $this->assertFalse(
            $assignedEventIds->contains(
                $unassignedEvent->id
            )
        );
    }

    public function test_same_organization_does_not_automatically_grant_event_assignment(): void
    {
        [
            $organizer,
            $organization,
        ] = $this->createTicketOrganizerContext();

        $event = $this->createEvent(
            $organization,
            'Organization Event'
        );

        $this->assertFalse(
            $organizer
                ->assignedTicketingEventIds()
                ->contains($event->id)
        );
    }

    public function test_inactive_event_assignment_is_not_available_to_ticketing_manager(): void
    {
        [
            $organizer,
            $organization,
        ] = $this->createTicketOrganizerContext();

        $event = $this->createEvent(
            $organization,
            'Inactive Assignment Event'
        );

        $organizer->assignedEvents()->attach(
            $event->id,
            [
                'role' =>
                    User::ORGANIZATION_ROLE_TICKET_ORGANIZER,

                'status' =>
                    Event::STAFF_STATUS_INACTIVE,

                'assigned_at' =>
                    now(),
            ]
        );

        $this->assertFalse(
            $organizer
                ->assignedTicketingEventIds()
                ->contains($event->id)
        );
    }

    public function test_other_event_role_is_not_treated_as_ticketing_manager_assignment(): void
    {
        [
            $organizer,
            $organization,
        ] = $this->createTicketOrganizerContext();

        $event = $this->createEvent(
            $organization,
            'Registration Event'
        );

        $organizer->assignedEvents()->attach(
            $event->id,
            [
                'role' =>
                    User::ORGANIZATION_ROLE_REGISTRATION_OFFICER,

                'status' =>
                    Event::STAFF_STATUS_ACTIVE,

                'assigned_at' =>
                    now(),
            ]
        );

        $this->assertFalse(
            $organizer
                ->assignedTicketingEventIds()
                ->contains($event->id)
        );
    }

    private function createTicketOrganizerContext(): array
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

        return [
            $organizer,
            $organization,
        ];
    }

    private function createEvent(
        Organization $organization,
        string $name
    ): Event {
        return Event::query()->create([
            'organization_id' =>
                $organization->id,

            'name' =>
                $name,

            'status' =>
                Event::STATUS_ACTIVE,
        ]);
    }
}