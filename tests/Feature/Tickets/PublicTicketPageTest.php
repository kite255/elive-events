<?php

namespace Tests\Feature\Tickets;

use App\Models\Event;
use App\Models\EventTicketSetting;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketType;
use App\Services\Tickets\TicketIssuanceService;
use App\Services\Tickets\TicketOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicTicketPageTest extends TestCase
{
    use RefreshDatabase;

    private function createIssuedTicket(): Ticket
    {
        $organization = Organization::query()->create([
            'name' => 'Ticket Page Organizer',
            'slug' => 'ticket-page-organizer-' . uniqid(),
        ]);

        $event = Event::query()->create([
            'organization_id' =>
                $organization->id,

            'name' =>
                'eLive Concert Ticket',

            'slug' =>
                'elive-concert-ticket-' . uniqid(),

            'status' =>
                Event::STATUS_ACTIVE,

            'starts_at' =>
                now()->addDays(3),
        ]);

        EventTicketSetting::query()->create([
            'event_id' =>
                $event->id,

            'ticket_sales_enabled' =>
                true,

            'reservation_minutes' =>
                15,

            'max_tickets_per_order' =>
                10,

            'allow_guest_checkout' =>
                true,
        ]);

        $ticketType =
            TicketType::query()->create([
                'event_id' =>
                    $event->id,

                'name' =>
                    'VIP',

                'code' =>
                    'VIP',

                'price' =>
                    50000,

                'currency' =>
                    'TZS',

                'capacity' =>
                    100,

                'min_per_order' =>
                    1,

                'max_per_order' =>
                    5,

                'is_active' =>
                    true,

                'is_public' =>
                    true,
            ]);

        $order =
            app(
                TicketOrderService::class
            )->createOrder(
                event: $event,
                buyer: [
                    'name' =>
                        'Lucas Buyer',

                    'phone' =>
                        '255700000001',

                    'email' =>
                        'buyer@example.com',
                ],
                items: [
                    [
                        'ticket_type_id' =>
                            $ticketType->id,

                        'quantity' =>
                            1,
                    ],
                ],
            );

        $order->update([
            'status' =>
                TicketOrder::STATUS_PAID,

            'paid_at' =>
                now(),
        ]);

        return app(
            TicketIssuanceService::class
        )
            ->issueForOrder(
                $order->fresh()
            )
            ->first();
    }

    public function test_issued_ticket_can_open_public_ticket_page(): void
    {
        $ticket =
            $this->createIssuedTicket();

        $response =
            $this->get(
                '/tickets/'
                . $ticket->public_token
            );

        $response
            ->assertOk()
            ->assertSee(
                'eLive Concert Ticket'
            )
            ->assertSee(
                'VIP'
            )
            ->assertSee(
                $ticket->ticket_number
            )
            ->assertSee(
                'Lucas Buyer'
            );
    }

    public function test_ticket_page_contains_qr_code(): void
    {
        $ticket =
            $this->createIssuedTicket();

        $response =
            $this->get(
                '/tickets/'
                . $ticket->public_token
            );

        $response
            ->assertOk()
            ->assertSee(
                '<svg',
                false
            );
    }

    public function test_ticket_page_does_not_expose_qr_hash(): void
    {
        $ticket =
            $this->createIssuedTicket();

        $response =
            $this->get(
                '/tickets/'
                . $ticket->public_token
            );

        $response
            ->assertDontSee(
                $ticket->qr_token_hash
            );
    }

    public function test_ticket_page_does_not_render_raw_qr_secret_as_text(): void
    {
        $ticket =
            $this->createIssuedTicket();

        $rawQrToken =
            $ticket->qr_token_encrypted;

        $response =
            $this->get(
                '/tickets/'
                . $ticket->public_token
            );

        $response
            ->assertDontSee(
                $rawQrToken
            );
    }

    public function test_unknown_ticket_public_token_returns_404(): void
    {
        $response =
            $this->get(
                '/tickets/'
                . str_repeat(
                    'x',
                    40
                )
            );

        $response->assertNotFound();
    }

    public function test_cancelled_ticket_is_not_publicly_available(): void
    {
        $ticket =
            $this->createIssuedTicket();

        $ticket->update([
            'status' =>
                Ticket::STATUS_CANCELLED,

            'cancelled_at' =>
                now(),
        ]);

        $response =
            $this->get(
                '/tickets/'
                . $ticket->public_token
            );

        $response->assertNotFound();
    }

    public function test_refunded_ticket_is_not_publicly_available(): void
    {
        $ticket =
            $this->createIssuedTicket();

        $ticket->update([
            'status' =>
                Ticket::STATUS_REFUNDED,

            'refunded_at' =>
                now(),
        ]);

        $response =
            $this->get(
                '/tickets/'
                . $ticket->public_token
            );

        $response->assertNotFound();
    }
}
