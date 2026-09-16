<?php

namespace Tests\Feature\Authorization;

use App\Filament\Pages\AdminTicketLookup;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketOrganizerManualLookupAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_organizer_can_access_manual_lookup_page(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Organizer A',
            'email' => 'orga@example.com',
        ]);

        $organizer = User::query()->create([
            'name' => 'Ticket Organizer A',
            'email' => 'organizer-a@example.com',
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

        $this->assertTrue(
            AdminTicketLookup::canAccess()
        );
    }
}