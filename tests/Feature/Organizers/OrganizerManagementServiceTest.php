<?php

namespace Tests\Feature\Organizers;

use App\Models\Organization;
use App\Models\User;
use App\Services\Organizers\OrganizerManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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


}
