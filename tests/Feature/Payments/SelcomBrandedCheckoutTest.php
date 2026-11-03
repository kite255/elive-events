<?php

namespace Tests\Feature\Payments;

use App\Models\Event;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\TicketOrder;
use App\Services\Payments\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SelcomBrandedCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('cache.default', 'array');
        config()->set('services.selcom', [
            'enabled' => true,
            'vendor_id' => 'TESTVENDOR',
            'api_key' => 'test-key',
            'api_secret' => 'test-secret',
            'base_url' => 'https://gateway.example.test',
            'timeout' => 5,
        ]);
        Http::preventStrayRequests();
    }

    private function payment(): Payment
    {
        $organization = Organization::query()->create([
            'name' => 'Checkout Test Organization',
            'slug' => 'checkout-org-' . uniqid(),
        ]);
        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Checkout Test Concert',
            'slug' => 'checkout-event-' . uniqid(),
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
            'order_number' => 'SC-' . uniqid(),
            'buyer_name' => 'Jane Mushi',
            'buyer_email' => 'jane@example.com',
            'buyer_phone' => '0712345678',
            'quantity' => 1,
            'subtotal' => 1500,
            'discount_amount' => 0,
            'total' => 1500,
            'currency' => 'TZS',
            'status' => TicketOrder::STATUS_PENDING,
            'expires_at' => now()->addMinutes(30),
        ]);

        return app(PaymentService::class)->createForTicketOrder($order);
    }

    private function fakeOrdersAndWallet(): void
    {
        Http::fake(function ($request) {
            if (str_ends_with($request->url(), '/v1/checkout/create-order-minimal')) {
                return Http::response([
                    'resultcode' => '000',
                    'result' => 'SUCCESS',
                    'data' => [[
                        'payment_gateway_url' => base64_encode('https://gateway.example.test/pay/abc'),
                        'payment_token' => 'TOKEN-1234',
                    ]],
                ]);
            }
            if (str_ends_with($request->url(), '/v1/checkout/wallet-payment')) {
                return Http::response([
                    'resultcode' => '111',
                    'result' => 'PENDING',
                    'data' => [],
                ]);
            }

            return Http::response(['result' => 'FAIL', 'resultcode' => '404'], 404);
        });
    }

    public function test_selcom_checkout_displays_branded_methods_without_contacting_provider(): void
    {
        $payment = $this->payment();
        Http::fake();

        $this->get(route('payments.pay', $payment))
            ->assertOk()
            ->assertSee('Mobile Money')
            ->assertSee('QR Code / Payment Token')
            ->assertSee('Debit / Credit Card');

        Http::assertNothingSent();
        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
    }

    public function test_qr_checkout_initializes_one_order_and_shows_provider_token(): void
    {
        $payment = $this->payment();
        $this->fakeOrdersAndWallet();

        $this->post(route('payments.submit', $payment), ['method' => 'qr'])
            ->assertRedirect(route('payments.pay', $payment));

        $this->get(route('payments.pay', $payment))
            ->assertOk()
            ->assertSee('TOKEN-1234')
            ->assertSee('Check Payment Status');

        $this->assertSame(Payment::STATUS_PROCESSING, $payment->fresh()->status);
        $this->assertNull($payment->fresh()->paid_at);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/v1/checkout/create-order-minimal'));

        // A second POST cannot create another Selcom order.
        $this->post(route('payments.submit', $payment), ['method' => 'qr'])
            ->assertRedirect(route('payments.pay', $payment));
        Http::assertSentCount(1);
    }

    public function test_wallet_checkout_requests_phone_push_once_and_never_marks_paid(): void
    {
        $payment = $this->payment();
        $this->fakeOrdersAndWallet();

        $this->post(route('payments.submit', $payment), [
            'method' => 'mobile',
            'phone' => '0712345678',
        ])->assertRedirect(route('payments.pay', $payment));

        $fresh = $payment->fresh();
        $this->assertSame(Payment::STATUS_PROCESSING, $fresh->status);
        $this->assertNull($fresh->paid_at);
        $this->assertNotNull(data_get($fresh->metadata, 'wallet_attempted_at'));
        $this->assertNotNull(data_get($fresh->metadata, 'wallet_transaction_id'));
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/v1/checkout/wallet-payment')
            && ($request->data()['order_id'] ?? '') === $payment->reference
            && ($request->data()['msisdn'] ?? '') === '255712345678'
            && $request->hasHeader('Signed-Fields', 'transid,order_id,msisdn'));

        $this->post(route('payments.submit', $payment), [
            'method' => 'mobile',
            'phone' => '0712345678',
        ])->assertRedirect(route('payments.pay', $payment));

        Http::assertSentCount(2);
    }

    public function test_invalid_phone_is_rejected_without_provider_request(): void
    {
        $payment = $this->payment();
        Http::fake();

        $this->post(route('payments.submit', $payment), [
            'method' => 'mobile',
            'phone' => '123',
        ])->assertRedirect(route('payments.pay', $payment));

        // Phone format must be validated before initiating provider checkout,
        // not merely rejected after a Selcom order has been created.
        Http::assertNothingSent();
    }
}
