<?php

namespace Tests\Feature\Events;

use App\Models\Event;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PublicEventsApiTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-09-20 18:30:00', 'Africa/Dar_es_Salaam'));

        $this->organization = Organization::query()->create([
            'name' => 'Public Events Test Organization',
            'slug' => 'public-events-test-organization',
            'status' => Organization::STATUS_ACTIVE,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_future_event_is_upcoming(): void
    {
        $event = $this->createPublicEvent([
            'name' => 'Future Event',
            'slug' => 'future-event',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(2),
        ]);

        $this->getJson('/api/public/events')
            ->assertOk()
            ->assertJsonPath('data.0.id', 'events-'.$event->id)
            ->assertJsonPath('data.0.status', 'upcoming');
    }

    public function test_event_inside_start_and_end_window_is_live(): void
    {
        $event = $this->createPublicEvent([
            'name' => 'Live Event',
            'slug' => 'live-event',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
        ]);

        $this->getJson('/api/public/events')
            ->assertOk()
            ->assertJsonPath('data.0.id', 'events-'.$event->id)
            ->assertJsonPath('data.0.status', 'live');
    }

    public function test_event_without_end_time_is_live_only_on_its_start_date(): void
    {
        $today = $this->createPublicEvent([
            'name' => 'Today Event',
            'slug' => 'today-event',
            'starts_at' => now()->subHour(),
            'ends_at' => null,
        ]);

        $past = $this->createPublicEvent([
            'name' => 'Old Event',
            'slug' => 'old-event',
            'starts_at' => now()->subDays(5),
            'ends_at' => null,
        ]);

        $response = $this->getJson('/api/public/events')->assertOk();

        $events = collect($response->json('data'))->keyBy('id');

        $this->assertSame('live', $events['events-'.$today->id]['status']);
        $this->assertSame('past', $events['events-'.$past->id]['status']);
    }

    public function test_completed_event_is_past(): void
    {
        $event = $this->createPublicEvent([
            'name' => 'Completed Event',
            'slug' => 'completed-event',
            'status' => Event::STATUS_COMPLETED,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->subDay()->addHours(2),
        ]);

        $this->getJson('/api/public/events')
            ->assertOk()
            ->assertJsonPath('data.0.id', 'events-'.$event->id)
            ->assertJsonPath('data.0.status', 'past');
    }

    public function test_hidden_draft_and_cancelled_events_are_excluded(): void
    {
        $visible = $this->createPublicEvent([
            'name' => 'Visible Event',
            'slug' => 'visible-event',
            'starts_at' => now()->addDay(),
        ]);

        $this->createPublicEvent([
            'name' => 'Hidden Event',
            'slug' => 'hidden-event',
            'show_on_elive_website' => false,
            'starts_at' => now()->addDay(),
        ]);

        $this->createPublicEvent([
            'name' => 'Draft Event',
            'slug' => 'draft-event',
            'status' => Event::STATUS_DRAFT,
            'starts_at' => now()->addDay(),
        ]);

        $this->createPublicEvent([
            'name' => 'Cancelled Event',
            'slug' => 'cancelled-event',
            'status' => Event::STATUS_CANCELLED,
            'starts_at' => now()->addDay(),
        ]);

        $this->getJson('/api/public/events')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', 'events-'.$visible->id);
    }

    private function createPublicEvent(array $overrides = []): Event
    {
        return Event::query()->create(array_merge([
            'organization_id' => $this->organization->id,
            'name' => 'Public Event',
            'slug' => 'public-event-'.uniqid(),
            'event_type' => 'conference',
            'starts_at' => now()->addDay(),
            'ends_at' => null,
            'status' => Event::STATUS_ACTIVE,
            'show_on_elive_website' => true,
            'registration_is_open' => true,
        ], $overrides));
    }
}
