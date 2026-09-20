<?php

namespace Tests\Feature\Events;

use App\Models\Event;
use App\Models\EventTicketSetting;
use App\Models\Organization;
use App\Models\TicketType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicEventTicketCtaTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticketed_concert_shows_buy_tickets_on_public_event_pages(): void
    {
        $organization = Organization::query()->create([
            'name' => 'eLive Test Organization',
            'slug' => 'elive-test-organization',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'eLive Concert Test',
            'slug' => 'elive-concert-test',
            'event_type' => 'concert',
            'venue' => 'Mlimani City Conference Hall',
            'starts_at' => now()->addDays(7),
            'ends_at' => now()->addDays(7)->addHours(5),
            'capacity' => 500,
            'status' => 'active',
            'registration_is_open' => false,
        ]);

        EventTicketSetting::query()->create([
            'event_id' => $event->id,
            'ticket_sales_enabled' => true,
            'reservation_minutes' => 15,
            'max_tickets_per_order' => 10,
            'allow_guest_checkout' => true,
        ]);

        TicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'Regular',
            'code' => 'REG',
            'price' => 1000,
            'currency' => 'TZS',
            'capacity' => 100,
            'min_per_order' => 1,
            'max_per_order' => 10,
            'is_active' => true,
            'is_public' => true,
            'requires_holder_details' => false,
            'sort_order' => 1,
        ]);

        $ticketUrl = route(
            'public.tickets.buy',
            ['event' => $event->slug]
        );

        $this->get(
            route('public.events.index')
        )
            ->assertOk()
            ->assertSee('Buy Tickets')
            ->assertSee($ticketUrl, false);

        $this->get(
            route(
                'public.events.show',
                ['event' => $event->slug]
            )
        )
            ->assertOk()
            ->assertSee('Buy Tickets')
            ->assertSee($ticketUrl, false);
    }

    public function test_normal_registration_event_still_shows_register_now(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Conference Organization',
            'slug' => 'conference-organization',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Test Conference',
            'slug' => 'test-conference',
            'event_type' => 'conference',
            'venue' => 'Conference Hall',
            'starts_at' => now()->addDays(10),
            'ends_at' => now()->addDays(10)->addHours(8),
            'capacity' => 200,
            'status' => 'active',
            'registration_is_open' => true,
        ]);

        $this->get(
            route('public.events.index')
        )
            ->assertOk()
            ->assertSee('Register Now');

        $this->get(
            route(
                'public.events.show',
                ['event' => $event->slug]
            )
        )
            ->assertOk()
            ->assertSee('Register for Event');
    }
}