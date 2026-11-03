<?php

namespace Tests\Feature\Payments;

use App\Models\Event;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\TicketOrder;
use App\Services\Payments\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Tests\TestCase;

class SelcomCheckoutFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.selcom', [
            'enabled' => true,
            'vendor_id' => 'TESTVENDOR',
            'api_key' => 'test-key',
            'api_secret' => 'test-secret',
            'base_url' => 'https://gateway.example.test',
            'timeout' => 5,
        ]);
    }

    public function test_selcom_checkout_uses_buyer_details_and_keeps_ticket_unpaid(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Selcom Checkout Organization',
            'slug' => 'selcom-checkout-' . uniqid(),
        ]);
        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Selcom Test Concert',
            'slug' => 'selcom-concert-' . uniqid(),
            'status' => Event::STATUS_ACTIVE,
        ]);
        $gateway = PaymentGateway::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Selcom',
            'code' => 'selcom',
            'environment' => 'sandbox',
            'is_default' => true,
            'is_enabled' => true,
        ]);
        $order = TicketOrder::query()->create([
            'event_id' => $event->id,
            'order_number' => 'SC-' . uniqid(),
            'buyer_name' => 'Jane Mushi',
            'buyer_email' => 'jane@example.com',
            'buyer_phone' => '0712 345 678',
            'quantity' => 1,
            'subtotal' => 25000,
            'discount_amount' => 0,
            'total' => 25000,
            'currency' => 'TZS',
            'status' => TicketOrder::STATUS_PENDING,
            'expires_at' => now()->addMinutes(30),
        ]);
        $payment = app(PaymentService::class)->createForTicketOrder($order);

        Http::fake(['gateway.example.test/*' => Http::response([
            'result' => 'SUCCESS',
            'resultcode' => '000',
            'data' => [[
                'payment_gateway_url' => base64_encode('https://checkout.example.test/order/123'),
            ]],
        ])]);

        $checkout = app(PaymentService::class)->start($payment);

        $this->assertSame('https://checkout.example.test/order/123', $checkout['redirect_url']);
        $this->assertSame($payment->reference, $checkout['order_tracking_id']);

        $payment->refresh();
        $order->refresh();
        $this->assertSame(Payment::STATUS_PROCESSING, $payment->status);
        $this->assertSame($payment->reference, $payment->provider_tracking_id);
        $this->assertNull($payment->paid_at);
        $this->assertFalse($order->isPaid());
        $this->assertSame(TicketOrder::STATUS_PROCESSING, $order->status);

        Http::assertSent(function ($request) use ($payment): bool {
            $payload = $request->data();

            return $request->method() === 'POST'
                && $request->url() === 'https://gateway.example.test/v1/checkout/create-order-minimal'
                && ($payload['vendor'] ?? '') === 'TESTVENDOR'
                && ($payload['order_id'] ?? '') === $payment->reference
                && ($payload['buyer_name'] ?? '') === 'Jane Mushi'
                && ($payload['buyer_email'] ?? '') === 'jane@example.com'
                && ($payload['buyer_phone'] ?? '') === '255712345678'
                && (string) ($payload['amount'] ?? '') === '25000.00'
                && ($payload['currency'] ?? '') === 'TZS'
                && ! array_key_exists('payment_methods', $payload)
                && ! array_key_exists('billing.firstname', $payload)
                && ! array_key_exists('billing.phone', $payload)
                && ($payload['no_of_items'] ?? '') === '1'
                && base64_decode((string) ($payload['redirect_url'] ?? ''), true)
                    === route('payments.status', ['payment' => $payment->reference])
                && base64_decode((string) ($payload['cancel_url'] ?? ''), true)
                    === route('payments.status', ['payment' => $payment->reference])
                && base64_decode((string) ($payload['webhook'] ?? ''), true)
                    === route('payments.selcom.webhook');
        });
    }

    public function test_selcom_checkout_refuses_to_start_when_disabled(): void
    {
        config()->set('services.selcom.enabled', false);

        $this->expectException(\RuntimeException::class);
        app(\App\Services\Payments\SelcomService::class)->createPayment(new Payment([
            'reference' => 'TEST-NO-CHECKOUT',
            'currency' => 'TZS',
            'amount' => 1000,
        ]));
    }
    /**
     * These tests must run against the test cache, never the live gateway.
     */
    private function pendingCheckoutPayment(): Payment
    {
        $organization = Organization::query()->create([
            'name' => 'Payment Replay Test',
            'slug' => 'payment-replay-' . uniqid(),
        ]);
        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Replay Test Event',
            'slug' => 'replay-event-' . uniqid(),
            'status' => Event::STATUS_ACTIVE,
        ]);
        PaymentGateway::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Selcom',
            'code' => 'selcom',
            'environment' => 'sandbox',
            'is_default' => true,
            'is_enabled' => true,
        ]);
        $order = TicketOrder::query()->create([
            'event_id' => $event->id,
            'order_number' => 'REPLAY-' . uniqid(),
            'buyer_name' => 'Jane Mushi',
            'buyer_email' => 'jane@example.com',
            'buyer_phone' => '0712345678',
            'quantity' => 1,
            'subtotal' => 25000,
            'discount_amount' => 0,
            'total' => 25000,
            'currency' => 'TZS',
            'status' => TicketOrder::STATUS_PENDING,
            'expires_at' => now()->addMinutes(30),
        ]);

        return app(PaymentService::class)->createForTicketOrder($order);
    }

    public function test_terminal_and_active_payments_never_initialize_gateway_checkout(): void
    {
        config()->set('cache.default', 'array');
        Http::preventStrayRequests();
        Http::fake();

        foreach ([
            Payment::STATUS_COMPLETED,
            Payment::STATUS_FAILED,
            Payment::STATUS_CANCELLED,
            Payment::STATUS_EXPIRED,
            Payment::STATUS_REFUNDED,
            Payment::STATUS_PARTIALLY_REFUNDED,
            Payment::STATUS_PROCESSING,
        ] as $status) {
            $payment = $this->pendingCheckoutPayment();
            $payment->update(['status' => $status]);

            try {
                app(PaymentService::class)->start($payment);
                $this->fail('Checkout unexpectedly accepted status: ' . $status);
            } catch (RuntimeException $exception) {
                $this->assertNotSame('', $exception->getMessage());
            }
        }

        $tracked = $this->pendingCheckoutPayment();
        $tracked->update(['provider_tracking_id' => 'TRACKED-123']);
        $this->expectException(RuntimeException::class);
        try {
            app(PaymentService::class)->start($tracked);
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_held_atomic_checkout_lock_rejects_second_attempt_without_gateway_call(): void
    {
        config()->set('cache.default', 'array');
        Http::preventStrayRequests();
        Http::fake();
        $payment = $this->pendingCheckoutPayment();
        $lock = Cache::lock('payment:checkout:' . $payment->getKey(), 30);
        $this->assertTrue($lock->get());

        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('already being initialized');
            app(PaymentService::class)->start($payment);
        } finally {
            $lock->release();
            Http::assertNothingSent();
        }
    }

    public function test_failed_gateway_response_cannot_trigger_second_checkout_order(): void
    {
        config()->set('cache.default', 'array');
        $payment = $this->pendingCheckoutPayment();

        Http::preventStrayRequests();
        Http::fake(['gateway.example.test/*' => Http::response([
            'result' => 'FAIL',
            'resultcode' => '500',
        ], 500)]);

        try {
            app(PaymentService::class)->start($payment);
            $this->fail('First checkout should fail.');
        } catch (RuntimeException $exception) {
            $this->assertNotSame('', $exception->getMessage());
        }

        $this->assertNotNull(data_get($payment->fresh()->metadata, 'checkout_attempted_at'));
        Http::assertSentCount(1);

        try {
            app(PaymentService::class)->start($payment);
            $this->fail('Second checkout must be blocked pending reconciliation.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('reconciliation', $exception->getMessage());
        }

        Http::assertSentCount(1);
        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
    }

    public function test_uncertain_selcom_checkout_can_query_status_without_creating_another_order(): void
    {
        config()->set('cache.default', 'array');
        $payment = $this->pendingCheckoutPayment();
        $payment->update(['metadata' => [
            'checkout_attempted_at' => now()->toIso8601String(),
        ]]);

        Http::preventStrayRequests();
        Http::fake(['gateway.example.test/*' => Http::response([
            'result' => 'SUCCESS',
            'resultcode' => '000',
            'data' => [[
                'order_id' => $payment->reference,
                'amount' => '25000.00',
                'payment_status' => 'PENDING',
            ]],
        ])]);

        $updated = app(PaymentService::class)->reconcileUncertainSelcomCheckout($payment);

        $this->assertSame(Payment::STATUS_PROCESSING, $updated->status);
        $this->assertSame($payment->reference, $updated->provider_tracking_id);
        $this->assertNotNull(data_get($updated->metadata, 'checkout_attempted_at'));
        $this->assertNull($updated->paid_at);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && str_contains($request->url(), '/v1/checkout/order-status'));
    }

    public function test_uncertain_selcom_status_failure_keeps_payment_unconfirmed(): void
    {
        config()->set('cache.default', 'array');
        $payment = $this->pendingCheckoutPayment();
        $payment->update(['metadata' => [
            'checkout_attempted_at' => now()->toIso8601String(),
        ]]);

        Http::preventStrayRequests();
        Http::fake(['gateway.example.test/*' => Http::response([
            'result' => 'FAIL',
            'resultcode' => '404',
        ], 404)]);

        try {
            app(PaymentService::class)->reconcileUncertainSelcomCheckout($payment);
            $this->fail('Failed status lookup must not confirm payment.');
        } catch (RuntimeException $exception) {
            $this->assertNotSame('', $exception->getMessage());
        }

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertNotNull(data_get($payment->fresh()->metadata, 'checkout_attempted_at'));
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->method() === 'GET');
    }

}
