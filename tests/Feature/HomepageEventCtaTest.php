<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventTicketSetting;
use App\Models\Organization;
use App\Models\TicketType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomepageEventCtaTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_shows_buy_tickets_for_ticketed_event_with_open_sales(): void
    {
        $organization = Organization::query()->create([
            'name' => 'eLive Test Organization',
            'slug' => 'elive-test-organization',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Ticketed Concert',
            'slug' => 'ticketed-concert',
            'event_type' => 'concert',
            'venue' => 'Test Venue',
            'starts_at' => now()->addDays(10),
            'ends_at' => now()->addDays(10)->addHours(4),
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

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Buy Tickets')
            ->assertSee($ticketUrl, false);
    }

    public function test_homepage_shows_register_now_for_registration_event(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Conference Organization',
            'slug' => 'conference-organization',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Registration Event',
            'slug' => 'registration-event',
            'event_type' => 'conference',
            'venue' => 'Conference Hall',
            'starts_at' => now()->addDays(10),
            'ends_at' => now()->addDays(10)->addHours(4),
            'capacity' => 200,
            'status' => 'active',
            'registration_is_open' => true,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Register Now');
    }

    public function test_homepage_shows_tickets_closed_when_ticket_sales_are_enabled_but_not_open(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Closed Ticket Organization',
            'slug' => 'closed-ticket-organization',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Closed Ticket Event',
            'slug' => 'closed-ticket-event',
            'event_type' => 'concert',
            'venue' => 'Test Venue',
            'starts_at' => now()->addDays(10),
            'ends_at' => now()->addDays(10)->addHours(4),
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
            'sales_start_at' => now()->subDays(5),
            'sales_end_at' => now()->subDay(),
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

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Tickets Closed');
    }
}