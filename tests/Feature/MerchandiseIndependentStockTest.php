<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\MerchandiseOrder;
use App\Models\MerchandiseProduct;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MerchandiseIndependentStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_expiring_unlinked_order_restores_stock_once(): void
    {
        $event = Event::query()->create([
            'name' => 'Test Merchandise Event',
            'slug' => 'shop-' . Str::lower(Str::random(8)),
            'status' => 'active',
        ]);

        $product = MerchandiseProduct::query()->create([
            'event_id' => $event->id,
            'name' => 'Mug',
            'slug' => 'mug',
            'category' => 'drinkware',
            'price_tzs' => 10000,
            'stock_quantity' => 3,
            'is_active' => true,
        ]);

        $order = MerchandiseOrder::query()->create([
            'event_id' => $event->id,
            'merchandise_product_id' => $product->id,
            'public_token' => Str::random(48),
            'order_number' => 'TEST-' . Str::upper(Str::random(14)),
            'buyer_name' => 'Test Buyer',
            'buyer_phone' => '255700000000',
            'quantity' => 2,
            'unit_price_tzs' => 10000,
            'total_tzs' => 20000,
            'status' => 'pending_payment',
            'expires_at' => now()->subMinute(),
        ]);

        $this->artisan('merchandise:expire-orders')->assertSuccessful();
        $this->assertSame('expired', $order->fresh()->status);
        $this->assertSame(5, $product->fresh()->stock_quantity);

        $this->artisan('merchandise:expire-orders')->assertSuccessful();
        $this->assertSame(5, $product->fresh()->stock_quantity);
    }

    public function test_completed_gateway_payment_prevents_expiry_stock_release(): void
    {
        $event = Event::query()->create([
            'name' => 'Gateway Safety Event',
            'slug' => 'gateway-' . Str::lower(Str::random(8)),
            'status' => 'active',
        ]);
        $product = MerchandiseProduct::query()->create([
            'event_id' => $event->id,
            'name' => 'Book',
            'slug' => 'book',
            'price_tzs' => 10000,
            'stock_quantity' => 2,
            'is_active' => true,
        ]);
        $order = MerchandiseOrder::query()->create([
            'event_id' => $event->id,
            'merchandise_product_id' => $product->id,
            'public_token' => Str::random(48),
            'order_number' => 'TEST-' . Str::upper(Str::random(14)),
            'buyer_name' => 'Test Buyer',
            'buyer_phone' => '255700000000',
            'quantity' => 1,
            'unit_price_tzs' => 10000,
            'total_tzs' => 10000,
            'status' => 'pending_payment',
            'expires_at' => now()->subMinute(),
        ]);
        Payment::query()->create([
            'event_id' => $event->id,
            'merchandise_order_id' => $order->id,
            'reference' => 'PAY-' . Str::upper(Str::random(14)),
            'amount' => 10000,
            'currency' => 'TZS',
            'status' => Payment::STATUS_COMPLETED,
        ]);

        $this->artisan('merchandise:expire-orders')->assertSuccessful();

        $this->assertSame('pending_payment', $order->fresh()->status);
        $this->assertSame(2, $product->fresh()->stock_quantity);
    }

    public function test_paid_order_does_not_expire_or_restore_stock(): void
    {
        $event = Event::query()->create([
            'name' => 'Paid Test Event',
            'slug' => 'paid-' . Str::lower(Str::random(8)),
            'status' => 'active',
        ]);
        $product = MerchandiseProduct::query()->create([
            'event_id' => $event->id,
            'name' => 'Cap',
            'slug' => 'cap',
            'price_tzs' => 10000,
            'stock_quantity' => 3,
            'is_active' => true,
        ]);
        $order = MerchandiseOrder::query()->create([
            'event_id' => $event->id,
            'merchandise_product_id' => $product->id,
            'public_token' => Str::random(48),
            'order_number' => 'TEST-' . Str::upper(Str::random(14)),
            'buyer_name' => 'Test Buyer',
            'buyer_phone' => '255700000000',
            'quantity' => 1,
            'unit_price_tzs' => 10000,
            'total_tzs' => 10000,
            'status' => 'paid',
            'expires_at' => now()->subMinute(),
        ]);

        $this->artisan('merchandise:expire-orders')->assertSuccessful();
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(3, $product->fresh()->stock_quantity);
    }
}
