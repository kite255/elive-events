<?php

namespace Tests\Feature\Tickets;

use App\Models\Event;
use App\Models\EventTicketSetting;
use App\Models\Organization;
use App\Models\TicketType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicTicketPurchasePageTest extends TestCase
{
    use RefreshDatabase;

    private function createEvent(): Event
    {
        $organization = Organization::query()->create([
            'name' => 'Public Ticket Organizer',
            'slug' => 'public-ticket-organizer-' . uniqid(),
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'eLive Public Concert',
            'slug' => 'elive-public-concert-' . uniqid(),
            'status' => Event::STATUS_ACTIVE,
            'starts_at' => now()->addDays(7),
        ]);

        EventTicketSetting::query()->create([
            'event_id' => $event->id,
            'ticket_sales_enabled' => true,
            'reservation_minutes' => 15,
            'max_tickets_per_order' => 10,
            'allow_guest_checkout' => true,
        ]);

        return $event;
    }

    public function test_public_ticket_purchase_page_is_available(): void
    {
        $event = $this->createEvent();

        TicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'VIP',
            'code' => 'VIP',
            'price' => 50000,
            'currency' => 'TZS',
            'capacity' => 100,
            'min_per_order' => 1,
            'max_per_order' => 5,
            'is_active' => true,
            'is_public' => true,
            'sort_order' => 1,
        ]);

        $response = $this->get(
            '/events/' . $event->slug . '/tickets'
        );

        $response
            ->assertOk()
            ->assertSee('eLive Public Concert')
            ->assertSee('VIP')
            ->assertSee('50,000');
    }

    public function test_private_ticket_type_is_not_shown_publicly(): void
    {
        $event = $this->createEvent();

        TicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'Public VIP',
            'code' => 'PUB-VIP',
            'price' => 50000,
            'currency' => 'TZS',
            'capacity' => 100,
            'min_per_order' => 1,
            'max_per_order' => 5,
            'is_active' => true,
            'is_public' => true,
            'sort_order' => 1,
        ]);

        TicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'Private Sponsor Ticket',
            'code' => 'SPONSOR',
            'price' => 0,
            'currency' => 'TZS',
            'capacity' => 20,
            'min_per_order' => 1,
            'max_per_order' => 1,
            'is_active' => true,
            'is_public' => false,
            'sort_order' => 2,
        ]);

        $response = $this->get(
            '/events/' . $event->slug . '/tickets'
        );

        $response
            ->assertOk()
            ->assertSee('Public VIP')
            ->assertDontSee('Private Sponsor Ticket');
    }

    public function test_ticket_type_outside_sales_period_is_not_shown(): void
    {
        $event = $this->createEvent();

        TicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'Available Ticket',
            'code' => 'AVAILABLE',
            'price' => 20000,
            'currency' => 'TZS',
            'capacity' => 100,
            'min_per_order' => 1,
            'max_per_order' => 5,
            'is_active' => true,
            'is_public' => true,
            'sales_start_at' => now()->subDay(),
            'sales_end_at' => now()->addDay(),
        ]);

        TicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'Future Ticket',
            'code' => 'FUTURE',
            'price' => 20000,
            'currency' => 'TZS',
            'capacity' => 100,
            'min_per_order' => 1,
            'max_per_order' => 5,
            'is_active' => true,
            'is_public' => true,
            'sales_start_at' => now()->addDay(),
        ]);

        $response = $this->get(
            '/events/' . $event->slug . '/tickets'
        );

        $response
            ->assertOk()
            ->assertSee('Available Ticket')
            ->assertDontSee('Future Ticket');
    }

    public function test_ticket_purchase_page_is_blocked_when_event_ticket_sales_are_disabled(): void
    {
        $event = $this->createEvent();

        $event->ticketSetting()->update([
            'ticket_sales_enabled' => false,
        ]);

        $response = $this->get(
            '/events/' . $event->slug . '/tickets'
        );

        $response->assertNotFound();
    }
}
