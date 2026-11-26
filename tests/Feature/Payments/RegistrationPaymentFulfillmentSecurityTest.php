<?php

namespace Tests\Feature\Payments;

use App\Models\Attendee;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Payment;
use App\Services\AutomaticCommunicationService;
use App\Services\BadgeGenerationService;
use App\Services\Payments\PaymentFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class RegistrationPaymentFulfillmentSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_cross_organization_registration_payment_is_not_fulfilled(): void
    {
        $this->assertRejectedRegistration(true, 'registered', 'Attendee event does not belong to the payment organization.');
    }

    public function test_unapproved_registration_payment_is_not_fulfilled(): void
    {
        $this->assertRejectedRegistration(false, 'pending_approval', 'Registration is no longer eligible for fulfillment.');
    }

    private function assertRejectedRegistration(bool $foreign, string $status, string $message): void
    {
        $organization = Organization::create(['name' => 'Registration fulfillment organization']);
        $other = Organization::create(['name' => 'Other fulfillment organization']);
        $event = Event::create([
            'organization_id' => $organization->id,
            'name' => 'Registration fulfillment conference',
            'event_type' => 'conference',
            'status' => Event::STATUS_ACTIVE,
            'registration_auto_generate_badge' => true,
        ]);
        $attendee = Attendee::create([
            'event_id' => $event->id,
            'full_name' => 'Test Participant',
            'status' => $status,
            'registration_source' => 'public',
        ]);
        $payment = Payment::create([
            'organization_id' => $foreign ? $other->id : $organization->id,
            'event_id' => $event->id,
            'attendee_id' => $attendee->id,
            'reference' => 'REG-SEC-'.uniqid(),
            'amount' => 50000,
            'currency' => 'TZS',
            'status' => Payment::STATUS_COMPLETED,
            'paid_at' => now(),
        ]);

        $this->mock(BadgeGenerationService::class)->shouldNotReceive('generateForAttendee');
        $this->mock(AutomaticCommunicationService::class)->shouldNotReceive('handleRegistration');

        try {
            app(PaymentFulfillmentService::class)->fulfill($payment);
            $this->fail('Ineligible registration payment was fulfilled.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());
        }

        $this->assertSame(Payment::STATUS_COMPLETED, $payment->fresh()->status);
        $this->assertNull($payment->fresh()->fulfilled_at);
    }
}
