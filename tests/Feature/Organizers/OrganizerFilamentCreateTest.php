<?php

namespace Tests\Feature\Organizers;

use App\Filament\Resources\Organizers\Pages\CreateOrganizer;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrganizerFilamentCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_ticket_organizer_from_filament(): void
    {
        $admin = User::query()->create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'is_super_admin' => true,
        ]);

        $organization = Organization::query()->create([
            'name' => 'Concert Events Ltd',
            'email' => 'concert@example.com',
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

        $this->actingAs($admin);

        Livewire::test(CreateOrganizer::class)
            ->fillForm([
                'name' => 'Concert Organizer',
                'email' => 'organizer@example.com',
                'password' => 'secret-password',
                'organization_id' => $organization->id,
                'assigned_event_ids' => [
                    $eventOne->id,
                    $eventTwo->id,
                ],
                'status' => User::ORGANIZATION_STATUS_ACTIVE,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $organizer = User::query()
            ->where(
                'email',
                'organizer@example.com'
            )
            ->first();

        $this->assertNotNull($organizer);

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
                    User::ORGANIZATION_STATUS_ACTIVE,

                'is_owner' =>
                    false,
            ]
        );

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
    }

    public function test_ticketing_manager_form_accepts_multiple_assigned_events(): void
    {
        $admin = User::query()->create([
            'name' => 'Super Admin',
            'email' => 'admin2@example.com',
            'password' => 'password',
            'is_super_admin' => true,
        ]);

        $organization = Organization::query()->create([
            'name' => 'Multiple Events Ltd',
            'email' => 'multiple-events@example.com',
        ]);

        $eventOne = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Event One',
            'status' => Event::STATUS_ACTIVE,
        ]);

        $eventTwo = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Event Two',
            'status' => Event::STATUS_ACTIVE,
        ]);

        $this->actingAs($admin);

        Livewire::test(CreateOrganizer::class)
            ->fillForm([
                'name' => 'Multiple Event Manager',
                'email' => 'multiple.manager@example.com',
                'password' => 'secret-password',
                'organization_id' => $organization->id,
                'assigned_event_ids' => [
                    $eventOne->id,
                    $eventTwo->id,
                ],
                'status' => User::ORGANIZATION_STATUS_ACTIVE,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $organizer = User::query()
            ->where(
                'email',
                'multiple.manager@example.com'
            )
            ->firstOrFail();

        $this->assertEqualsCanonicalizing(
            [
                $eventOne->id,
                $eventTwo->id,
            ],
            $organizer
                ->assignedTicketingEventIds()
                ->all()
        );
    }

    public function test_ticketing_manager_form_rejects_event_from_another_organization(): void
    {
        $admin = User::query()->create([
            'name' => 'Super Admin',
            'email' => 'admin3@example.com',
            'password' => 'password',
            'is_super_admin' => true,
        ]);

        $organizationOne = Organization::query()->create([
            'name' => 'Organization One',
            'email' => 'organization-one@example.com',
        ]);

        $organizationTwo = Organization::query()->create([
            'name' => 'Organization Two',
            'email' => 'organization-two@example.com',
        ]);

        $validEvent = Event::query()->create([
            'organization_id' => $organizationOne->id,
            'name' => 'Valid Event',
            'status' => Event::STATUS_ACTIVE,
        ]);

        $foreignEvent = Event::query()->create([
            'organization_id' => $organizationTwo->id,
            'name' => 'Foreign Event',
            'status' => Event::STATUS_ACTIVE,
        ]);

        $this->actingAs($admin);

        Livewire::test(CreateOrganizer::class)
            ->fillForm([
                'name' => 'Restricted Manager',
                'email' => 'restricted@example.com',
                'password' => 'secret-password',
                'organization_id' => $organizationOne->id,
                'assigned_event_ids' => [
                    $validEvent->id,
                    $foreignEvent->id,
                ],
                'status' => User::ORGANIZATION_STATUS_ACTIVE,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'assigned_event_ids',
            ]);

        $this->assertDatabaseMissing(
            'users',
            [
                'email' =>
                    'restricted@example.com',
            ]
        );
    }
}