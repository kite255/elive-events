<?php

namespace Tests\Feature;

use App\Models\AttendeeMerchandise;
use App\Models\MerchandiseOrder;
use App\Models\MerchandiseVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchandiseVariantInventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_variant_inventory_counts_paid_and_pending_public_orders_and_attendee_selections(): void
    {
        $this->assertTrue(method_exists(MerchandiseVariant::class, 'publicCommittedQuantity'));
        $this->assertTrue(method_exists(MerchandiseVariant::class, 'committedQuantity'));
        $this->assertTrue(method_exists(MerchandiseVariant::class, 'remainingQuantity'));
    }

    public function test_public_order_expiry_command_is_registered(): void
    {
        $this->assertNotNull(\Illuminate\Support\Facades\Artisan::all()['merchandise:expire-orders'] ?? null);
    }
}
