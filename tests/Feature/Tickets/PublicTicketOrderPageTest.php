<?php

namespace Tests\Feature\Tickets;

use App\Models\Event;
use App\Models\EventTicketSetting;
use App\Models\Organization;
use App\Models\TicketOrder;
use App\Models\TicketType;
use App\Services\Tickets\TicketIssuanceService;
use App\Services\Tickets\TicketOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicTicketOrderPageTest extends TestCase
{
    use RefreshDatabase;

    private function createEvent(): Event
    {
        $organization = Organization::query()->create([
            'name' => 'My Tickets Organizer',
            'slug' => 'my-tickets-organizer-' . uniqid(),
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'eLive My Tickets Concert',
            'slug' => 'elive-my-tickets-' . uniqid(),
            'status' => Event::STATUS_ACTIVE,
            'starts_at' => now()->addDays(5),
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

    private function createOrder(
        bool $paid = true
    ): TicketOrder {
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

        $order = app(TicketOrderService::class)
            ->createOrder(
                event: $event,
                buyer: [
                    'name' => 'Lucas Buyer',
                    'phone' => '255700000001',
                    'email' => 'buyer@example.com',
                ],
                items: [
                    [
                        'ticket_type_id' =>
                            $ticketType->id,

                        'quantity' => 2,
                    ],
                ],
            );

        if (! $paid) {
            return $order;
        }

        $order->update([
            'status' =>
                TicketOrder::STATUS_PAID,

            'paid_at' =>
                now(),
        ]);

        app(TicketIssuanceService::class)
            ->issueForOrder(
                $order->fresh()
            );

        return $order->fresh();
    }

    public function test_paid_order_can_open_my_tickets_page(): void
    {
        $order =
            $this->createOrder();

        $response =
            $this->get(
                '/events/tickets/order/'
                . $order->public_token
            );

        $response
            ->assertOk()
            ->assertSee(
                'My Tickets'
            )
            ->assertSee(
                'eLive My Tickets Concert'
            )
            ->assertSee(
                $order->order_number
            )
            ->assertSee(
                'Lucas Buyer'
            )
            ->assertSee(
                'VIP'
            );
    }

    public function test_my_tickets_page_lists_all_issued_tickets(): void
    {
        $order =
            $this->createOrder();

        $tickets =
            $order
                ->tickets()
                ->orderBy('id')
                ->get();

        $this->assertCount(
            2,
            $tickets
        );

        $response =
            $this->get(
                '/events/tickets/order/'
                . $order->public_token
            );

        foreach ($tickets as $ticket) {
            $response->assertSee(
                $ticket->ticket_number
            );
        }
    }

    public function test_unpaid_order_cannot_open_my_tickets_page(): void
    {
        $order =
            $this->createOrder(
                paid: false
            );

        $response =
            $this->get(
                '/events/tickets/order/'
                . $order->public_token
            );

        $response->assertNotFound();
    }

    public function test_unknown_order_public_token_returns_404(): void
    {
        $response =
            $this->get(
                '/events/tickets/order/'
                . str_repeat(
                    'x',
                    48
                )
            );

        $response->assertNotFound();
    }

    public function test_my_tickets_page_does_not_expose_qr_secrets(): void
    {
        $order =
            $this->createOrder();

        $ticket =
            $order
                ->tickets()
                ->first();

        $this->assertNotNull(
            $ticket
        );

        $response =
            $this->get(
                '/events/tickets/order/'
                . $order->public_token
            );

        $response
            ->assertDontSee(
                $ticket->qr_token_hash
            )
            ->assertDontSee(
                $ticket->qr_token_encrypted
            );
    }
}
