<?php

namespace Tests\Feature;

use App\Filament\Resources\Attendees\AttendeeResource;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceAttendeeAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mixed_role_user_only_has_attendee_access_to_permitted_events(): void
    {
        $organization = Organization::create(['name' => 'Workspace authorization']);
        $user = User::factory()->create(['is_super_admin' => false, 'is_gate_staff' => false]);
        $organization->users()->attach($user->id, [
            'role' => User::ORGANIZATION_ROLE_EVENT_MANAGER,
            'status' => User::ORGANIZATION_STATUS_ACTIVE,
            'is_owner' => false,
            'joined_at' => now(),
        ]);

        $allowed = Event::create(['organization_id' => $organization->id, 'name' => 'Managed event', 'status' => Event::STATUS_ACTIVE]);
        $denied = Event::create(['organization_id' => $organization->id, 'name' => 'Communication event', 'status' => Event::STATUS_ACTIVE]);
        $allowed->assignUser($user, User::ORGANIZATION_ROLE_EVENT_MANAGER);
        $denied->assignUser($user, User::ORGANIZATION_ROLE_COMMUNICATION_OFFICER);

        $this->actingAs($user);
        $this->assertTrue(AttendeeResource::canViewAny());
        $this->assertEquals([$allowed->id], AttendeeResource::authorizedAttendeeEvents($user)->pluck('events.id')->all());

        $allowed->suspendAssignedUser($user);
        $this->assertFalse(AttendeeResource::canViewAny());
    }

    public function test_gate_staff_with_super_admin_flag_cannot_access_attendee_resource(): void
    {
        $this->actingAs(User::factory()->create(['is_super_admin' => true, 'is_gate_staff' => true]));
        $this->assertFalse(AttendeeResource::canViewAny());
        $this->assertFalse(AttendeeResource::canCreate());
        $this->assertFalse(AttendeeResource::canImport());
        $this->assertSame(0, AttendeeResource::getEloquentQuery()->count());
    }
}
