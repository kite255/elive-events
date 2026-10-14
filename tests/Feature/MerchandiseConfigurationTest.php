<?php

namespace Tests\Feature;

use App\Models\MerchandiseProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MerchandiseConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_merchandise_tables_support_general_products(): void
    {
        $this->assertTrue(Schema::hasTable('merchandise_products'));
        $this->assertTrue(Schema::hasColumns('merchandise_products', [
            'category', 'price_tzs', 'stock_quantity', 'colors', 'sizes', 'is_active',
        ]));
        $this->assertTrue(Schema::hasColumns('merchandise_orders', [
            'public_token', 'color', 'size', 'quantity', 'status',
        ]));
    }

    public function test_merchandise_product_attributes_are_cast(): void
    {
        $product = new MerchandiseProduct([
            'category' => 'books',
            'colors' => [],
            'sizes' => [],
            'is_active' => false,
            'stock_quantity' => 5,
        ]);

        $this->assertSame('books', $product->category);
        $this->assertSame([], $product->colors);
        $this->assertSame([], $product->sizes);
        $this->assertFalse($product->is_active);
        $this->assertSame(5, $product->stock_quantity);
    }

    public function test_unknown_merchandise_order_is_not_found(): void
    {
        $this->get('/merchandise/orders/not-a-valid-order-token')->assertNotFound();
    }
}
