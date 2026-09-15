<?php

namespace Tests\Feature\Organizers;

use App\Filament\Resources\Organizers\Pages\EditOrganizer;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrganizerFilamentEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_suspend_ticket_organizer_from_filament(): void
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

        $this->actingAs($admin);

        Livewire::test(
            EditOrganizer::class,
            [
                'record' => $organizer->getRouteKey(),
            ]
        )
            ->fillForm([
                'name' => 'Ticket Organizer',
                'email' => 'organizer@example.com',
                'password' => null,
                'organization_id' => $organization->id,
                'status' => User::ORGANIZATION_STATUS_SUSPENDED,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

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

        $this->assertSame(
            1,
            User::query()
                ->where(
                    'email',
                    'organizer@example.com'
                )
                ->count()
        );
    }
}
