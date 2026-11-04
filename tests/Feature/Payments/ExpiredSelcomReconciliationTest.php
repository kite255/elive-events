<?php

namespace Tests\Feature\Payments;

use App\Models\Event;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\TicketOrder;
use App\Services\Payments\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ExpiredSelcomReconciliationTest extends TestCase
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

    private function expiredPayment(): Payment
    {
        $organization = Organization::query()->create([
            'name' => 'Expired Selcom Organization',
            'slug' => 'expired-selcom-' . uniqid(),
        ]);
        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Expired Selcom Concert',
            'slug' => 'expired-selcom-concert-' . uniqid(),
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
            'order_number' => 'EXPIRED-' . uniqid(),
            'buyer_name' => 'Example Buyer',
            'buyer_email' => 'example@example.test',
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
        $order->update(['status' => TicketOrder::STATUS_EXPIRED, 'expires_at' => now()->subMinutes(5)]);
        $payment->update([
            'status' => Payment::STATUS_EXPIRED,
            'provider_tracking_id' => $payment->reference,
        ]);

        return $payment->fresh();
    }

    private function fakeSelcom(Payment $payment, string $status, string $amount = '500.00'): void
    {
        Http::fake(['gateway.example.test/*' => Http::response([
            'result' => 'SUCCESS',
            'resultcode' => '000',
            'data' => [[
                'order_id' => $payment->reference,
                'amount' => $amount,
                'payment_status' => $status,
                'transid' => $status === 'COMPLETED' ? 'SEL-TEST-TRANSACTION' : '',
            ]],
        ])]);
    }

    public function test_pending_expired_payment_is_not_mutated_or_fulfilled(): void
    {
        $payment = $this->expiredPayment();
        $this->fakeSelcom($payment, 'PENDING');

        $this->assertSame(0, Artisan::call('payments:reconcile-expired-selcom'));
        $this->assertSame(Payment::STATUS_EXPIRED, $payment->fresh()->status);
        $this->assertNull($payment->fresh()->paid_at);
        $this->assertNull($payment->fresh()->fulfilled_at);
        $this->assertSame(0, $payment->transactions()->count());
        Queue::assertNothingPushed();
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->method() === 'GET'
            && str_contains($request->url(), '/v1/checkout/order-status'));
    }

    public function test_completed_expired_payment_creates_one_review_only(): void
    {
        $payment = $this->expiredPayment();
        $this->fakeSelcom($payment, 'COMPLETED');

        $this->assertSame(0, Artisan::call('payments:reconcile-expired-selcom'));
        $this->assertSame(0, Artisan::call('payments:reconcile-expired-selcom'));

        $this->assertSame(Payment::STATUS_EXPIRED, $payment->fresh()->status);
        $this->assertNull($payment->fresh()->paid_at);
        $this->assertNull($payment->fresh()->fulfilled_at);
        $this->assertSame(1, $payment->transactions()->where('type', 'expired_selcom_review')->count());
        $audit = $payment->transactions()->where('type', 'expired_selcom_review')->firstOrFail();
        $this->assertSame('requires_review', $audit->status);
        $this->assertSame('COMPLETED', data_get($audit->response_payload, 'provider_status'));
        $this->assertArrayNotHasKey('selcom_response', $audit->response_payload);
        Queue::assertNothingPushed();
        Http::assertSentCount(2);
    }

    public function test_amount_mismatch_is_rejected_without_review_or_fulfillment(): void
    {
        $payment = $this->expiredPayment();
        $this->fakeSelcom($payment, 'COMPLETED', '900.00');

        $this->assertSame(1, Artisan::call('payments:reconcile-expired-selcom'));
        $this->assertSame(Payment::STATUS_EXPIRED, $payment->fresh()->status);
        $this->assertSame(0, $payment->transactions()->count());
        Queue::assertNothingPushed();
    }

    public function test_invalid_limits_fail_without_any_gateway_requests(): void
    {
        Http::fake();

        $this->assertSame(2, Artisan::call('payments:reconcile-expired-selcom', ['--limit' => '101']));
        $this->assertSame(2, Artisan::call('payments:reconcile-expired-selcom', ['--lookback-hours' => '0']));
        Http::assertNothingSent();
    }
}
