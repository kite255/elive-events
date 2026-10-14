<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\MerchandiseProduct;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MerchandiseConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_merchandise_is_hidden_by_default(): void
    {
        $event = Event::factory()->create([
            'merchandise_section_enabled' => false,
            'status' => 'active',
        ]);

        $response = $this->get(route('public.merchandise.index', ['event' => $event->slug]));
        $response->assertNotFound();
    }

    public function test_unpublished_products_are_not_listed(): void
    {
        $event = Event::factory()->create([
            'merchandise_section_enabled' => true,
            'status' => 'active',
        ]);

        MerchandiseProduct::create([
            'event_id' => $event->id,
            'name' => 'Hidden Mug',
            'slug' => 'hidden-mug',
            'category' => 'drinkware',
            'price_tzs' => 10000,
            'stock_quantity' => 2,
            'is_active' => false,
        ]);

        $this->get(route('public.merchandise.index', ['event' => $event->slug]))
            ->assertOk()
            ->assertDontSee('Hidden Mug');
    }
}
