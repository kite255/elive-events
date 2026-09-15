<?php

namespace Tests\Feature\Organizers;

use App\Models\Organization;
use App\Models\User;
use App\Services\Organizers\OrganizerManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizerReassignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_reassigning_ticket_organizer_moves_them_to_new_organization(): void
    {
        $oldOrganization = Organization::query()->create([
            'name' => 'Old Events Ltd',
            'email' => 'old-events@example.com',
        ]);

        $newOrganization = Organization::query()->create([
            'name' => 'New Events Ltd',
            'email' => 'new-events@example.com',
        ]);

        $organizer = User::query()->create([
            'name' => 'Ticket Organizer',
            'email' => 'organizer@example.com',
            'password' => 'password',
            'is_super_admin' => false,
        ]);

        $organizer->organizations()->attach(
            $oldOrganization->id,
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

        app(
            OrganizerManagementService::class
        )->updateAssignment(
            $organizer,
            [
                'name' => $organizer->name,
                'email' => $organizer->email,
                'password' => null,
                'organization_id' => $newOrganization->id,
                'status' => User::ORGANIZATION_STATUS_ACTIVE,
            ]
        );

        $this->assertDatabaseMissing(
            'organization_user',
            [
                'organization_id' =>
                    $oldOrganization->id,

                'user_id' =>
                    $organizer->id,

                'role' =>
                    User::ORGANIZATION_ROLE_TICKET_ORGANIZER,
            ]
        );

        $this->assertDatabaseHas(
            'organization_user',
            [
                'organization_id' =>
                    $newOrganization->id,

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

        $this->assertSame(
            1,
            $organizer
                ->fresh()
                ->ticketOrganizerOrganizations()
                ->count()
        );
    }

    public function test_reassignment_does_not_remove_other_non_ticket_organizer_membership(): void
    {
        $memberOrganization = Organization::query()->create([
            'name' => 'Member Organization',
            'email' => 'member-org@example.com',
        ]);

        $oldTicketOrganization = Organization::query()->create([
            'name' => 'Old Ticket Organization',
            'email' => 'old-ticket@example.com',
        ]);

        $newTicketOrganization = Organization::query()->create([
            'name' => 'New Ticket Organization',
            'email' => 'new-ticket@example.com',
        ]);

        $organizer = User::query()->create([
            'name' => 'Ticket Organizer',
            'email' => 'organizer@example.com',
            'password' => 'password',
            'is_super_admin' => false,
        ]);

        $organizer->organizations()->attach(
            $memberOrganization->id,
            [
                'role' => 'member',
                'status' =>
                    User::ORGANIZATION_STATUS_ACTIVE,
                'is_owner' => false,
                'joined_at' => now(),
            ]
        );

        $organizer->organizations()->attach(
            $oldTicketOrganization->id,
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

        app(
            OrganizerManagementService::class
        )->updateAssignment(
            $organizer,
            [
                'name' => $organizer->name,
                'email' => $organizer->email,
                'password' => null,
                'organization_id' => $newTicketOrganization->id,
                'status' => User::ORGANIZATION_STATUS_ACTIVE,
            ]
        );

        $this->assertDatabaseHas(
            'organization_user',
            [
                'organization_id' =>
                    $memberOrganization->id,

                'user_id' =>
                    $organizer->id,

                'role' =>
                    'member',
            ]
        );
    }
}
