<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageEventCarouselAutoplayTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_loads_up_to_six_upcoming_events_for_carousel(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Carousel Autoplay Organization',
            'slug' => 'carousel-autoplay-organization',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        foreach (range(1, 7) as $index) {
            Event::query()->create([
                'organization_id' => $organization->id,
                'name' => 'Upcoming Event ' . $index,
                'slug' => 'upcoming-event-' . $index,
                'event_type' => 'conference',
                'venue' => 'Test Venue',
                'starts_at' => now()->addDays($index),
                'ends_at' => now()->addDays($index)->addHours(4),
                'capacity' => 200,
                'status' => 'active',
                'registration_is_open' => true,
            ]);
        }

        $response = $this->get(route('home'));

        $response
            ->assertOk()
            ->assertSee('data-card-count="6"', false)
            ->assertSee('Upcoming Event 1')
            ->assertSee('Upcoming Event 6')
            ->assertDontSee('Upcoming Event 7');
    }

    public function test_homepage_carousel_declares_autoplay_configuration(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Autoplay Marker Organization',
            'slug' => 'autoplay-marker-organization',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Autoplay Event',
            'slug' => 'autoplay-event',
            'event_type' => 'conference',
            'venue' => 'Test Venue',
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(5)->addHours(4),
            'capacity' => 200,
            'status' => 'active',
            'registration_is_open' => true,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-autoplay="true"', false)
            ->assertSee('data-autoplay-interval="4000"', false);
    }
}
