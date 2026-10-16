<?php

namespace Tests\Feature;

use App\Jobs\FulfillCompletedPayment;
use App\Models\Event;
use App\Models\MerchandiseOrder;
use App\Models\MerchandiseProduct;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Services\Payments\PaymentService;
use App\Services\Payments\PesapalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class MerchandisePesapalNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function makePayment(bool $expired = false): array
    {
        $org = Organization::create(['name' => 'Pesapal Test ' . Str::random(12)]);
        $event = Event::create([
            'organization_id' => $org->id,
            'name' => 'Pesapal Merchandise Test',
            'slug' => 'pesapal-merch-' . Str::lower(Str::random(12)),
            'status' => 'active',
        ]);
        $gateway = PaymentGateway::create([
            'name' => 'Pesapal Test',
            'code' => 'pesapal',
            'is_enabled' => true,
            'is_default' => true,
            'environment' => 'sandbox',
        ]);
        $product = MerchandiseProduct::create([
            'event_id' => $event->id,
            'name' => 'Merchandise',
            'slug' => 'merch',
            'price_tzs' => 10000,
            'stock_quantity' => 0,
            'is_active' => true,
        ]);
        $order = MerchandiseOrder::create([
            'event_id' => $event->id,
            'merchandise_product_id' => $product->id,
            'public_token' => Str::random(48),
            'order_number' => 'MER-' . Str::upper(Str::random(12)),
            'buyer_name' => 'Buyer',
            'buyer_phone' => '255700000000',
            'quantity' => 1,
            'unit_price_tzs' => 10000,
            'total_tzs' => 10000,
            'status' => 'pending_payment',
            'expires_at' => $expired ? now()->subMinute() : now()->addMinutes(10),
        ]);
        $payment = Payment::create([
            'organization_id' => $org->id,
            'event_id' => $event->id,
            'merchandise_order_id' => $order->id,
            'payment_gateway_id' => $gateway->id,
            'reference' => 'PAY-' . Str::upper(Str::random(12)),
            'provider_tracking_id' => 'TRACK-' . Str::upper(Str::random(12)),
            'amount' => 10000,
            'currency' => 'TZS',
            'status' => Payment::STATUS_PROCESSING,
        ]);

        return [$order, $payment];
    }

    private function response(Payment $payment, string $status = 'COMPLETED'): array
    {
        return [
            'merchant_reference' => $payment->reference,
            'order_tracking_id' => $payment->provider_tracking_id,
            'currency' => 'TZS',
            'amount' => 10000,
            'payment_status_description' => $status,
            'status_code' => $status === 'COMPLETED' ? 1 : 0,
        ];
    }

    private function fakeGateway(array $responses): void
    {
        $mock = $this->mock(PesapalService::class);
        foreach ($responses as $response) {
            $mock->shouldReceive('getPaymentStatus')->once()->andReturn($response);
        }
    }

    public function test_valid_completed_notification_dispatches_fulfillment_only_once(): void
    {
        [$order, $payment] = $this->makePayment();
        Queue::fake();
        $this->fakeGateway([$this->response($payment), $this->response($payment)]);

        $service = app(PaymentService::class);
        $service->syncFromGateway($payment, $payment->provider_tracking_id);
        $service->syncFromGateway($payment->fresh(), $payment->provider_tracking_id);

        $this->assertSame(Payment::STATUS_COMPLETED, $payment->fresh()->status);
        $this->assertSame('pending_payment', $order->fresh()->status);
        Queue::assertPushed(FulfillCompletedPayment::class, 1);
    }

    public function test_mismatched_merchant_reference_is_rejected_without_fulfillment(): void
    {
        [$order, $payment] = $this->makePayment();
        Queue::fake();
        $response = $this->response($payment);
        $response['merchant_reference'] = 'UNRELATED';
        $this->fakeGateway([$response]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('merchant reference mismatch');
        try {
            app(PaymentService::class)->syncFromGateway($payment, $payment->provider_tracking_id);
        } finally {
            $this->assertSame(Payment::STATUS_PROCESSING, $payment->fresh()->status);
            Queue::assertNotPushed(FulfillCompletedPayment::class);
        }
    }

    public function test_failed_provider_status_does_not_dispatch_fulfillment(): void
    {
        [$order, $payment] = $this->makePayment();
        Queue::fake();
        $this->fakeGateway([$this->response($payment, 'FAILED')]);

        app(PaymentService::class)->syncFromGateway($payment, $payment->provider_tracking_id);

        $this->assertSame(Payment::STATUS_FAILED, $payment->fresh()->status);
        $this->assertSame('pending_payment', $order->fresh()->status);
        Queue::assertNotPushed(FulfillCompletedPayment::class);
    }

    public function test_pending_provider_response_for_expired_reservation_marks_payment_expired(): void
    {
        [$order, $payment] = $this->makePayment(true);
        Queue::fake();
        $this->fakeGateway([$this->response($payment, 'PENDING')]);

        app(PaymentService::class)->syncFromGateway($payment, $payment->provider_tracking_id);

        $this->assertSame(Payment::STATUS_EXPIRED, $payment->fresh()->status);
        Queue::assertNotPushed(FulfillCompletedPayment::class);
    }

    public function test_ipn_verifies_with_gateway_and_acknowledges_valid_notification(): void
    {
        [$order, $payment] = $this->makePayment();
        Queue::fake();
        $this->fakeGateway([$this->response($payment)]);

        $this->postJson(route('payments.pesapal.ipn'), [
            'OrderTrackingId' => $payment->provider_tracking_id,
            'OrderMerchantReference' => $payment->reference,
            'OrderNotificationType' => 'IPNCHANGE',
        ])->assertOk()->assertJsonPath('status', 200);

        $this->assertSame(Payment::STATUS_COMPLETED, $payment->fresh()->status);
        Queue::assertPushed(FulfillCompletedPayment::class, 1);
    }
}
