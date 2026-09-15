<?php

namespace Tests\Feature\Organizers;

use App\Filament\Resources\Organizers\Pages\EditOrganizer;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrganizerFilamentEditPrefillTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_form_prefills_current_organization_and_status(): void
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
            ->assertFormSet([
                'name' =>
                    'Ticket Organizer',

                'email' =>
                    'organizer@example.com',

                'organization_id' =>
                    $organization->id,

                'status' =>
                    User::ORGANIZATION_STATUS_ACTIVE,

                'password' =>
                    null,
            ]);
    }
}
