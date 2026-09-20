<?php

namespace Tests\Feature\Tickets;

use App\Models\Event;
use App\Models\Organization;
use App\Models\TicketOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpireTicketOrdersCommandTest extends TestCase
{
    use RefreshDatabase;

    private function createEvent(): Event
    {
        $organization = Organization::query()->create([
            'name' => 'Ticket Expiry Organization',
            'slug' => 'ticket-expiry-' . uniqid(),
        ]);

        return Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Ticket Expiry Event',
            'slug' => 'ticket-expiry-event-' . uniqid(),
            'status' => Event::STATUS_ACTIVE,
        ]);
    }

    private function createOrder(
        Event $event,
        string $status,
        $expiresAt
    ): TicketOrder {
        return TicketOrder::query()->create([
            'event_id' => $event->id,
            'order_number' => 'ORD-' . strtoupper(uniqid()),
            'buyer_name' => 'Expiry Test Buyer',
            'buyer_phone' => '255700000001',
            'buyer_email' => 'buyer@example.com',
            'quantity' => 1,
            'subtotal' => 10000,
            'discount_amount' => 0,
            'total' => 10000,
            'currency' => 'TZS',
            'status' => $status,
            'expires_at' => $expiresAt,
        ]);
    }

    public function test_expired_pending_order_is_marked_expired(): void
    {
        $event = $this->createEvent();

        $order = $this->createOrder(
            $event,
            TicketOrder::STATUS_PENDING,
            now()->subMinute()
        );

        $this->artisan('tickets:expire-orders')
            ->assertSuccessful();

        $this->assertSame(
            TicketOrder::STATUS_EXPIRED,
            $order->fresh()->status
        );
    }

    public function test_expired_processing_order_is_marked_expired(): void
    {
        $event = $this->createEvent();

        $order = $this->createOrder(
            $event,
            TicketOrder::STATUS_PROCESSING,
            now()->subMinute()
        );

        $this->artisan('tickets:expire-orders')
            ->assertSuccessful();

        $this->assertSame(
            TicketOrder::STATUS_EXPIRED,
            $order->fresh()->status
        );
    }

    public function test_future_reservation_is_not_expired(): void
    {
        $event = $this->createEvent();

        $order = $this->createOrder(
            $event,
            TicketOrder::STATUS_PENDING,
            now()->addMinutes(15)
        );

        $this->artisan('tickets:expire-orders')
            ->assertSuccessful();

        $this->assertSame(
            TicketOrder::STATUS_PENDING,
            $order->fresh()->status
        );
    }

    public function test_paid_order_is_never_expired(): void
    {
        $event = $this->createEvent();

        $order = $this->createOrder(
            $event,
            TicketOrder::STATUS_PAID,
            now()->subHour()
        );

        $order->update([
            'paid_at' => now()->subMinutes(30),
        ]);

        $this->artisan('tickets:expire-orders')
            ->assertSuccessful();

        $this->assertSame(
            TicketOrder::STATUS_PAID,
            $order->fresh()->status
        );
    }

    public function test_order_with_completed_payment_is_not_expired(): void
{
    $event = $this->createEvent();

    $order = $this->createOrder(
        $event,
        TicketOrder::STATUS_PROCESSING,
        now()->subMinute()
    );

    \App\Models\Payment::query()->create([
        'organization_id' =>
            $event->organization_id,

        'event_id' =>
            $event->id,

        'ticket_order_id' =>
            $order->id,

        'reference' =>
            'TEST-COMPLETED-' . uniqid(),

        'amount' =>
            $order->total,

        'currency' =>
            $order->currency,

        'status' =>
            \App\Models\Payment::STATUS_COMPLETED,

        'initiated_at' =>
            now()->subMinutes(5),

        'paid_at' =>
            now(),
    ]);

    $this->artisan(
        'tickets:expire-orders'
    )->assertSuccessful();

    $this->assertSame(
        TicketOrder::STATUS_PROCESSING,
        $order->fresh()->status
    );
}
}
