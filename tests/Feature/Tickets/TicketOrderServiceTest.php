<?php

namespace Tests\Feature\Tickets;

use App\Models\Event;
use App\Models\EventTicketSetting;
use App\Models\Organization;
use App\Models\TicketOrder;
use App\Models\TicketType;
use App\Services\Tickets\TicketOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TicketOrderServiceTest extends TestCase
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
            'name' => 'eLive Concert',
            'slug' => 'elive-concert-' . uniqid(),
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

    public function test_it_creates_pending_ticket_order_with_expiry(): void
    {
        $event = $this->createEvent();

        $ticketType = TicketType::create([
            'event_id' => $event->id,
            'name' => 'VIP',
            'code' => 'VIP',
            'price' => 50000,
            'currency' => 'TZS',
            'capacity' => 100,
            'min_per_order' => 1,
            'max_per_order' => 10,
            'is_active' => true,
            'is_public' => true,
        ]);

        $service = app(TicketOrderService::class);

        $order = $service->createOrder(
            event: $event,
            buyer: [
                'name' => 'Lucas Buyer',
                'phone' => '255700000001',
                'email' => 'buyer@example.com',
            ],
            items: [
                [
                    'ticket_type_id' => $ticketType->id,
                    'quantity' => 2,
                ],
            ],
        );

        $this->assertSame(
            TicketOrder::STATUS_PENDING,
            $order->status
        );

        $this->assertSame(2, $order->quantity);

        $this->assertSame(
            '100000.00',
            $order->total
        );

        $this->assertNotNull($order->expires_at);

        $this->assertCount(
            1,
            $order->items
        );

        $this->assertSame(
            2,
            $order->items->first()->quantity
        );
    }

    public function test_it_rejects_order_when_capacity_is_insufficient(): void
    {
        $event = $this->createEvent();

        $ticketType = TicketType::create([
            'event_id' => $event->id,
            'name' => 'VVIP',
            'code' => 'VVIP',
            'price' => 100000,
            'currency' => 'TZS',
            'capacity' => 1,
            'min_per_order' => 1,
            'max_per_order' => 10,
            'is_active' => true,
            'is_public' => true,
        ]);

        $service = app(TicketOrderService::class);

        $this->expectException(
            ValidationException::class
        );

        $service->createOrder(
            event: $event,
            buyer: [
                'name' => 'Buyer',
                'phone' => '255700000002',
                'email' => null,
            ],
            items: [
                [
                    'ticket_type_id' => $ticketType->id,
                    'quantity' => 2,
                ],
            ],
        );
    }

    public function test_it_uses_event_reservation_minutes(): void
    {
        $event = $this->createEvent();

        $event->ticketSetting()->update([
            'reservation_minutes' => 30,
        ]);

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

        $before = now();

        $order = app(TicketOrderService::class)
            ->createOrder(
                event: $event,
                buyer: [
                    'name' => 'Buyer',
                    'phone' => '255700000003',
                    'email' => null,
                ],
                items: [
                    [
                        'ticket_type_id' => $ticketType->id,
                        'quantity' => 1,
                    ],
                ],
            );

        $this->assertTrue(
            $order->expires_at->between(
                $before->copy()->addMinutes(29),
                $before->copy()->addMinutes(31)
            )
        );
    }
}
