<?php

namespace Tests\Feature\Payments;

use App\Models\Attendee;
use App\Models\Event;
use App\Models\EventPaymentSetting;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\User;
use App\Services\CheckInService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckInPaymentEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_unpaid_attendee_is_blocked_when_payment_is_required_for_check_in(): void
    {
        [
            'event' => $event,
            'attendee' => $attendee,
        ] = $this->createCheckInScenario(true);

        $result = app(CheckInService::class)->checkIn(
            attendee: $attendee,
            method: CheckInService::METHOD_MANUAL
        );

        $this->assertFalse($result['success']);
        $this->assertSame('payment_required', $result['status']);

        $this->assertDatabaseMissing('check_ins', [
            'event_id' => $event->id,
            'attendee_id' => $attendee->id,
        ]);

        $this->assertNull(
            $attendee->fresh()->checked_in_at
        );
    }

    public function test_paid_attendee_can_check_in_when_payment_blocking_is_enabled(): void
    {
        [
            'organization' => $organization,
            'event' => $event,
            'attendee' => $attendee,
        ] = $this->createCheckInScenario(true);

        Payment::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'attendee_id' => $attendee->id,
            'reference' => 'CHECKIN-PAID-001',
            'amount' => 1000,
            'currency' => 'TZS',
            'status' => Payment::STATUS_COMPLETED,
            'initiated_at' => now(),
            'paid_at' => now(),
        ]);

        $result = app(CheckInService::class)->checkIn(
            attendee: $attendee,
            method: CheckInService::METHOD_MANUAL
        );

        $this->assertTrue($result['success']);
        $this->assertSame('checked_in', $result['status']);

        $this->assertDatabaseHas('check_ins', [
            'event_id' => $event->id,
            'attendee_id' => $attendee->id,
        ]);

        $this->assertNotNull(
            $attendee->fresh()->checked_in_at
        );
    }

    public function test_unpaid_attendee_can_check_in_when_payment_blocking_is_disabled(): void
    {
        [
            'event' => $event,
            'attendee' => $attendee,
        ] = $this->createCheckInScenario(false);

        $result = app(CheckInService::class)->checkIn(
            attendee: $attendee,
            method: CheckInService::METHOD_MANUAL
        );

        $this->assertTrue($result['success']);
        $this->assertSame('checked_in', $result['status']);

        $this->assertDatabaseHas('check_ins', [
            'event_id' => $event->id,
            'attendee_id' => $attendee->id,
        ]);

        $this->assertNotNull(
            $attendee->fresh()->checked_in_at
        );
    }

    private function createCheckInScenario(
        bool $blockUnpaidCheckIn
    ): array {
        $organization = Organization::query()->create([
            'name' => 'Check-in Payment Test Organization',
            'email' => 'checkin-payments@example.com',
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Check-in Payment Test Event',
            'venue' => 'Test Venue',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
            'status' => Event::STATUS_ACTIVE,
            'registration_is_open' => true,
        ]);

        EventPaymentSetting::query()->create([
            'event_id' => $event->id,
            'payments_enabled' => true,
            'currency' => 'TZS',
            'registration_fee' => 1000,
            'payment_required_before_confirmation' => false,
            'payment_required_before_badge' => false,
            'block_check_in_if_unpaid' => $blockUnpaidCheckIn,
            'allow_manual_payment' => false,
        ]);

        $attendee = Attendee::query()->create([
            'event_id' => $event->id,
            'full_name' => 'Check-in Payment Test Attendee',
            'email' => 'checkin-attendee@example.com',
            'status' => 'registered',
            'registration_source' => 'manual',
        ]);

        $officer = User::factory()->create();

        $officer->organizations()->attach(
            $organization->id,
            [
                'role' => User::ORGANIZATION_ROLE_OWNER,
                'status' => User::ORGANIZATION_STATUS_ACTIVE,
                'is_owner' => true,
                'joined_at' => now(),
            ]
        );

        $this->actingAs($officer);

        return [
            'organization' => $organization,
            'event' => $event,
            'attendee' => $attendee,
            'officer' => $officer,
        ];
    }
}
