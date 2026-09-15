<?php

namespace Tests\Feature\Authorization;

use App\Filament\Resources\Organizers\OrganizerResource;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizerResourceScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_organizer_resource_only_returns_ticket_organizers(): void
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

        $ticketOrganizer = User::query()->create([
            'name' => 'Ticket Organizer',
            'email' => 'organizer@example.com',
            'password' => 'password',
            'is_super_admin' => false,
        ]);

        $ticketOrganizer->organizations()->attach(
            $organization->id,
            [
                'role' =>
                    User::ORGANIZATION_ROLE_TICKET_ORGANIZER,

                'status' =>
                    User::ORGANIZATION_STATUS_ACTIVE,

                'is_owner' => false,
                'joined_at' => now(),
            ]
        );

        $regularUser = User::query()->create([
            'name' => 'Regular User',
            'email' => 'regular@example.com',
            'password' => 'password',
            'is_super_admin' => false,
        ]);

        $regularUser->organizations()->attach(
            $organization->id,
            [
                'role' =>
                    User::ORGANIZATION_ROLE_MEMBER,

                'status' =>
                    User::ORGANIZATION_STATUS_ACTIVE,

                'is_owner' => false,
                'joined_at' => now(),
            ]
        );

        $this->actingAs($admin);

        $ids = OrganizerResource::getEloquentQuery()
            ->pluck('users.id');

        $this->assertTrue(
            $ids->contains($ticketOrganizer->id)
        );

        $this->assertFalse(
            $ids->contains($regularUser->id)
        );

        $this->assertFalse(
            $ids->contains($admin->id)
        );
    }
}
