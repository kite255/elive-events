<?php

namespace Tests\Feature\Authorization;

use App\Filament\Resources\Organizers\OrganizerResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizerResourcePagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_open_organizer_list_and_create_pages(): void
    {
        $admin = User::query()->create([
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => 'password',
            'is_super_admin' => true,
        ]);

        $this->actingAs($admin);

        $this->get(
            OrganizerResource::getUrl('index')
        )->assertOk();

        $this->get(
            OrganizerResource::getUrl('create')
        )->assertOk();
    }

    public function test_non_super_admin_cannot_open_organizer_pages(): void
    {
        $user = User::query()->create([
            'name' => 'Regular User',
            'email' => 'regular@example.com',
            'password' => 'password',
            'is_super_admin' => false,
        ]);

        $this->actingAs($user);

        $indexResponse = $this->get(
            OrganizerResource::getUrl('index')
        );

        $createResponse = $this->get(
            OrganizerResource::getUrl('create')
        );

        $this->assertContains(
            $indexResponse->getStatusCode(),
            [
                403,
                404,
            ]
        );

        $this->assertContains(
            $createResponse->getStatusCode(),
            [
                403,
                404,
            ]
        );
    }
}
