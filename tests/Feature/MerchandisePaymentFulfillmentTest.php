<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\MerchandiseOrder;
use App\Models\MerchandiseProduct;
use App\Models\Organization;
use App\Models\Payment;
use App\Services\Payments\PaymentFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class MerchandisePaymentFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    private function records(array $paymentOverrides = [], array $orderOverrides = []): array
    {
        $org = Organization::create(['name' => 'Fulfillment ' . Str::random(10)]);
        $event = Event::create([
            'organization_id' => $org->id,
            'name' => 'Merchandise Fulfillment Test',
            'slug' => 'fulfill-' . Str::lower(Str::random(10)),
            'status' => 'active',
        ]);
        $product = MerchandiseProduct::create([
            'event_id' => $event->id,
            'name' => 'Mug',
            'slug' => 'mug',
            'price_tzs' => 10000,
            'stock_quantity' => 0,
            'is_active' => true,
        ]);
        $order = MerchandiseOrder::create(array_merge([
            'event_id' => $event->id,
            'merchandise_product_id' => $product->id,
            'public_token' => Str::random(48),
            'order_number' => 'TEST-' . Str::upper(Str::random(12)),
            'buyer_name' => 'Buyer',
            'buyer_phone' => '255700000000',
            'quantity' => 1,
            'unit_price_tzs' => 10000,
            'total_tzs' => 10000,
            'status' => 'pending_payment',
            'expires_at' => now()->addMinutes(10),
        ], $orderOverrides));
        $payment = Payment::create(array_merge([
            'organization_id' => $org->id,
            'event_id' => $event->id,
            'merchandise_order_id' => $order->id,
            'reference' => 'PAY-' . Str::upper(Str::random(12)),
            'amount' => 10000,
            'currency' => 'TZS',
            'status' => Payment::STATUS_COMPLETED,
        ], $paymentOverrides));
        return [$order, $payment, $product];
    }

    public function test_completed_payment_fulfills_order_only_once(): void
    {
        [$order, $payment, $product] = $this->records();
        $service = app(PaymentFulfillmentService::class);
        $service->fulfill($payment);
        $firstFulfilledAt = $payment->fresh()->fulfilled_at;
        $this->assertNotNull($firstFulfilledAt);
        $this->assertSame('paid', $order->fresh()->status);
        $service->fulfill($payment->fresh());
        $this->assertEquals($firstFulfilledAt, $payment->fresh()->fulfilled_at);
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(0, $product->fresh()->stock_quantity);
    }

    public function test_pending_payment_cannot_fulfill(): void
    {
        [$order, $payment] = $this->records(['status' => Payment::STATUS_PENDING]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Payment must be completed');
        app(PaymentFulfillmentService::class)->fulfill($payment);
    }

    public function test_expired_reservation_rejects_late_completed_payment(): void
    {
        [$order, $payment] = $this->records([], ['expires_at' => now()->subMinute()]);
        try {
            app(PaymentFulfillmentService::class)->fulfill($payment);
            $this->fail('Late payment must not fulfill the expired order.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('expired', $exception->getMessage());
        }
        $this->assertSame('pending_payment', $order->fresh()->status);
        $this->assertNull($payment->fresh()->fulfilled_at);
    }

    public function test_mismatched_amount_cannot_fulfill(): void
    {
        [$order, $payment] = $this->records(['amount' => 5000]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not match');
        app(PaymentFulfillmentService::class)->fulfill($payment);
    }

    public function test_mismatched_currency_cannot_fulfill(): void
    {
        [$order, $payment] = $this->records(['currency' => 'USD']);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not match');
        app(PaymentFulfillmentService::class)->fulfill($payment);
    }
}
