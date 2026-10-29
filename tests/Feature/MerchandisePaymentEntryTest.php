<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\MerchandiseOrder;
use App\Models\MerchandiseProduct;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MerchandisePaymentEntryTest extends TestCase
{
    use RefreshDatabase;

    private function order(bool $expired = false): MerchandiseOrder
    {
        $org = Organization::create(['name' => 'Payment Entry ' . Str::random(12)]);
        $event = Event::create([
            'organization_id' => $org->id,
            'name' => 'Payment Entry Event',
            'slug' => 'payment-entry-' . Str::lower(Str::random(12)),
            'status' => 'active',
        ]);
        PaymentGateway::create([
            'name' => 'Pesapal Test',
            'code' => 'pesapal',
            'is_enabled' => true,
            'is_default' => true,
            'environment' => 'sandbox',
        ]);
        $product = MerchandiseProduct::create([
            'event_id' => $event->id,
            'name' => 'Mug',
            'slug' => 'mug',
            'price_tzs' => 10000,
            'stock_quantity' => 0,
            'is_active' => true,
        ]);
        return MerchandiseOrder::create([
            'event_id' => $event->id,
            'merchandise_product_id' => $product->id,
            'public_token' => Str::random(48),
            'order_number' => 'M-' . Str::upper(Str::random(12)),
            'buyer_name' => 'Buyer',
            'buyer_phone' => '255700000000',
            'quantity' => 1,
            'unit_price_tzs' => 10000,
            'total_tzs' => 10000,
            'status' => 'pending_payment',
            'expires_at' => $expired ? now()->subMinute() : now()->addMinutes(10),
        ]);
    }

    public function test_order_page_offers_payment_for_active_reservation(): void
    {
        $order = $this->order();
        $this->get(route('public.merchandise.orders.show', $order->public_token))
            ->assertOk()
            ->assertSee('Pay securely with Pesapal');
    }

    public function test_payment_action_creates_once_and_redirects_to_existing_payment_route(): void
    {
        $order = $this->order();
        $url = route('public.merchandise.orders.pay', $order->public_token);
        $this->post($url)->assertRedirect();
        $payment = Payment::where('merchandise_order_id', $order->id)->firstOrFail();
        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
        $this->assertSame('10000.00', $payment->amount);
        $this->assertSame('TZS', $payment->currency);

        $this->post($url)->assertRedirect(route('payments.pay', $payment->reference));
        $this->assertSame(1, Payment::where('merchandise_order_id', $order->id)->count());
    }

    public function test_expired_reservation_cannot_initiate_gateway_payment(): void
    {
        $order = $this->order(true);
        $this->get(route('public.merchandise.orders.show', $order->public_token))
            ->assertOk()
            ->assertDontSee('Pay securely with Pesapal');
        $this->post(route('public.merchandise.orders.pay', $order->public_token))
            ->assertRedirect(route('public.merchandise.orders.show', $order->public_token));
        $this->assertSame(0, Payment::where('merchandise_order_id', $order->id)->count());
    }
}
