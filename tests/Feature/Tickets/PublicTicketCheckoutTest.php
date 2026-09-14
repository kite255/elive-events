<?php

namespace Tests\Feature\Tickets;

use App\Models\Event;
use App\Models\EventTicketSetting;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\TicketOrder;
use App\Models\TicketType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicTicketCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function createEvent(): Event
    {
        $organization = Organization::query()->create([
            'name' => 'Checkout Organizer',
            'slug' => 'checkout-organizer-' . uniqid(),
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'eLive Checkout Concert',
            'slug' => 'elive-checkout-' . uniqid(),
            'status' => Event::STATUS_ACTIVE,
            'starts_at' => now()->addDays(10),
        ]);

        EventTicketSetting::query()->create([
            'event_id' => $event->id,
            'ticket_sales_enabled' => true,
            'reservation_minutes' => 15,
            'max_tickets_per_order' => 10,
            'allow_guest_checkout' => true,
        ]);

        PaymentGateway::query()->create([
            'organization_id' => null,
            'name' => 'Pesapal',
            'code' => 'pesapal',
            'is_enabled' => true,
            'is_default' => true,
            'environment' => 'sandbox',
        ]);

        return $event;
    }

    private function createTicketType(
        Event $event
    ): TicketType {
        return TicketType::query()->create([
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
    }

    public function test_buyer_can_create_ticket_order_and_payment(): void
    {
        $event = $this->createEvent();

        $ticketType =
            $this->createTicketType(
                $event
            );

        $response =
            $this->post(
                '/events/'
                . $event->slug
                . '/tickets',
                [
                    'buyer_name' =>
                        'Lucas Buyer',

                    'buyer_phone' =>
                        '255700000001',

                    'buyer_email' =>
                        'buyer@example.com',

                    'tickets' => [
                        $ticketType->id => 2,
                    ],
                ]
            );

        $order =
            TicketOrder::query()
                ->latest('id')
                ->first();

        $this->assertNotNull(
            $order
        );

        $this->assertSame(
            'Lucas Buyer',
            $order->buyer_name
        );

        $this->assertSame(
            2,
            $order->quantity
        );

        $this->assertSame(
            '100000.00',
            $order->total
        );

        $this->assertSame(
            TicketOrder::STATUS_PENDING,
            $order->status
        );

        $payment =
            Payment::query()
                ->where(
                    'ticket_order_id',
                    $order->id
                )
                ->first();

        $this->assertNotNull(
            $payment
        );

        $this->assertSame(
            Payment::STATUS_PENDING,
            $payment->status
        );

        $this->assertSame(
            '100000.00',
            $payment->amount
        );

        $response->assertRedirect(
            route(
                'payments.pay',
                $payment
            )
        );
    }

    public function test_checkout_requires_buyer_name(): void
    {
        $event = $this->createEvent();

        $ticketType =
            $this->createTicketType(
                $event
            );

        $response =
            $this
                ->from(
                    '/events/'
                    . $event->slug
                    . '/tickets'
                )
                ->post(
                    '/events/'
                    . $event->slug
                    . '/tickets',
                    [
                        'buyer_name' => '',
                        'buyer_phone' =>
                            '255700000001',
                        'buyer_email' =>
                            'buyer@example.com',
                        'tickets' => [
                            $ticketType->id => 1,
                        ],
                    ]
                );

        $response
            ->assertRedirect()
            ->assertSessionHasErrors([
                'buyer_name',
            ]);

        $this->assertDatabaseCount(
            'ticket_orders',
            0
        );
    }

    public function test_checkout_requires_at_least_one_ticket(): void
    {
        $event = $this->createEvent();

        $response =
            $this
                ->from(
                    '/events/'
                    . $event->slug
                    . '/tickets'
                )
                ->post(
                    '/events/'
                    . $event->slug
                    . '/tickets',
                    [
                        'buyer_name' =>
                            'Lucas Buyer',
                        'buyer_phone' =>
                            '255700000001',
                        'buyer_email' =>
                            'buyer@example.com',
                        'tickets' => [],
                    ]
                );

        $response
            ->assertRedirect()
            ->assertSessionHasErrors([
                'tickets',
            ]);

        $this->assertDatabaseCount(
            'ticket_orders',
            0
        );
    }

    public function test_checkout_rejects_ticket_from_another_event(): void
    {
        $event = $this->createEvent();

        $otherEvent =
            $this->createEvent();

        $otherTicket =
            $this->createTicketType(
                $otherEvent
            );

        $response =
            $this
                ->from(
                    '/events/'
                    . $event->slug
                    . '/tickets'
                )
                ->post(
                    '/events/'
                    . $event->slug
                    . '/tickets',
                    [
                        'buyer_name' =>
                            'Lucas Buyer',

                        'buyer_phone' =>
                            '255700000001',

                        'buyer_email' =>
                            'buyer@example.com',

                        'tickets' => [
                            $otherTicket->id => 1,
                        ],
                    ]
                );

        $response
            ->assertRedirect()
            ->assertSessionHasErrors([
                'tickets',
            ]);

        $this->assertDatabaseCount(
            'ticket_orders',
            0
        );
    }

    public function test_checkout_is_blocked_when_ticket_sales_are_disabled(): void
    {
        $event =
            $this->createEvent();

        $ticketType =
            $this->createTicketType(
                $event
            );

        $event
            ->ticketSetting()
            ->update([
                'ticket_sales_enabled' =>
                    false,
            ]);

        $response =
            $this->post(
                '/events/'
                . $event->slug
                . '/tickets',
                [
                    'buyer_name' =>
                        'Lucas Buyer',

                    'buyer_phone' =>
                        '255700000001',

                    'buyer_email' =>
                        'buyer@example.com',

                    'tickets' => [
                        $ticketType->id => 1,
                    ],
                ]
            );

        $response->assertNotFound();

        $this->assertDatabaseCount(
            'ticket_orders',
            0
        );
    }
}
