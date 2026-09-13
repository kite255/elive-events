<?php

namespace Tests\Feature\Payments;

use App\Models\Attendee;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Payment;
use App\Services\Payments\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class ReconcilePendingPaymentsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_reconciliation_command_exists(): void
    {
        $this->artisan('payments:reconcile')
            ->assertExitCode(0);
    }

    public function test_pending_payment_is_synchronized_with_gateway(): void
    {
        $payment = $this->createPayment(
            Payment::STATUS_PENDING,
            'tracking-pending-001'
        );

        $this->mock(
            PaymentService::class,
            function (MockInterface $mock) use ($payment): void {
                $mock->shouldReceive('syncFromGateway')
                    ->once()
                    ->withArgs(
                        fn (Payment $receivedPayment) =>
                            $receivedPayment->is($payment)
                    )
                    ->andReturn($payment);
            }
        );

        $this->artisan('payments:reconcile')
            ->assertExitCode(0);
    }

    public function test_processing_payment_is_synchronized_with_gateway(): void
    {
        $payment = $this->createPayment(
            Payment::STATUS_PROCESSING,
            'tracking-processing-001'
        );

        $this->mock(
            PaymentService::class,
            function (MockInterface $mock) use ($payment): void {
                $mock->shouldReceive('syncFromGateway')
                    ->once()
                    ->withArgs(
                        fn (Payment $receivedPayment) =>
                            $receivedPayment->is($payment)
                    )
                    ->andReturn($payment);
            }
        );

        $this->artisan('payments:reconcile')
            ->assertExitCode(0);
    }

    public function test_pending_payment_without_tracking_id_is_skipped(): void
    {
        $this->createPayment(
            Payment::STATUS_PENDING,
            null
        );

        $this->mock(
            PaymentService::class,
            function (MockInterface $mock): void {
                $mock->shouldNotReceive(
                    'syncFromGateway'
                );
            }
        );

        $this->artisan('payments:reconcile')
            ->assertExitCode(0);
    }

    public function test_completed_payment_is_skipped(): void
    {
        $this->createPayment(
            Payment::STATUS_COMPLETED,
            'tracking-completed-001'
        );

        $this->mock(
            PaymentService::class,
            function (MockInterface $mock): void {
                $mock->shouldNotReceive(
                    'syncFromGateway'
                );
            }
        );

        $this->artisan('payments:reconcile')
            ->assertExitCode(0);
    }

    public function test_one_failed_reconciliation_does_not_stop_other_payments(): void
    {
        $firstPayment = $this->createPayment(
            Payment::STATUS_PENDING,
            'tracking-failed-001'
        );

        $secondPayment = $this->createPayment(
            Payment::STATUS_PENDING,
            'tracking-success-001'
        );

        $this->mock(
            PaymentService::class,
            function (MockInterface $mock) use (
                $firstPayment,
                $secondPayment
            ): void {
                $mock->shouldReceive('syncFromGateway')
                    ->once()
                    ->withArgs(
                        fn (Payment $payment) =>
                            $payment->is($firstPayment)
                    )
                    ->andThrow(
                        new RuntimeException(
                            'Gateway temporarily unavailable.'
                        )
                    );

                $mock->shouldReceive('syncFromGateway')
                    ->once()
                    ->withArgs(
                        fn (Payment $payment) =>
                            $payment->is($secondPayment)
                    )
                    ->andReturn($secondPayment);
            }
        );

        $this->artisan('payments:reconcile')
            ->assertExitCode(0);
    }

    private function createPayment(
        string $status,
        ?string $trackingId
    ): Payment {
        $organization = Organization::query()->create([
            'name' =>
                'Reconciliation Test Organization '.uniqid(),
            'email' =>
                uniqid().'@reconciliation.example.com',
        ]);

        $event = Event::query()->create([
            'organization_id' =>
                $organization->id,

            'name' =>
                'Reconciliation Test Event '.uniqid(),

            'venue' =>
                'Test Venue',

            'starts_at' =>
                now()->addDay(),

            'ends_at' =>
                now()->addDays(2),

            'status' =>
                Event::STATUS_ACTIVE,

            'registration_is_open' =>
                true,
        ]);

        $attendee = Attendee::query()->create([
            'event_id' =>
                $event->id,

            'full_name' =>
                'Reconciliation Test Attendee',

            'email' =>
                uniqid().'@attendee.example.com',

            'status' =>
                'registered',

            'registration_source' =>
                'manual',
        ]);

        return Payment::query()->create([
            'organization_id' =>
                $organization->id,

            'event_id' =>
                $event->id,

            'attendee_id' =>
                $attendee->id,

            'reference' =>
                'RECON-'.strtoupper(uniqid()),

            'amount' =>
                1000,

            'currency' =>
                'TZS',

            'status' =>
                $status,

            'provider_tracking_id' =>
                $trackingId,

            'initiated_at' =>
                now()->subMinutes(10),
        ]);
    }
}