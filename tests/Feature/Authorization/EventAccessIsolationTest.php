<?php

namespace Tests\Feature\Authorization;

use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventAccessIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_access_all_events(): void
    {
        [$a, $b, $c] = $this->events();
        $user = User::factory()->create(['is_super_admin' => true]);

        $this->assertAccess($user, [$a, $b, $c], []);
    }

    public function test_organization_admin_cannot_access_another_organization(): void
    {
        [$a, $b, $c] = $this->events();
        $user = $this->user();
        $this->membership($user, $a, User::ORGANIZATION_ROLE_ADMIN);

        $this->assertAccess($user, [$a, $b], [$c]);
    }

    public function test_owner_flag_grants_consistent_query_and_record_access(): void
    {
        [$a, $b, $c] = $this->events();
        $user = $this->user();
        $this->membership($user, $a, User::ORGANIZATION_ROLE_MEMBER, true);

        $this->assertAccess($user, [$a, $b], [$c]);
    }

    public function test_event_manager_can_only_access_assigned_events(): void
    {
        [$a, $b, $c] = $this->events();
        $user = $this->user();
        $this->membership($user, $a);
        $user->assignToEvent($a, User::ORGANIZATION_ROLE_EVENT_MANAGER);

        $this->assertAccess($user, [$a], [$b, $c]);
    }

    public function test_suspended_membership_overrides_active_event_assignment(): void
    {
        [$a, $b, $c] = $this->events();
        $user = $this->user();
        $this->membership($user, $a);
        $user->assignToEvent($a, User::ORGANIZATION_ROLE_EVENT_MANAGER);
        $this->assertAccess($user, [$a], [$b, $c]);

        $user->organizations()->updateExistingPivot($a->organization_id, [
            'status' => User::ORGANIZATION_STATUS_SUSPENDED,
        ]);

        $this->assertAccess($user, [], [$a, $b, $c]);
    }

    public function test_suspended_and_removed_assignments_revoke_access(): void
    {
        [$a, $b, $c] = $this->events();
        $user = $this->user();
        $this->membership($user, $a);
        $user->assignToEvent($a, User::ORGANIZATION_ROLE_EVENT_MANAGER);
        $this->assertAccess($user, [$a], [$b, $c]);

        $user->suspendEventAssignment($a);
        $this->assertAccess($user, [], [$a, $b, $c]);

        $user->assignToEvent($a, User::ORGANIZATION_ROLE_EVENT_MANAGER);
        $this->assertAccess($user, [$a], [$b, $c]);
        $user->removeEventAssignment($a);
        $this->assertAccess($user, [], [$a, $b, $c]);
    }

    public function test_assignment_without_organization_membership_is_not_authorized(): void
    {
        [$a, $b, $c] = $this->events();
        $user = $this->user();
        $this->membership($user, $a);
        $user->assignToEvent($c, User::ORGANIZATION_ROLE_EVENT_MANAGER);

        $this->assertAccess($user, [], [$a, $b, $c]);
    }

    public function test_membership_suspension_is_isolated_to_one_organization(): void
    {
        [$a, $b, $c] = $this->events();
        $user = $this->user();
        $this->membership($user, $a);
        $this->membership($user, $c, User::ORGANIZATION_ROLE_ADMIN);
        $user->assignToEvent($a, User::ORGANIZATION_ROLE_EVENT_MANAGER);

        $this->assertAccess($user, [$a, $c], [$b]);

        $user->organizations()->updateExistingPivot($a->organization_id, [
            'status' => User::ORGANIZATION_STATUS_SUSPENDED,
        ]);
        $this->assertAccess($user, [$c], [$a, $b]);
    }

    public function test_guest_has_no_event_access(): void
    {
        [$a, $b, $c] = $this->events();

        $this->assertAccess(null, [], [$a, $b, $c]);
    }

    private function user(): User
    {
        return User::factory()->create(['is_super_admin' => false]);
    }

    private function membership(User $user, Event $event, string $role = User::ORGANIZATION_ROLE_EVENT_MANAGER, bool $owner = false): void
    {
        $user->organizations()->attach($event->organization_id, [
            'role' => $role,
            'status' => User::ORGANIZATION_STATUS_ACTIVE,
            'is_owner' => $owner,
            'joined_at' => now(),
        ]);
    }

    private function events(): array
    {
        $first = Organization::create(['name' => 'First Organization', 'slug' => 'first-organization']);
        $second = Organization::create(['name' => 'Second Organization', 'slug' => 'second-organization']);

        return [
            Event::create(['organization_id' => $first->id, 'name' => 'First Event', 'slug' => 'first-event', 'status' => Event::STATUS_ACTIVE]),
            Event::create(['organization_id' => $first->id, 'name' => 'Second Event', 'slug' => 'second-event', 'status' => Event::STATUS_ACTIVE]),
            Event::create(['organization_id' => $second->id, 'name' => 'Third Event', 'slug' => 'third-event', 'status' => Event::STATUS_ACTIVE]),
        ];
    }

    private function assertAccess(?User $user, array $allowed, array $denied): void
    {
        $this->assertEqualsCanonicalizing(
            array_map(fn (Event $event) => $event->id, $allowed),
            Event::query()->accessibleBy($user)->pluck('events.id')->all()
        );

        foreach ($allowed as $event) {
            $this->assertTrue($event->isAccessibleBy($user));
            $this->assertTrue($event->canBeManagedBy($user));
        }

        foreach ($denied as $event) {
            $this->assertFalse($event->isAccessibleBy($user));
            $this->assertFalse($event->canBeManagedBy($user));
        }
    }
}
