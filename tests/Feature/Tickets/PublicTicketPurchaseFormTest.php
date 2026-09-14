<?php

namespace Tests\Feature\Tickets;

use App\Models\Event;
use App\Models\EventTicketSetting;
use App\Models\Organization;
use App\Models\TicketType;
use App\Services\Tickets\TicketOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicTicketPurchaseFormTest extends TestCase
{
    use RefreshDatabase;

    private function createEvent(): Event
    {
        $organization = Organization::query()->create([
            'name' => 'Ticket Form Organizer',
            'slug' => 'ticket-form-organizer-' . uniqid(),
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'eLive Music Night',
            'slug' => 'elive-music-night-' . uniqid(),
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

    public function test_purchase_page_contains_checkout_form(): void
    {
        $event = $this->createEvent();

        $ticketType = TicketType::query()->create([
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
        ]);

        $response = $this->get(
            route(
                'public.tickets.buy',
                $event->slug
            )
        );

        $response
            ->assertOk()
            ->assertSee('Your details')
            ->assertSee('Order summary')
            ->assertSee('Continue to Payment')
            ->assertSee('buyer_name', false)
            ->assertSee('buyer_phone', false)
            ->assertSee('buyer_email', false)
            ->assertSee(
                'tickets[' . $ticketType->id . ']',
                false
            );
    }

    public function test_sold_out_ticket_is_not_selectable(): void
    {
        $event = $this->createEvent();

        $ticketType = TicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'Sold Out VIP',
            'code' => 'SOLD-VIP',
            'price' => 100000,
            'currency' => 'TZS',
            'capacity' => 1,
            'min_per_order' => 1,
            'max_per_order' => 5,
            'is_active' => true,
            'is_public' => true,
        ]);

        /*
         * Reserve the only available ticket.
         *
         * This is important because capacity = 0 means
         * unlimited capacity in the current ticketing logic.
         */
        app(TicketOrderService::class)
            ->createOrder(
                event: $event,
                buyer: [
                    'name' => 'Existing Buyer',
                    'phone' => '255700000009',
                    'email' => 'existing@example.com',
                ],
                items: [
                    [
                        'ticket_type_id' =>
                            $ticketType->id,

                        'quantity' => 1,
                    ],
                ],
            );

        $response = $this->get(
            route(
                'public.tickets.buy',
                $event->slug
            )
        );

        $response
            ->assertOk()
            ->assertSee('Sold Out VIP')
            ->assertSee('SOLD OUT')
            ->assertSee(
                'disabled',
                false
            );
    }
}