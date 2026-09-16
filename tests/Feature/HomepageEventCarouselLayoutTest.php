<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageEventCarouselLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_upcoming_events_render_compact_homepage_track(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Carousel Test Organization',
            'slug' => 'carousel-test-organization',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        foreach ([
            ['First Event', 'first-event', 5],
            ['Second Event', 'second-event', 10],
        ] as [$name, $slug, $days]) {
            Event::query()->create([
                'organization_id' => $organization->id,
                'name' => $name,
                'slug' => $slug,
                'event_type' => 'conference',
                'venue' => 'Test Venue',
                'starts_at' => now()->addDays($days),
                'ends_at' => now()->addDays($days)->addHours(4),
                'capacity' => 200,
                'status' => 'active',
                'registration_is_open' => true,
            ]);
        }

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-card-count="2"', false);
    }

    public function test_three_upcoming_events_render_three_card_track(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Three Card Organization',
            'slug' => 'three-card-organization',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        foreach ([5, 10, 15] as $index => $days) {
            Event::query()->create([
                'organization_id' => $organization->id,
                'name' => 'Event ' . ($index + 1),
                'slug' => 'event-' . ($index + 1),
                'event_type' => 'conference',
                'venue' => 'Test Venue',
                'starts_at' => now()->addDays($days),
                'ends_at' => now()->addDays($days)->addHours(4),
                'capacity' => 200,
                'status' => 'active',
                'registration_is_open' => true,
            ]);
        }

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('data-card-count="3"', false);
    }
}