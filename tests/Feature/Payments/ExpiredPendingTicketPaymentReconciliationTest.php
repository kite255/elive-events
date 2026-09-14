<?php

namespace Tests\Feature\Payments;

use App\Models\Event;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\TicketOrder;
use App\Services\Payments\PesapalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class ExpiredPendingTicketPaymentReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_pesapal_payment_becomes_expired_when_ticket_order_has_expired(): void
    {
        $organization =
            Organization::query()->create([
                'name' =>
                    'Expired Payment Test Organization',
            ]);

        $event =
            Event::query()->create([
                'organization_id' =>
                    $organization->id,

                'name' =>
                    'Expired Payment Test Event',

                'status' =>
                    Event::STATUS_ACTIVE,

                'starts_at' =>
                    now()->addDays(7),
            ]);

        $gateway =
            PaymentGateway::query()->create([
                'organization_id' =>
                    $organization->id,

                'name' =>
                    'Pesapal',

                'code' =>
                    'pesapal',

                'is_enabled' =>
                    true,

                'is_default' =>
                    true,

                'environment' =>
                    'sandbox',
            ]);

        $order =
            TicketOrder::query()->create([
                'event_id' =>
                    $event->id,

                'order_number' =>
                    'ELV-ORDER-EXPIRED-001',

                'buyer_name' =>
                    'Expired Buyer',

                'buyer_phone' =>
                    '255700000001',

                'buyer_email' =>
                    'expired@example.com',

                'quantity' =>
                    1,

                'subtotal' =>
                    10000,

                'discount_amount' =>
                    0,

                'total' =>
                    10000,

                'currency' =>
                    'TZS',

                'status' =>
                    TicketOrder::STATUS_EXPIRED,

                'expires_at' =>
                    now()->subMinutes(10),
            ]);

        $payment =
            Payment::query()->create([
                'organization_id' =>
                    $organization->id,

                'event_id' =>
                    $event->id,

                'ticket_order_id' =>
                    $order->id,

                'payment_gateway_id' =>
                    $gateway->id,

                'reference' =>
                    'ELV-PAY-EXPIRED-001',

                'provider_reference' =>
                    'ELV-PAY-EXPIRED-001',

                'provider_tracking_id' =>
                    'tracking-expired-001',

                'amount' =>
                    10000,

                'currency' =>
                    'TZS',

                'status' =>
                    Payment::STATUS_PROCESSING,

                'initiated_at' =>
                    now()->subMinutes(20),
            ]);

        $this->mock(
            PesapalService::class,
            function (MockInterface $mock) use ($payment): void {
                $mock
                    ->shouldReceive('getPaymentStatus')
                    ->once()
                    ->with(
                        'tracking-expired-001'
                    )
                    ->andReturn([
                        'merchant_reference' =>
                            $payment->reference,

                        'order_tracking_id' =>
                            'tracking-expired-001',

                        'currency' =>
                            'TZS',

                        'amount' =>
                            10000,

                        'payment_status_description' =>
                            'PENDING',

                        'status_code' =>
                            0,

                        'payment_method' =>
                            '',

                        'confirmation_code' =>
                            '',
                    ]);
            }
        );

        $this->artisan(
            'payments:reconcile'
        )->assertExitCode(0);

        $payment->refresh();
        $order->refresh();

        $this->assertSame(
            Payment::STATUS_EXPIRED,
            $payment->status
        );

        $this->assertSame(
            TicketOrder::STATUS_EXPIRED,
            $order->status
        );

        $this->assertDatabaseHas(
            'payment_transactions',
            [
                'payment_id' =>
                    $payment->id,

                'provider_reference' =>
                    'tracking-expired-001',
            ]
        );
    }

    public function test_pending_pesapal_payment_remains_processing_when_ticket_order_is_still_valid(): void
    {
        $organization =
            Organization::query()->create([
                'name' =>
                    'Active Payment Test Organization',
            ]);

        $event =
            Event::query()->create([
                'organization_id' =>
                    $organization->id,

                'name' =>
                    'Active Payment Test Event',

                'status' =>
                    Event::STATUS_ACTIVE,

                'starts_at' =>
                    now()->addDays(7),
            ]);

        $gateway =
            PaymentGateway::query()->create([
                'organization_id' =>
                    $organization->id,

                'name' =>
                    'Pesapal',

                'code' =>
                    'pesapal',

                'is_enabled' =>
                    true,

                'is_default' =>
                    true,

                'environment' =>
                    'sandbox',
            ]);

        $order =
            TicketOrder::query()->create([
                'event_id' =>
                    $event->id,

                'order_number' =>
                    'ELV-ORDER-ACTIVE-001',

                'buyer_name' =>
                    'Active Buyer',

                'buyer_phone' =>
                    '255700000002',

                'buyer_email' =>
                    'active@example.com',

                'quantity' =>
                    1,

                'subtotal' =>
                    10000,

                'discount_amount' =>
                    0,

                'total' =>
                    10000,

                'currency' =>
                    'TZS',

                'status' =>
                    TicketOrder::STATUS_PROCESSING,

                'expires_at' =>
                    now()->addMinutes(10),
            ]);

        $payment =
            Payment::query()->create([
                'organization_id' =>
                    $organization->id,

                'event_id' =>
                    $event->id,

                'ticket_order_id' =>
                    $order->id,

                'payment_gateway_id' =>
                    $gateway->id,

                'reference' =>
                    'ELV-PAY-ACTIVE-001',

                'provider_reference' =>
                    'ELV-PAY-ACTIVE-001',

                'provider_tracking_id' =>
                    'tracking-active-001',

                'amount' =>
                    10000,

                'currency' =>
                    'TZS',

                'status' =>
                    Payment::STATUS_PROCESSING,

                'initiated_at' =>
                    now()->subMinutes(5),
            ]);

        $this->mock(
            PesapalService::class,
            function (MockInterface $mock) use ($payment): void {
                $mock
                    ->shouldReceive('getPaymentStatus')
                    ->once()
                    ->with(
                        'tracking-active-001'
                    )
                    ->andReturn([
                        'merchant_reference' =>
                            $payment->reference,

                        'order_tracking_id' =>
                            'tracking-active-001',

                        'currency' =>
                            'TZS',

                        'amount' =>
                            10000,

                        'payment_status_description' =>
                            'PENDING',

                        'status_code' =>
                            0,

                        'payment_method' =>
                            '',

                        'confirmation_code' =>
                            '',
                    ]);
            }
        );

        $this->artisan(
            'payments:reconcile'
        )->assertExitCode(0);

        $payment->refresh();
        $order->refresh();

        $this->assertSame(
            Payment::STATUS_PROCESSING,
            $payment->status
        );

        $this->assertSame(
            TicketOrder::STATUS_PROCESSING,
            $order->status
        );

        $this->assertDatabaseHas(
            'payment_transactions',
            [
                'payment_id' =>
                    $payment->id,

                'provider_reference' =>
                    'tracking-active-001',

                'status' =>
                    Payment::STATUS_PROCESSING,
            ]
        );
    }

}
