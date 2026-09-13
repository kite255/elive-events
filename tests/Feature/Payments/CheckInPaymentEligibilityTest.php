<?php

namespace Tests\Feature\Payments;

use App\Models\Attendee;
use App\Models\Event;
use App\Models\EventPaymentSetting;
use App\Models\Organization;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CheckInPaymentEligibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_reports_completed_payment_for_attendee(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Test Organization',
            'email' => 'payments-test@example.com',
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Payment Test Event',
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
            'block_check_in_if_unpaid' => true,
            'allow_manual_payment' => false,
        ]);

        $attendee = Attendee::query()->create([
            'event_id' => $event->id,
            'full_name' => 'Payment Test Attendee',
            'email' => 'attendee-test@example.com',
            'status' => 'registered',
            'registration_source' => 'manual',
        ]);

        /*
         * No completed payment exists yet.
         */
        $this->assertFalse(
            $event->fresh()
                ->attendeeHasCompletedPayment(
                    $attendee
                )
        );

        /*
         * Create a successful payment for this attendee.
         */
        Payment::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'attendee_id' => $attendee->id,
            'reference' => 'TEST-CHECKIN-PAYMENT-001',
            'amount' => 1000,
            'currency' => 'TZS',
            'status' => Payment::STATUS_COMPLETED,
            'initiated_at' => now(),
            'paid_at' => now(),
        ]);

        /*
         * The event must now recognise the attendee
         * as having a completed payment.
         */
        $this->assertTrue(
            $event->fresh()
                ->attendeeHasCompletedPayment(
                    $attendee
                )
        );
    }

    public function test_pending_payment_does_not_count_as_completed(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Pending Payment Organization',
            'email' => 'pending-test@example.com',
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Pending Payment Event',
            'venue' => 'Test Venue',
            'starts_at' => now()->addDay(),
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
            'block_check_in_if_unpaid' => true,
            'allow_manual_payment' => false,
        ]);

        $attendee = Attendee::query()->create([
            'event_id' => $event->id,
            'full_name' => 'Pending Payment Attendee',
            'email' => 'pending-attendee@example.com',
            'status' => 'registered',
            'registration_source' => 'manual',
        ]);

        Payment::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'attendee_id' => $attendee->id,
            'reference' => 'TEST-PENDING-PAYMENT-001',
            'amount' => 1000,
            'currency' => 'TZS',
            'status' => Payment::STATUS_PENDING,
            'initiated_at' => now(),
        ]);

        $this->assertFalse(
            $event->fresh()
                ->attendeeHasCompletedPayment(
                    $attendee
                )
        );
    }

    public function test_completed_payment_from_another_event_does_not_count(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Multi Event Organization',
            'email' => 'multi-event@example.com',
        ]);

        $eventOne = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Event One',
            'venue' => 'Venue One',
            'starts_at' => now()->addDay(),
            'status' => Event::STATUS_ACTIVE,
            'registration_is_open' => true,
        ]);

        $eventTwo = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Event Two',
            'venue' => 'Venue Two',
            'starts_at' => now()->addDays(2),
            'status' => Event::STATUS_ACTIVE,
            'registration_is_open' => true,
        ]);

        $attendee = Attendee::query()->create([
            'event_id' => $eventOne->id,
            'full_name' => 'Event One Attendee',
            'email' => 'event-one@example.com',
            'status' => 'registered',
            'registration_source' => 'manual',
        ]);

        Payment::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $eventTwo->id,
            'attendee_id' => $attendee->id,
            'reference' => 'TEST-WRONG-EVENT-001',
            'amount' => 1000,
            'currency' => 'TZS',
            'status' => Payment::STATUS_COMPLETED,
            'initiated_at' => now(),
            'paid_at' => now(),
        ]);

        $this->assertFalse(
            $eventOne->fresh()
                ->attendeeHasCompletedPayment(
                    $attendee
                )
        );
    }
}
