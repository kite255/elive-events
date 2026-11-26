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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
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

    public function test_registration_fulfillment_replay_and_distinct_payment_are_rejected_safely(): void
    {
        $payment = $this->approvedPayment();
        $this->mock(BadgeGenerationService::class)->shouldNotReceive('generateForAttendee');
        $this->mock(AutomaticCommunicationService::class)
            ->shouldReceive('handleRegistration')->once()->andReturn(collect());

        $service = app(PaymentFulfillmentService::class);
        $service->fulfill($payment);
        $service->fulfill($payment);
        $this->assertNotNull($payment->fresh()->fulfilled_at);

        $second = $payment->fresh()->replicate();
        $second->reference = 'REG-DUP-'.uniqid();
        $second->fulfilled_at = null;
        $second->save();

        try {
            $service->fulfill($second);
            $this->fail('Distinct payment fulfilled the same registration.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('manual reconciliation', $exception->getMessage());
        }

        $this->assertSame(Payment::STATUS_COMPLETED, $second->fresh()->status);
        $this->assertNull($second->fresh()->fulfilled_at);
    }

    public function test_held_attendee_lock_prevents_fulfillment(): void
    {
        $payment = $this->approvedPayment();
        $this->mock(BadgeGenerationService::class)->shouldNotReceive('generateForAttendee');
        $this->mock(AutomaticCommunicationService::class)->shouldNotReceive('handleRegistration');
        $lock = Cache::lock('payment:registration-fulfillment:'.$payment->attendee_id, 300);
        $this->assertTrue($lock->get());

        try {
            app(PaymentFulfillmentService::class)->fulfill($payment);
            $this->fail('Fulfillment bypassed the held attendee lock.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('already in progress', $exception->getMessage());
        } finally {
            $lock->release();
        }

        $this->assertNull($payment->fresh()->fulfilled_at);
    }

    public function test_existing_badge_is_reused_after_interrupted_fulfillment(): void
    {
        Storage::fake('public');
        $payment = $this->approvedPayment(true);
        Storage::disk('public')->put('test-registration-badge.png', 'existing badge');
        $payment->attendee->forceFill(['badge_path' => 'test-registration-badge.png'])->save();

        $this->mock(BadgeGenerationService::class)->shouldNotReceive('generateForAttendee');
        $communications = $this->mock(AutomaticCommunicationService::class);
        $communications->shouldReceive('handleRegistration')->once()->andThrow(new RuntimeException('Interrupted'));
        $communications->shouldReceive('handleRegistration')->once()->andReturn(collect());
        $service = app(PaymentFulfillmentService::class);

        try {
            $service->fulfill($payment);
            $this->fail('Interrupted fulfillment was treated as successful.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Interrupted', $exception->getMessage());
        }

        $this->assertNull($payment->fresh()->fulfilled_at);
        $service->fulfill($payment);
        $this->assertNotNull($payment->fresh()->fulfilled_at);
    }

    private function approvedPayment(bool $badge = false): Payment
    {
        $organization = Organization::create(['name' => 'Registration replay organization']);
        $event = Event::create([
            'organization_id' => $organization->id,
            'name' => 'Registration replay conference',
            'event_type' => 'conference',
            'status' => Event::STATUS_ACTIVE,
            'registration_auto_generate_badge' => $badge,
        ]);
        $attendee = Attendee::create([
            'event_id' => $event->id,
            'full_name' => 'Approved Participant',
            'status' => 'registered',
            'registration_source' => 'public',
        ]);

        return Payment::create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'attendee_id' => $attendee->id,
            'reference' => 'REG-REPLAY-'.uniqid(),
            'amount' => 50000,
            'currency' => 'TZS',
            'status' => Payment::STATUS_COMPLETED,
            'paid_at' => now(),
        ]);
    }
}
