<?php

namespace Tests\Feature\Authorization;

use App\Filament\Resources\Organizers\OrganizerResource;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizerResourceAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_access_organizer_resource(): void
    {
        $admin = User::query()->create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'is_super_admin' => true,
        ]);

        $this->actingAs($admin);

        $this->assertTrue(
            OrganizerResource::shouldRegisterNavigation()
        );

        $this->assertTrue(
            OrganizerResource::canViewAny()
        );

        $this->assertTrue(
            OrganizerResource::canCreate()
        );
    }

    public function test_ticket_organizer_cannot_access_organizer_resource(): void
    {
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

                'is_owner' => false,

                'joined_at' =>
                    now(),
            ]
        );

        $this->actingAs($organizer);

        $this->assertFalse(
            OrganizerResource::shouldRegisterNavigation()
        );

        $this->assertFalse(
            OrganizerResource::canViewAny()
        );

        $this->assertFalse(
            OrganizerResource::canCreate()
        );
    }

    public function test_regular_organization_user_cannot_access_organizer_resource(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Regular Organization',
            'email' => 'regular@example.com',
        ]);

        $user = User::query()->create([
            'name' => 'Organization User',
            'email' => 'user@example.com',
            'password' => 'password',
            'is_super_admin' => false,
        ]);

        $user->organizations()->attach(
            $organization->id,
            [
                'role' =>
                    User::ORGANIZATION_ROLE_ADMIN,

                'status' =>
                    User::ORGANIZATION_STATUS_ACTIVE,

                'is_owner' => false,

                'joined_at' =>
                    now(),
            ]
        );

        $this->actingAs($user);

        $this->assertFalse(
            OrganizerResource::shouldRegisterNavigation()
        );

        $this->assertFalse(
            OrganizerResource::canViewAny()
        );

        $this->assertFalse(
            OrganizerResource::canCreate()
        );
    }
}
