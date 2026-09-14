<?php

namespace Tests\Feature\Tickets;

use App\Models\Event;
use App\Models\EventTicketSetting;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketOrderItem;
use App\Models\TicketType;
use App\Services\Tickets\TicketAvailabilityService;
use App\Services\Tickets\TicketOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TicketReservationTest extends TestCase
{
    use RefreshDatabase;

    private function createEvent(): Event
    {
        $organization = Organization::create([
            'name' => 'Concert Organizer',
            'slug' => 'concert-organizer-' . uniqid(),
        ]);

        $event = Event::create([
            'organization_id' => $organization->id,
            'name' => 'eLive Test Concert',
            'slug' => 'elive-test-concert-' . uniqid(),
            'status' => Event::STATUS_ACTIVE,
        ]);

        EventTicketSetting::create([
            'event_id' => $event->id,
            'ticket_sales_enabled' => true,
            'reservation_minutes' => 15,
            'max_tickets_per_order' => 10,
            'allow_guest_checkout' => true,
        ]);

        return $event;
    }

    public function test_pending_unexpired_orders_reserve_ticket_capacity(): void
    {
        $event = $this->createEvent();

        $ticketType = TicketType::create([
            'event_id' => $event->id,
            'name' => 'VIP',
            'code' => 'VIP',
            'price' => 50000,
            'currency' => 'TZS',
            'capacity' => 10,
            'min_per_order' => 1,
            'max_per_order' => 10,
            'is_active' => true,
            'is_public' => true,
        ]);

        $order = TicketOrder::create([
            'event_id' => $event->id,
            'order_number' => 'ORD-' . uniqid(),
            'buyer_name' => 'Test Buyer',
            'quantity' => 4,
            'subtotal' => 200000,
            'discount_amount' => 0,
            'total' => 200000,
            'currency' => 'TZS',
            'status' => TicketOrder::STATUS_PENDING,
            'expires_at' => now()->addMinutes(15),
        ]);

        TicketOrderItem::create([
            'ticket_order_id' => $order->id,
            'ticket_type_id' => $ticketType->id,
            'quantity' => 4,
            'unit_price' => 50000,
            'subtotal' => 200000,
            'discount_amount' => 0,
            'total' => 200000,
        ]);

        $service = app(
            TicketAvailabilityService::class
        );

        $this->assertSame(
            4,
            $service->reservedQuantity(
                $ticketType
            )
        );

        $this->assertSame(
            6,
            $service->availableQuantity(
                $ticketType
            )
        );
    }

    public function test_expired_pending_orders_do_not_reserve_capacity(): void
    {
        $event = $this->createEvent();

        $ticketType = TicketType::create([
            'event_id' => $event->id,
            'name' => 'Regular',
            'code' => 'REG',
            'price' => 10000,
            'currency' => 'TZS',
            'capacity' => 100,
            'min_per_order' => 1,
            'max_per_order' => 10,
            'is_active' => true,
            'is_public' => true,
        ]);

        $order = TicketOrder::create([
            'event_id' => $event->id,
            'order_number' => 'ORD-' . uniqid(),
            'buyer_name' => 'Expired Buyer',
            'quantity' => 5,
            'subtotal' => 50000,
            'discount_amount' => 0,
            'total' => 50000,
            'currency' => 'TZS',
            'status' => TicketOrder::STATUS_PENDING,
            'expires_at' => now()->subMinute(),
        ]);

        TicketOrderItem::create([
            'ticket_order_id' => $order->id,
            'ticket_type_id' => $ticketType->id,
            'quantity' => 5,
            'unit_price' => 10000,
            'subtotal' => 50000,
            'discount_amount' => 0,
            'total' => 50000,
        ]);

        $service = app(
            TicketAvailabilityService::class
        );

        $this->assertSame(
            0,
            $service->reservedQuantity(
                $ticketType
            )
        );

        $this->assertSame(
            100,
            $service->availableQuantity(
                $ticketType
            )
        );
    }

    public function test_issued_tickets_reduce_available_capacity(): void
    {
        $event = $this->createEvent();

        $ticketType = TicketType::create([
            'event_id' => $event->id,
            'name' => 'VVIP',
            'code' => 'VVIP',
            'price' => 100000,
            'currency' => 'TZS',
            'capacity' => 10,
            'min_per_order' => 1,
            'max_per_order' => 10,
            'is_active' => true,
            'is_public' => true,
        ]);

        $order = TicketOrder::create([
            'event_id' => $event->id,
            'order_number' => 'ORD-' . uniqid(),
            'buyer_name' => 'Paid Buyer',
            'quantity' => 3,
            'subtotal' => 300000,
            'discount_amount' => 0,
            'total' => 300000,
            'currency' => 'TZS',
            'status' => TicketOrder::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $item = TicketOrderItem::create([
            'ticket_order_id' => $order->id,
            'ticket_type_id' => $ticketType->id,
            'quantity' => 3,
            'unit_price' => 100000,
            'subtotal' => 300000,
            'discount_amount' => 0,
            'total' => 300000,
        ]);

        for ($i = 1; $i <= 3; $i++) {
            Ticket::create([
                'event_id' => $event->id,
                'ticket_order_id' => $order->id,
                'ticket_order_item_id' => $item->id,
                'ticket_type_id' => $ticketType->id,

                'ticket_number' =>
                    'TEST-' . uniqid() . '-' . $i,

                'public_token' =>
                    Str::random(40),

                'qr_token_hash' =>
                    hash(
                        'sha256',
                        Str::random(64)
                    ),

                'holder_name' =>
                    'Ticket Holder ' . $i,

                'price' => 100000,
                'currency' => 'TZS',

                'status' =>
                    Ticket::STATUS_ISSUED,

                'issued_at' => now(),
            ]);
        }

        $service = app(
            TicketAvailabilityService::class
        );

        $this->assertSame(
            3,
            $service->soldQuantity(
                $ticketType
            )
        );

        $this->assertSame(
            7,
            $service->availableQuantity(
                $ticketType
            )
        );
    }

    public function test_existing_active_reservation_prevents_overselling(): void
    {
        $event = $this->createEvent();

        $ticketType = TicketType::create([
            'event_id' => $event->id,
            'name' => 'VIP',
            'code' => 'VIP',
            'price' => 50000,
            'currency' => 'TZS',
            'capacity' => 2,
            'min_per_order' => 1,
            'max_per_order' => 10,
            'is_active' => true,
            'is_public' => true,
        ]);

        $service = app(
            TicketOrderService::class
        );

        $firstOrder =
            $service->createOrder(
                event: $event,
                buyer: [
                    'name' =>
                        'First Buyer',

                    'phone' =>
                        '255700000001',

                    'email' =>
                        null,
                ],
                items: [
                    [
                        'ticket_type_id' =>
                            $ticketType->id,

                        'quantity' =>
                            2,
                    ],
                ],
            );

        $this->assertSame(
            TicketOrder::STATUS_PENDING,
            $firstOrder->status
        );

        $this->assertNotNull(
            $firstOrder->expires_at
        );

        $this->assertSame(
            2,
            app(
                TicketAvailabilityService::class
            )->reservedQuantity(
                $ticketType
            )
        );

        $this->assertSame(
            0,
            app(
                TicketAvailabilityService::class
            )->availableQuantity(
                $ticketType
            )
        );

        $this->expectException(
            ValidationException::class
        );

        $service->createOrder(
            event: $event,
            buyer: [
                'name' =>
                    'Second Buyer',

                'phone' =>
                    '255700000002',

                'email' =>
                    null,
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
    }
}