<?php

namespace Tests\Feature\Authorization;

use App\Models\Attendee;
use App\Models\Event;
use App\Models\EventCommunication;
use App\Models\Organization;
use App\Models\User;
use App\Services\BadgeGenerationService;
use App\Services\QrTokenService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectEventRouteAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_mixed_event_badge_batch_is_rejected_before_generation(): void
    {
        [$event, $other] = $this->events();
        $user = $this->member($event, User::ORGANIZATION_ROLE_ADMIN);
        $first = Attendee::create(['event_id' => $event->id, 'full_name' => 'Allowed']);
        $second = Attendee::create(['event_id' => $other->id, 'full_name' => 'Restricted']);

        $this->mock(BadgeGenerationService::class, fn ($mock) => $mock->shouldNotReceive('generateForAttendee'));
        $this->actingAs($user)->get(route('badges.print', ['ids' => $first->id.','.$second->id]))->assertForbidden();
        $this->assertNull($first->fresh()->badge_path);
        $this->assertNull($second->fresh()->badge_path);
    }

    public function test_communication_preview_requires_communication_permission(): void
    {
        [$event, $other] = $this->events();
        $user = $this->member($event, User::ORGANIZATION_ROLE_CHECK_IN_OFFICER);
        $user->assignToEvent($event, User::ORGANIZATION_ROLE_CHECK_IN_OFFICER);
        $communication = EventCommunication::create(['event_id' => $event->id, 'title' => 'Private draft', 'is_public' => false]);

        $this->assertTrue($event->isAccessibleBy($user));
        $this->actingAs($user)->get(route('admin.event-communications.preview', $communication))->assertForbidden();
    }

    public function test_cross_organization_qr_check_in_is_forbidden_without_attendee_disclosure(): void
    {
        [$event, $other] = $this->events();
        $user = $this->member($event, User::ORGANIZATION_ROLE_ADMIN);
        $attendee = Attendee::create(['event_id' => $other->id, 'full_name' => 'Restricted attendee']);
        $token = app(QrTokenService::class)->getTokenForAttendee($attendee);

        $this->actingAs($user)->get(route('qr.check-in', $token))->assertForbidden()->assertDontSee('Restricted attendee');
        $this->assertDatabaseMissing('check_ins', ['attendee_id' => $attendee->id]);
        $this->assertNull($attendee->fresh()->checked_in_at);
    }

    public function test_gate_staff_with_admin_role_can_only_check_in_assigned_events(): void
    {
        [$event, $other] = $this->events();
        $user = $this->member($event, User::ORGANIZATION_ROLE_ADMIN);
        $user->forceFill(['is_gate_staff' => true])->save();
        $user->assignToEvent($event, User::ORGANIZATION_ROLE_CHECK_IN_OFFICER);

        $this->assertTrue($user->canUseGateScanner());
        $this->assertTrue($event->canBeCheckedInBy($user));
        $this->assertFalse($other->canBeCheckedInBy($user));
        $this->assertFalse($event->canBeManagedBy($user));
        $this->assertFalse($event->canManageRegistrationBy($user));
        $this->assertFalse($event->canManageBadgesBy($user));
        $this->assertFalse($event->canManageCommunicationBy($user));
        $this->assertFalse($event->canViewReportsBy($user));
        $this->assertFalse($event->canResetTicketCheckInsBy($user));

        $this->actingAs($user)->get(route('badges.print'))->assertForbidden();
        $communication = EventCommunication::create(['event_id' => $event->id, 'title' => 'Gate restricted']);
        $this->get(route('admin.event-communications.preview', $communication))->assertForbidden();
    }

    public function test_gate_flag_overrides_super_admin_for_event_report_download(): void
    {
        [$event] = $this->events();
        $user = User::factory()->create(['is_super_admin' => true, 'is_gate_staff' => true]);

        $this->assertFalse($user->canManageEvent($event));
        $this->assertFalse($user->canManageEventRegistration($event));
        $this->assertFalse($user->canManageEventBadges($event));
        $this->assertFalse($user->canManageEventCommunication($event));
        $this->assertFalse($user->canViewEventReports($event));
        $this->actingAs($user)->get(route('admin.events.reports.download', $event->id))->assertForbidden();
    }

    private function events(): array
    {
        $a = Organization::create(['name' => 'Route Organization A']);
        $b = Organization::create(['name' => 'Route Organization B']);

        return [
            Event::create(['organization_id' => $a->id, 'name' => 'Route Event A', 'slug' => 'route-event-a', 'status' => Event::STATUS_ACTIVE]),
            Event::create(['organization_id' => $b->id, 'name' => 'Route Event B', 'slug' => 'route-event-b', 'status' => Event::STATUS_ACTIVE]),
        ];
    }

    private function member(Event $event, string $role): User
    {
        $user = User::factory()->create(['is_super_admin' => false]);
        $user->organizations()->attach($event->organization_id, [
            'role' => $role,
            'status' => User::ORGANIZATION_STATUS_ACTIVE,
            'is_owner' => false,
            'joined_at' => now(),
        ]);

        return $user;
    }
}
