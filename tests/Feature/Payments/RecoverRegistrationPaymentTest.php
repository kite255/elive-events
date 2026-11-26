<?php

namespace Tests\Feature\Payments;

use App\Models\Attendee;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Services\Payments\PaymentFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecoverRegistrationPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function payment(bool $evidence = true): Payment
    {
        $organization = Organization::create(['name' => 'Recovery test organization']);
        $event = Event::create([
            'organization_id' => $organization->id,
            'name' => 'Recovery test event',
            'status' => Event::STATUS_ACTIVE,
        ]);
        $attendee = Attendee::create([
            'event_id' => $event->id,
            'full_name' => 'Recovery test participant',
            'status' => 'registered',
            'registration_source' => 'public',
        ]);
        $payment = Payment::create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'attendee_id' => $attendee->id,
            'reference' => 'REC-'.uniqid(),
            'provider_tracking_id' => 'TRACK-'.uniqid(),
            'amount' => 50000,
            'currency' => 'TZS',
            'status' => Payment::STATUS_COMPLETED,
            'paid_at' => now(),
        ]);

        if ($evidence) {
            $payment->transactions()->create([
                'type' => PaymentTransaction::TYPE_PAYMENT_STATUS,
                'status' => Payment::STATUS_COMPLETED,
                'response_payload' => [
                    'payment_status_description' => 'COMPLETED',
                    'merchant_reference' => $payment->reference,
                    'order_tracking_id' => $payment->provider_tracking_id,
                    'amount' => 50000,
                    'currency' => 'TZS',
                ],
            ]);
        }

        return $payment;
    }

    public function test_preview_does_not_fulfill_payment(): void
    {
        $payment = $this->payment();
        $this->mock(PaymentFulfillmentService::class)->shouldNotReceive('fulfill');

        $this->artisan('payments:recover-registration', ['payment' => $payment->id])
            ->expectsOutput('Preview only. Independently verify current provider status before using --execute.')
            ->assertExitCode(0);

        $this->assertNull($payment->fresh()->fulfilled_at);
    }

    public function test_missing_provider_evidence_blocks_recovery(): void
    {
        $payment = $this->payment(false);
        $this->mock(PaymentFulfillmentService::class)->shouldNotReceive('fulfill');

        $this->artisan('payments:recover-registration', ['payment' => $payment->id, '--execute' => true])
            ->assertExitCode(1);

        $this->assertNull($payment->fresh()->fulfilled_at);
    }

    public function test_conflicting_completed_payment_blocks_recovery(): void
    {
        $payment = $this->payment();
        $duplicate = $payment->replicate();
        $duplicate->reference = 'CONFLICT-'.uniqid();
        $duplicate->provider_tracking_id = 'TRACK-'.uniqid();
        $duplicate->save();
        $this->mock(PaymentFulfillmentService::class)->shouldNotReceive('fulfill');

        $this->artisan('payments:recover-registration', ['payment' => $payment->id])
            ->expectsOutput('Conflicting registration payment exists. Manual reconciliation required.')
            ->assertExitCode(1);
    }

    public function test_declining_confirmation_does_not_fulfill_payment(): void
    {
        $payment = $this->payment();
        $this->mock(PaymentFulfillmentService::class)->shouldNotReceive('fulfill');

        $this->artisan('payments:recover-registration', ['payment' => $payment->id, '--execute' => true])
            ->expectsConfirmation('Have you independently verified this exact payment is still completed and not reversed at the provider?', 'no')
            ->assertExitCode(1);

        $this->assertNull($payment->fresh()->fulfilled_at);
    }
}
