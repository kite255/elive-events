<?php

namespace Tests\Feature\Tickets;

use App\Models\Event;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\TicketCheckIn;
use App\Models\TicketOrder;
use App\Models\TicketOrderItem;
use App\Models\TicketType;
use App\Services\Tickets\TicketCapacityMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketCapacityMetricsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_capacity_metrics_use_checkout_sold_reserved_and_expiration_rules(): void
    {
        [$event, $type] = $this->makeEventAndType(10);

        $soldOrder = $this->makeOrder($event, TicketOrder::STATUS_PAID, 2, null);
        $soldItem = $this->makeItem($soldOrder, $type, 2);

        foreach (range(1, 2) as $index) {
            Ticket::query()->create([
                'event_id' => $event->id,
                'ticket_order_id' => $soldOrder->id,
                'ticket_order_item_id' => $soldItem->id,
                'ticket_type_id' => $type->id,
                'ticket_number' => 'SOLD-' . $index . '-' . uniqid(),
                'public_token' => 'sold-' . uniqid(),
                'qr_token_hash' => hash('sha256', 'sold-' . uniqid()),
                'holder_name' => 'Sold Buyer',
                'price' => 10000,
                'currency' => 'TZS',
                'status' => Ticket::STATUS_ISSUED,
                'issued_at' => now(),
            ]);
        }

        $reservation = $this->makeOrder($event, TicketOrder::STATUS_PENDING, 3, now()->addMinutes(10));
        $this->makeItem($reservation, $type, 3);

        $expired = $this->makeOrder($event, TicketOrder::STATUS_PENDING, 4, now()->subMinute());
        $this->makeItem($expired, $type, 4);

        $ticket = Ticket::query()->where('ticket_order_id', $soldOrder->id)->firstOrFail();
        TicketCheckIn::query()->create([
            'event_id' => $event->id,
            'ticket_id' => $ticket->id,
            'method' => 'qr',
            'checked_in_at' => now(),
        ]);

        $metrics = app(TicketCapacityMetricsService::class)->forEvent($event);
        $row = $metrics['ticket_types'][0];

        $this->assertSame(10, $row['capacity']);
        $this->assertSame(2, $row['sold']);
        $this->assertSame(3, $row['reserved']);
        $this->assertSame(5, $row['available']);
        $this->assertSame(1, $row['checked_in']);
    }

    public function test_non_positive_capacity_is_unlimited(): void
    {
        [$event] = $this->makeEventAndType(0);

        $metrics = app(TicketCapacityMetricsService::class)->forEvent($event);
        $row = $metrics['ticket_types'][0];

        $this->assertNull($row['capacity']);
        $this->assertNull($row['available']);
        $this->assertNull($row['remaining']);
        $this->assertNull($metrics['summary']['capacity']);
        $this->assertNull($metrics['summary']['available']);
    }

    private function makeEventAndType(?int $capacity): array
    {
        $organization = Organization::query()->create([
            'name' => 'Capacity Organization',
            'slug' => 'capacity-org-' . uniqid(),
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Capacity Event',
            'slug' => 'capacity-event-' . uniqid(),
            'status' => Event::STATUS_ACTIVE,
        ]);

        $type = TicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'VIP',
            'code' => 'VIP',
            'price' => 10000,
            'currency' => 'TZS',
            'capacity' => $capacity,
            'min_per_order' => 1,
            'max_per_order' => 10,
            'is_active' => true,
            'is_public' => true,
        ]);

        return [$event, $type];
    }

    private function makeOrder(Event $event, string $status, int $quantity, $expiresAt): TicketOrder
    {
        return TicketOrder::query()->create([
            'event_id' => $event->id,
            'order_number' => 'ELV-CAP-' . strtoupper(uniqid()),
            'buyer_name' => 'Capacity Buyer',
            'buyer_phone' => '255700000001',
            'buyer_email' => 'capacity@example.com',
            'quantity' => $quantity,
            'subtotal' => 10000 * $quantity,
            'discount_amount' => 0,
            'total' => 10000 * $quantity,
            'currency' => 'TZS',
            'status' => $status,
            'paid_at' => $status === TicketOrder::STATUS_PAID ? now() : null,
            'expires_at' => $expiresAt,
        ]);
    }

    private function makeItem(TicketOrder $order, TicketType $type, int $quantity): TicketOrderItem
    {
        return TicketOrderItem::query()->create([
            'ticket_order_id' => $order->id,
            'ticket_type_id' => $type->id,
            'quantity' => $quantity,
            'unit_price' => 10000,
            'subtotal' => 10000 * $quantity,
            'discount_amount' => 0,
            'total' => 10000 * $quantity,
        ]);
    }
}
