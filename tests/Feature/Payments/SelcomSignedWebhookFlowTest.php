<?php

namespace Tests\Feature\Payments;

use App\Jobs\FulfillCompletedPayment;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\TicketOrder;
use App\Services\Payments\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SelcomSignedWebhookFlowTest extends TestCase
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
        Http::preventStrayRequests();
        Queue::fake();
    }

    private function trackedPayment(): Payment
    {
        $organization = Organization::query()->create([
            'name' => 'Webhook Verification Organization',
            'slug' => 'webhook-verified-' . uniqid(),
        ]);
        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Webhook Verification Event',
            'slug' => 'webhook-verification-' . uniqid(),
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
            'order_number' => 'WEBHOOK-' . uniqid(),
            'buyer_name' => 'Test Customer',
            'buyer_email' => 'customer@example.test',
            'buyer_phone' => '0712345678',
            'quantity' => 1,
            'subtotal' => 500,
            'discount_amount' => 0,
            'total' => 500,
            'currency' => 'TZS',
            'status' => TicketOrder::STATUS_PENDING,
            'expires_at' => now()->addMinutes(30),
        ]);

        $payment = app(PaymentService::class)->createForTicketOrder($order);
        $payment->update([
            'status' => Payment::STATUS_PROCESSING,
            'provider_tracking_id' => $payment->reference,
        ]);

        return $payment->fresh();
    }

    private function webhook(Payment $payment, string $claimedStatus = 'COMPLETED'): \Illuminate\Testing\TestResponse
    {
        $timestamp = now()->toIso8601String();
        $message = 'timestamp=' . $timestamp . '&order_id=' . $payment->reference;
        $signature = base64_encode(hash_hmac('sha256', $message, 'test-secret', true));

        return $this->withHeaders([
            'Authorization' => 'SELCOM ' . base64_encode('test-key'),
            'Timestamp' => $timestamp,
            'Digest-Method' => 'HS256',
            'Signed-Fields' => 'order_id',
            'Digest' => $signature,
        ])->postJson(route('payments.selcom.webhook'), [
            'order_id' => $payment->reference,
            'payment_status' => $claimedStatus,
        ]);
    }

    private function fakeProvider(Payment $payment, string $status, string $amount = '500.00'): void
    {
        Http::fake(['gateway.example.test/*' => Http::response([
            'result' => 'SUCCESS',
            'resultcode' => '000',
            'data' => [[
                'order_id' => $payment->reference,
                'amount' => $amount,
                'payment_status' => $status,
                'transid' => $status === 'COMPLETED' ? 'CONFIRMED-TRANSACTION-ID' : '',
            ]],
        ])]);
    }

    public function test_signed_webhook_only_completes_after_authenticated_provider_confirmation(): void
    {
        $payment = $this->trackedPayment();
        $this->fakeProvider($payment, 'COMPLETED');

        $this->webhook($payment)->assertOk();
        $this->assertSame(Payment::STATUS_COMPLETED, $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->paid_at);
        $this->assertNull($payment->fresh()->fulfilled_at);
        $this->assertFalse($payment->ticketOrder->fresh()->isPaid());
        Queue::assertPushed(FulfillCompletedPayment::class, 1);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && str_contains($request->url(), '/v1/checkout/order-status'));

        // Provider re-verification of a replay must not schedule fulfillment twice.
        $this->webhook($payment)->assertOk();
        Queue::assertPushed(FulfillCompletedPayment::class, 1);
        Http::assertSentCount(2);
    }

    public function test_forged_completed_status_cannot_override_provider_pending(): void
    {
        $payment = $this->trackedPayment();
        $this->fakeProvider($payment, 'PENDING');

        $this->webhook($payment, 'COMPLETED')->assertOk();
        $this->assertSame(Payment::STATUS_PROCESSING, $payment->fresh()->status);
        $this->assertNull($payment->fresh()->paid_at);
        $this->assertNull($payment->fresh()->fulfilled_at);
        Queue::assertNothingPushed();
    }

    public function test_provider_amount_mismatch_prevents_payment_completion(): void
    {
        $payment = $this->trackedPayment();
        $this->fakeProvider($payment, 'COMPLETED', '999.00');

        $this->webhook($payment)->assertStatus(503);
        $this->assertSame(Payment::STATUS_PROCESSING, $payment->fresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_invalid_signature_prevents_provider_status_lookup(): void
    {
        $payment = $this->trackedPayment();
        Http::fake();

        $this->withHeaders([
            'Authorization' => 'SELCOM ' . base64_encode('test-key'),
            'Timestamp' => now()->toIso8601String(),
            'Digest-Method' => 'HS256',
            'Signed-Fields' => 'order_id',
            'Digest' => 'incorrect-signature',
        ])->postJson(route('payments.selcom.webhook'), [
            'order_id' => $payment->reference,
            'payment_status' => 'COMPLETED',
        ])->assertUnauthorized();

        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }
}
