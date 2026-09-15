<?php

namespace Tests\Feature\Organizers;

use App\Filament\Resources\Organizers\Pages\CreateOrganizer;
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

        $this->actingAs($admin);

        Livewire::test(CreateOrganizer::class)
            ->fillForm([
                'name' => 'Concert Organizer',
                'email' => 'organizer@example.com',
                'password' => 'secret-password',
                'organization_id' => $organization->id,
                'status' => User::ORGANIZATION_STATUS_ACTIVE,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $organizer = User::query()
            ->where('email', 'organizer@example.com')
            ->first();

        $this->assertNotNull($organizer);

        $this->assertDatabaseHas(
            'organization_user',
            [
                'organization_id' => $organization->id,
                'user_id' => $organizer->id,
                'role' => User::ORGANIZATION_ROLE_TICKET_ORGANIZER,
                'status' => User::ORGANIZATION_STATUS_ACTIVE,
                'is_owner' => false,
            ]
        );
    }
}
