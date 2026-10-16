<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\MerchandiseOrder;
use App\Models\MerchandiseProduct;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MerchandiseCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function setupShop(int $stock): array
    {
        $organization = Organization::query()->create(['name' => 'Checkout Test Organization']);
        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Checkout Test Event',
            'slug' => 'checkout-' . Str::lower(Str::random(12)),
            'status' => Event::STATUS_ACTIVE,
            'merchandise_section_enabled' => true,
        ]);
        $product = MerchandiseProduct::query()->create([
            'event_id' => $event->id,
            'name' => 'Concert T-shirt',
            'slug' => 'shirt',
            'price_tzs' => 15000,
            'stock_quantity' => $stock,
            'is_active' => true,
        ]);

        return [$event, $product];
    }

    private function orderPayload(int $quantity = 1): array
    {
        return [
            'buyer_name' => 'Checkout Tester',
            'buyer_phone' => '255700000000',
            'quantity' => $quantity,
            'fulfillment_method' => 'event_pickup',
        ];
    }

    public function test_public_checkout_reserves_last_item_once(): void
    {
        [$event, $product] = $this->setupShop(1);
        $url = route('public.merchandise.store', [$event, $product]);

        $first = $this->post($url, $this->orderPayload());
        $first->assertRedirect();

        $this->assertSame(0, $product->fresh()->stock_quantity);
        $this->assertSame(1, MerchandiseOrder::query()->count());

        $this->post($url, $this->orderPayload())->assertStatus(422);

        $this->assertSame(0, $product->fresh()->stock_quantity);
        $this->assertSame(1, MerchandiseOrder::query()->count());
    }

    public function test_checkout_rejects_quantity_greater_than_stock(): void
    {
        [$event, $product] = $this->setupShop(1);

        $this->post(
            route('public.merchandise.store', [$event, $product]),
            $this->orderPayload(2)
        )->assertStatus(422);

        $this->assertSame(1, $product->fresh()->stock_quantity);
        $this->assertSame(0, MerchandiseOrder::query()->count());
    }

    public function test_checkout_is_not_available_when_event_shop_is_disabled(): void
    {
        [$event, $product] = $this->setupShop(1);
        $event->update(['merchandise_section_enabled' => false]);

        $this->post(
            route('public.merchandise.store', [$event, $product]),
            $this->orderPayload()
        )->assertNotFound();

        $this->assertSame(1, $product->fresh()->stock_quantity);
    }
}
