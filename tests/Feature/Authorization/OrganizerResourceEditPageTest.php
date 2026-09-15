<?php

namespace Tests\Feature\Authorization;

use App\Filament\Resources\Organizers\OrganizerResource;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizerResourceEditPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_open_ticket_organizer_edit_page(): void
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

        $this->get(
            OrganizerResource::getUrl(
                'edit',
                [
                    'record' => $organizer,
                ]
            )
        )->assertOk();
    }

    public function test_ticket_organizer_cannot_open_organizer_edit_page(): void
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

                'is_owner' =>
                    false,

                'joined_at' =>
                    now(),
            ]
        );

        $this->actingAs($organizer);

        $response = $this->get(
            OrganizerResource::getUrl(
                'edit',
                [
                    'record' => $organizer,
                ]
            )
        );

        $this->assertContains(
            $response->getStatusCode(),
            [
                403,
                404,
            ]
        );
    }
}
