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
                && $request->url() === 'https://gateway.example.test/v1/checkout/create-order'
                && ($payload['vendor'] ?? '') === 'TESTVENDOR'
                && ($payload['order_id'] ?? '') === $payment->reference
                && ($payload['buyer_name'] ?? '') === 'Jane Mushi'
                && ($payload['buyer_email'] ?? '') === 'jane@example.com'
                && ($payload['buyer_phone'] ?? '') === '255712345678'
                && (string) ($payload['amount'] ?? '') === '25000.00'
                && ($payload['currency'] ?? '') === 'TZS'
                && ($payload['payment_methods'] ?? '') === 'ALL'
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
}
