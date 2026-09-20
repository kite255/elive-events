<?php

namespace Tests\Feature\Organizers;

use App\Filament\Resources\Organizers\Pages\EditOrganizer;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class OrganizerFilamentProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_update_ticket_organizer_profile(): void
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
            'name' => 'Old Organizer Name',
            'email' => 'old-organizer@example.com',
            'password' => 'old-password',
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
                'record' => $organizer->getRouteKey(),
            ]
        )
            ->fillForm([
                'name' => 'Updated Organizer Name',
                'email' => 'updated-organizer@example.com',
                'password' => 'new-secret-password',
                'organization_id' => $organization->id,
                'status' => User::ORGANIZATION_STATUS_ACTIVE,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $organizer->refresh();

        $this->assertSame(
            'Updated Organizer Name',
            $organizer->name
        );

        $this->assertSame(
            'updated-organizer@example.com',
            $organizer->email
        );

        $this->assertTrue(
            Hash::check(
                'new-secret-password',
                $organizer->password
            )
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
                    User::ORGANIZATION_STATUS_ACTIVE,
            ]
        );
    }

    public function test_blank_password_keeps_existing_password(): void
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
            'password' => 'existing-password',
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

        $originalPasswordHash =
            $organizer->password;

        $this->actingAs($admin);

        Livewire::test(
            EditOrganizer::class,
            [
                'record' => $organizer->getRouteKey(),
            ]
        )
            ->fillForm([
                'name' => 'Ticket Organizer Updated',
                'email' => 'organizer@example.com',
                'password' => null,
                'organization_id' => $organization->id,
                'status' => User::ORGANIZATION_STATUS_ACTIVE,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $organizer->refresh();

        $this->assertSame(
            'Ticket Organizer Updated',
            $organizer->name
        );

        $this->assertSame(
            $originalPasswordHash,
            $organizer->password
        );
    }
}
