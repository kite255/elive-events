<?php

namespace Tests\Feature\Authorization;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketOrganizerRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_ticket_organizer_has_ticket_organizer_organization(): void
    {
        $organization = Organization::query()->create([
            'name' => 'ABC Events',
            'email' => 'abc-events@example.com',
        ]);

        $user = User::query()->create([
            'name' => 'John Organizer',
            'email' => 'john.organizer@example.com',
            'password' => 'password',
            'is_super_admin' => false,
        ]);

        $user->organizations()->attach(
            $organization->id,
            [
                'role' => User::ORGANIZATION_ROLE_TICKET_ORGANIZER,
                'status' => User::ORGANIZATION_STATUS_ACTIVE,
                'is_owner' => false,
                'joined_at' => now(),
            ]
        );

        $organizationIds = $user
            ->ticketOrganizerOrganizations()
            ->pluck('organizations.id')
            ->all();

        $this->assertSame(
            [$organization->id],
            $organizationIds
        );

        $this->assertTrue(
            $user->isTicketOrganizer()
        );
    }

    public function test_inactive_ticket_organizer_has_no_ticket_organizer_organizations(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Inactive Events',
            'email' => 'inactive-events@example.com',
        ]);

        $user = User::query()->create([
            'name' => 'Inactive Organizer',
            'email' => 'inactive.organizer@example.com',
            'password' => 'password',
            'is_super_admin' => false,
        ]);

        $user->organizations()->attach(
            $organization->id,
            [
                'role' => User::ORGANIZATION_ROLE_TICKET_ORGANIZER,
                'status' => User::ORGANIZATION_STATUS_INACTIVE,
                'is_owner' => false,
                'joined_at' => now(),
            ]
        );

        $this->assertSame(
            0,
            $user->ticketOrganizerOrganizations()->count()
        );

        $this->assertFalse(
            $user->isTicketOrganizer()
        );
    }

    public function test_suspended_ticket_organizer_has_no_ticket_organizer_organizations(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Suspended Events',
            'email' => 'suspended-events@example.com',
        ]);

        $user = User::query()->create([
            'name' => 'Suspended Organizer',
            'email' => 'suspended.organizer@example.com',
            'password' => 'password',
            'is_super_admin' => false,
        ]);

        $user->organizations()->attach(
            $organization->id,
            [
                'role' => User::ORGANIZATION_ROLE_TICKET_ORGANIZER,
                'status' => User::ORGANIZATION_STATUS_SUSPENDED,
                'is_owner' => false,
                'joined_at' => now(),
            ]
        );

        $this->assertSame(
            0,
            $user->ticketOrganizerOrganizations()->count()
        );

        $this->assertFalse(
            $user->isTicketOrganizer()
        );
    }

    public function test_regular_member_is_not_a_ticket_organizer(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Regular Organization',
            'email' => 'regular@example.com',
        ]);

        $user = User::query()->create([
            'name' => 'Regular Member',
            'email' => 'regular.member@example.com',
            'password' => 'password',
            'is_super_admin' => false,
        ]);

        $user->organizations()->attach(
            $organization->id,
            [
                'role' => User::ORGANIZATION_ROLE_MEMBER,
                'status' => User::ORGANIZATION_STATUS_ACTIVE,
                'is_owner' => false,
                'joined_at' => now(),
            ]
        );

        $this->assertSame(
            0,
            $user->ticketOrganizerOrganizations()->count()
        );

        $this->assertFalse(
            $user->isTicketOrganizer()
        );
    }
}