<?php

namespace Tests\Feature\Organizers;

use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use App\Services\Organizers\OrganizerManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class OrganizerManagementServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_new_ticket_organizer_and_assigns_the_organization(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Organizer One Ltd',
            'email' => 'org-one@example.com',
        ]);

        $service = app(
            OrganizerManagementService::class
        );

        $user = $service->createOrAssign([
            'name' => 'New Ticket Organizer',
            'email' => 'new.organizer@example.com',
            'password' => 'secret-password',
            'organization_id' => $organization->id,
            'status' => User::ORGANIZATION_STATUS_ACTIVE,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'new.organizer@example.com',
            'is_super_admin' => false,
        ]);

        $this->assertDatabaseHas(
            'organization_user',
            [
                'organization_id' =>
                    $organization->id,

                'user_id' =>
                    $user->id,

                'role' =>
                    User::ORGANIZATION_ROLE_TICKET_ORGANIZER,

                'status' =>
                    User::ORGANIZATION_STATUS_ACTIVE,

                'is_owner' =>
                    false,
            ]
        );
    }

    public function test_it_reuses_existing_user_by_email_instead_of_creating_duplicate_user(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Organizer Two Ltd',
            'email' => 'org-two@example.com',
        ]);

        $existingUser = User::query()->create([
            'name' => 'Existing User',
            'email' => 'existing@example.com',
            'password' => 'existing-password',
            'is_super_admin' => false,
        ]);

        $service = app(
            OrganizerManagementService::class
        );

        $user = $service->createOrAssign([
            'name' => 'Existing User',
            'email' => 'existing@example.com',
            'password' => null,
            'organization_id' => $organization->id,
            'status' => User::ORGANIZATION_STATUS_ACTIVE,
        ]);

        $this->assertSame(
            $existingUser->id,
            $user->id
        );

        $this->assertSame(
            1,
            User::query()
                ->where(
                    'email',
                    'existing@example.com'
                )
                ->count()
        );

        $this->assertDatabaseHas(
            'organization_user',
            [
                'organization_id' =>
                    $organization->id,

                'user_id' =>
                    $existingUser->id,

                'role' =>
                    User::ORGANIZATION_ROLE_TICKET_ORGANIZER,

                'status' =>
                    User::ORGANIZATION_STATUS_ACTIVE,

                'is_owner' =>
                    false,
            ]
        );
    }

    public function test_it_updates_existing_ticket_organizer_membership_status(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Organizer Three Ltd',
            'email' => 'org-three@example.com',
        ]);

        $organizer = User::query()->create([
            'name' => 'Status Organizer',
            'email' => 'status.organizer@example.com',
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

        $service = app(
            OrganizerManagementService::class
        );

        $updated = $service->updateAssignment(
            $organizer,
            [
                'organization_id' =>
                    $organization->id,

                'status' =>
                    User::ORGANIZATION_STATUS_SUSPENDED,
            ]
        );

        $this->assertSame(
            $organizer->id,
            $updated->id
        );

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

        $this->assertFalse(
            $updated->fresh()->isTicketOrganizer()
        );
    }

    public function test_it_assigns_selected_events_to_ticketing_manager(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Concert Company Ltd',
            'email' => 'concerts@example.com',
        ]);

        $eventOne = $this->createEvent(
            $organization,
            'Concert One'
        );

        $eventTwo = $this->createEvent(
            $organization,
            'Concert Two'
        );

        $eventNotAssigned = $this->createEvent(
            $organization,
            'Concert Three'
        );

        $service = app(
            OrganizerManagementService::class
        );

        $organizer = $service->createOrAssign([
            'name' =>
                'Concert Ticket Manager',

            'email' =>
                'concert.manager@example.com',

            'password' =>
                'secret-password',

            'organization_id' =>
                $organization->id,

            'status' =>
                User::ORGANIZATION_STATUS_ACTIVE,

            'assigned_event_ids' => [
                $eventOne->id,
                $eventTwo->id,
            ],
        ]);

        $this->assertDatabaseHas(
            'event_user',
            [
                'event_id' =>
                    $eventOne->id,

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
                    $eventTwo->id,

                'user_id' =>
                    $organizer->id,

                'role' =>
                    User::ORGANIZATION_ROLE_TICKET_ORGANIZER,

                'status' =>
                    Event::STAFF_STATUS_ACTIVE,
            ]
        );

        $this->assertDatabaseMissing(
            'event_user',
            [
                'event_id' =>
                    $eventNotAssigned->id,

                'user_id' =>
                    $organizer->id,

                'role' =>
                    User::ORGANIZATION_ROLE_TICKET_ORGANIZER,
            ]
        );

        $this->assertEqualsCanonicalizing(
            [
                $eventOne->id,
                $eventTwo->id,
            ],
            $organizer
                ->fresh()
                ->assignedTicketingEventIds()
                ->all()
        );
    }

    public function test_it_rejects_assigned_event_from_another_organization(): void
    {
        $organizationOne = Organization::query()->create([
            'name' => 'Organization One',
            'email' => 'organization-one@example.com',
        ]);

        $organizationTwo = Organization::query()->create([
            'name' => 'Organization Two',
            'email' => 'organization-two@example.com',
        ]);

        $validEvent = $this->createEvent(
            $organizationOne,
            'Valid Event'
        );

        $foreignEvent = $this->createEvent(
            $organizationTwo,
            'Foreign Event'
        );

        $service = app(
            OrganizerManagementService::class
        );

        try {
            $service->createOrAssign([
                'name' =>
                    'Restricted Manager',

                'email' =>
                    'restricted.manager@example.com',

                'password' =>
                    'secret-password',

                'organization_id' =>
                    $organizationOne->id,

                'status' =>
                    User::ORGANIZATION_STATUS_ACTIVE,

                'assigned_event_ids' => [
                    $validEvent->id,
                    $foreignEvent->id,
                ],
            ]);

            $this->fail(
                'Expected validation exception was not thrown.'
            );
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey(
                'assigned_event_ids',
                $exception->errors()
            );
        }

        $organizer = User::query()
            ->where(
                'email',
                'restricted.manager@example.com'
            )
            ->first();

        /*
         * The transaction must roll back completely.
         */
        $this->assertNull($organizer);
    }

    public function test_it_syncs_ticketing_manager_event_assignments_when_updated(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Festival Company',
            'email' => 'festival@example.com',
        ]);

        $eventOne = $this->createEvent(
            $organization,
            'Festival Day One'
        );

        $eventTwo = $this->createEvent(
            $organization,
            'Festival Day Two'
        );

        $eventThree = $this->createEvent(
            $organization,
            'Festival Day Three'
        );

        $service = app(
            OrganizerManagementService::class
        );

        $organizer = $service->createOrAssign([
            'name' =>
                'Festival Ticket Manager',

            'email' =>
                'festival.manager@example.com',

            'password' =>
                'secret-password',

            'organization_id' =>
                $organization->id,

            'status' =>
                User::ORGANIZATION_STATUS_ACTIVE,

            'assigned_event_ids' => [
                $eventOne->id,
                $eventTwo->id,
            ],
        ]);

        $service->updateAssignment(
            $organizer,
            [
                'name' =>
                    'Festival Ticket Manager',

                'email' =>
                    'festival.manager@example.com',

                'password' =>
                    null,

                'organization_id' =>
                    $organization->id,

                'status' =>
                    User::ORGANIZATION_STATUS_ACTIVE,

                'assigned_event_ids' => [
                    $eventTwo->id,
                    $eventThree->id,
                ],
            ]
        );

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