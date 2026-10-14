<?php

namespace Tests\Feature;

use App\Models\AttendeeMerchandise;
use App\Models\Event;
use App\Models\EventMerchandise;
use App\Models\MerchandiseOrder;
use App\Models\MerchandiseProduct;
use App\Models\MerchandiseVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MerchandiseVariantInventoryTest extends TestCase
{
    use RefreshDatabase;

    private function variantWithPublicOrder(int $quantity, string $status, ?\DateTimeInterface $expiresAt): MerchandiseVariant
    {
        $event = Event::query()->create([
            'name' => 'Inventory Test Event',
            'slug' => 'inventory-test-' . Str::lower(Str::random(8)),
            'status' => 'active',
        ]);
        $item = EventMerchandise::query()->create([
            'event_id' => $event->id,
            'name' => 'Book',
            'selection_type' => 'optional',
            'maximum_per_attendee' => 20,
            'is_active' => true,
        ]);
        $variant = MerchandiseVariant::query()->create([
            'event_merchandise_id' => $item->id,
            'name' => 'Standard',
            'stock_quantity' => 10,
            'price' => 12000,
            'currency' => 'TZS',
            'is_active' => true,
        ]);
        $product = MerchandiseProduct::query()->create([
            'event_id' => $event->id,
            'event_merchandise_id' => $item->id,
            'name' => 'Book',
            'slug' => 'book',
            'category' => 'books',
            'is_active' => true,
            'price_tzs' => 12000,
        ]);
        MerchandiseOrder::query()->create([
            'event_id' => $event->id,
            'merchandise_product_id' => $product->id,
            'merchandise_variant_id' => $variant->id,
            'public_token' => Str::random(48),
            'order_number' => 'TEST-' . Str::upper(Str::random(14)),
            'buyer_name' => 'Test Buyer',
            'buyer_phone' => '255700000000',
            'quantity' => $quantity,
            'unit_price_tzs' => 12000,
            'total_tzs' => 12000 * $quantity,
            'status' => $status,
            'expires_at' => $expiresAt,
        ]);
        return $variant->refresh();
    }

    public function test_active_pending_public_reservations_reduce_available_stock(): void
    {
        $variant = $this->variantWithPublicOrder(3, 'pending_payment', now()->addMinutes(10));
        $this->assertSame(3, $variant->publicCommittedQuantity());
        $this->assertSame(7, $variant->remainingQuantity());
    }

    public function test_expired_pending_reservations_do_not_reduce_available_stock(): void
    {
        $variant = $this->variantWithPublicOrder(3, 'pending_payment', now()->subMinute());
        $this->assertSame(0, $variant->publicCommittedQuantity());
        $this->assertSame(10, $variant->remainingQuantity());
    }

    public function test_paid_orders_remain_committed_after_reservation_deadline(): void
    {
        $variant = $this->variantWithPublicOrder(4, 'paid', now()->subDay());
        $this->assertSame(4, $variant->publicCommittedQuantity());
        $this->assertSame(6, $variant->remainingQuantity());
    }

    public function test_expiry_command_does_not_restore_physical_stock_for_linked_variants(): void
    {
        $variant = $this->variantWithPublicOrder(2, 'pending_payment', now()->subMinute());
        $this->artisan('merchandise:expire-orders')->assertSuccessful();
        $this->assertSame(10, $variant->fresh()->stock_quantity);
        $this->assertSame('expired', MerchandiseOrder::query()->firstOrFail()->status);
    }
}
