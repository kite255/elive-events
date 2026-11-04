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
                        'qr' => '000201' . str_repeat('1234567890', 23) . '6304ABCD',
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

        $response = $this->get(route('payments.pay', $payment))
            ->assertOk()
            ->assertSee('Mobile Money')
            ->assertSee('Pay with QR Code')
            ->assertSee('CRDB SimBanking, NBC Kiganjani or NMB Mkononi.')
            ->assertSee('App support may vary.')
            ->assertDontSee('QR Code / Payment Token')
            ->assertSee('e.g. 255712345678')
            ->assertSee('Never enter your mobile money PIN on this website.')
            ->assertSee('Enter your mobile money number to receive a payment request.')
            ->assertSee('Secure payments powered by Selcom')
            ->assertDontSee('Debit / Credit Card');

        $html = $response->getContent();
        $this->assertTrue(
            strpos($html, 'Vodacom M-Pesa') < strpos($html, 'Mixx by Yas')
            && strpos($html, 'Mixx by Yas') < strpos($html, 'Airtel Money')
            && strpos($html, 'Airtel Money') < strpos($html, 'HaloPesa')
            && strpos($html, 'HaloPesa') < strpos($html, 'T-Pesa')
        );

        foreach (['m-pesa.png', 'mixx-by-yas.png', 'airtel-money.png', 'halopesa.png', 't-pesa.png', 'tips.png'] as $logo) {
            $this->assertFileExists(public_path('images/payment-networks/' . $logo));
        }

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
            ->assertSee('Alternatively, use this payment token:')
            ->assertSee('Pay with QR Code')
            ->assertSee('CRDB SimBanking')
            ->assertSee('NBC Kiganjani')
            ->assertSee('NMB Mkononi')
            ->assertSee('Selcom Pesa')
            ->assertSee('M-Pesa')
            ->assertSee('Mixx by Yas')
            ->assertSee('Airtel Money')
            ->assertSee('Compatibility with this Selcom QR may vary.')
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

    public function test_emv_qr_payload_is_rendered_as_a_scannable_svg_without_changing_provider_data(): void
    {
        $payment = $this->payment();
        $emv = '000201' . str_repeat('1234567890', 23) . '6304ABCD';

        $payment->transactions()->create([
            'type' => 'order_created',
            'status' => 'success',
            'response_payload' => [
                'selcom_response' => ['data' => [[
                    'qr' => $emv,
                    'payment_token' => 'TOKEN-1234',
                ]]],
            ],
        ]);

        $details = app(\App\Services\Payments\SelcomBrandedCheckoutService::class)->qrDetails($payment);
        $this->assertSame('TOKEN-1234', $details['payment_token']);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $details['qr_image']);

        $svg = base64_decode(substr($details['qr_image'], strlen('data:image/svg+xml;base64,')), true);
        $this->assertIsString($svg);
        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringContainsString('</svg>', $svg);
    }

    public function test_arbitrary_qr_strings_are_not_rendered_as_payment_qr(): void
    {
        $payment = $this->payment();
        $payment->transactions()->create([
            'type' => 'order_created',
            'status' => 'success',
            'response_payload' => [
                'selcom_response' => ['data' => [[
                    'qr' => 'INVALID-QR-DATA',
                    'payment_token' => 'TOKEN-1234',
                ]]],
            ],
        ]);

        $details = app(\App\Services\Payments\SelcomBrandedCheckoutService::class)->qrDetails($payment);
        $this->assertNull($details['qr_image']);
        $this->assertSame('TOKEN-1234', $details['payment_token']);
    }

    public function test_wallet_checkout_requests_phone_push_once_and_never_marks_paid(): void
    {
        $payment = $this->payment();
        $this->fakeOrdersAndWallet();

        $this->post(route('payments.submit', $payment), [
            'method' => 'mobile',
            'phone' => '0712345678',
        ])->assertRedirect(route('payments.pay', $payment));

        $this->get(route('payments.pay', $payment))
            ->assertOk()
            ->assertSee('Waiting for Payment Confirmation')
            ->assertSee('Your tickets will be issued only after Selcom confirms payment.')
            ->assertSee('Check Payment Status');

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

    public function test_selcom_processing_status_view_auto_refreshes_without_initiating_payment(): void
    {
        $payment = $this->payment();
        $payment->update([
            'status' => Payment::STATUS_PROCESSING,
            'provider_tracking_id' => $payment->reference,
        ]);
        $payment->load(['event', 'gateway', 'ticketOrder']);

        $html = view('public.payments.status', [
            'payment' => $payment,
            'checkoutUnavailable' => false,
        ])->render();

        $this->assertStringContainsString('window.location.reload()', $html);
        $this->assertStringContainsString('25000', $html);
        $this->assertStringContainsString('This page also checks automatically', $html);

        Http::assertNothingSent();
        $this->assertNull($payment->fresh()->paid_at);
    }

    public function test_invalid_phone_is_rejected_without_provider_request(): void
    {
        $payment = $this->payment();
        Http::fake();

        $this->from(route('payments.pay', $payment))->post(route('payments.submit', $payment), [
            'method' => 'mobile',
            'phone' => '123',
        ])->assertRedirect(route('payments.pay', $payment));

        // Phone format must be validated before initiating provider checkout,
        // not merely rejected after a Selcom order has been created.
        Http::assertNothingSent();
    }
}
