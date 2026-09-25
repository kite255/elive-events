<?php

namespace Tests\Feature\Tickets;

use App\Models\Event;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketOrderItem;
use App\Models\TicketType;
use App\Models\TicketUpgrade;
use App\Services\Tickets\TicketUpgradeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class TicketUpgradeServiceTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        $organization = Organization::query()->create([
            'name' => 'Upgrade Service Organization',
            'slug' => 'upgrade-service-' . uniqid(),
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Upgrade Service Event',
            'slug' => 'upgrade-service-event-' . uniqid(),
            'status' => Event::STATUS_ACTIVE,
        ]);

        $regular = TicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'Regular',
            'code' => 'REG',
            'price' => 30000,
            'currency' => 'TZS',
            'capacity' => 100,
            'min_per_order' => 1,
            'max_per_order' => 10,
            'is_active' => true,
            'is_public' => true,
        ]);

        $vip = TicketType::query()->create([
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

        $order = TicketOrder::query()->create([
            'event_id' => $event->id,
            'order_number' => 'ORD-' . uniqid(),
            'buyer_name' => 'Upgrade Buyer',
            'buyer_phone' => '255700000001',
            'buyer_email' => 'buyer@example.com',
            'quantity' => 1,
            'subtotal' => 30000,
            'discount_amount' => 0,
            'total' => 30000,
            'currency' => 'TZS',
            'status' => TicketOrder::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $item = TicketOrderItem::query()->create([
            'ticket_order_id' => $order->id,
            'ticket_type_id' => $regular->id,
            'quantity' => 1,
            'unit_price' => 30000,
            'subtotal' => 30000,
            'discount_amount' => 0,
            'total' => 30000,
        ]);

        $ticket = Ticket::query()->create([
            'event_id' => $event->id,
            'ticket_order_id' => $order->id,
            'ticket_order_item_id' => $item->id,
            'ticket_type_id' => $regular->id,
            'ticket_number' => 'TKT-' . uniqid(),
            'public_token' => str_repeat('b', 40),
            'qr_token_hash' => hash('sha256', uniqid('qr-', true)),
            'holder_name' => 'Upgrade Buyer',
            'price' => 30000,
            'currency' => 'TZS',
            'status' => Ticket::STATUS_ISSUED,
            'issued_at' => now(),
        ]);

        return compact('event', 'regular', 'vip', 'order', 'ticket');
    }

    public function test_creates_upgrade_from_server_side_price_difference(): void
    {
        ['ticket' => $ticket, 'vip' => $vip] = $this->scenario();

        $upgrade = app(TicketUpgradeService::class)->create($ticket, $vip);

        $this->assertSame(TicketUpgrade::STATUS_PENDING, $upgrade->status);
        $this->assertSame('30000.00', $upgrade->original_price);
        $this->assertSame('50000.00', $upgrade->target_price);
        $this->assertSame('20000.00', $upgrade->upgrade_amount);
        $this->assertSame('TZS', $upgrade->currency);
        $this->assertNotEmpty($upgrade->reference);
        $this->assertNotEmpty($upgrade->public_token);
    }

    public function test_used_ticket_cannot_be_upgraded(): void
    {
        ['ticket' => $ticket, 'vip' => $vip] = $this->scenario();
        $ticket->update(['status' => Ticket::STATUS_USED, 'used_at' => now()]);

        $this->expectException(RuntimeException::class);
        app(TicketUpgradeService::class)->create($ticket->fresh(), $vip);
    }

    public function test_unpaid_order_cannot_be_upgraded(): void
    {
        ['ticket' => $ticket, 'vip' => $vip, 'order' => $order] = $this->scenario();
        $order->update(['status' => TicketOrder::STATUS_PROCESSING, 'paid_at' => null]);

        $this->expectException(RuntimeException::class);
        app(TicketUpgradeService::class)->create($ticket, $vip);
    }

    public function test_lower_or_equal_priced_target_cannot_be_used(): void
    {
        ['ticket' => $ticket, 'regular' => $regular] = $this->scenario();

        $this->expectException(RuntimeException::class);
        app(TicketUpgradeService::class)->create($ticket, $regular);
    }

    public function test_sold_out_target_cannot_be_used(): void
    {
        ['ticket' => $ticket, 'vip' => $vip, 'event' => $event, 'order' => $order] = $this->scenario();
        $vip->update(['capacity' => 1]);

        Ticket::query()->create([
            'event_id' => $event->id,
            'ticket_order_id' => $order->id,
            'ticket_order_item_id' => $ticket->ticket_order_item_id,
            'ticket_type_id' => $vip->id,
            'ticket_number' => 'VIP-' . uniqid(),
            'public_token' => str_repeat('c', 40),
            'qr_token_hash' => hash('sha256', uniqid('vip-qr-', true)),
            'holder_name' => 'Existing VIP',
            'price' => 50000,
            'currency' => 'TZS',
            'status' => Ticket::STATUS_ISSUED,
            'issued_at' => now(),
        ]);

        $this->expectException(RuntimeException::class);
        app(TicketUpgradeService::class)->create($ticket, $vip->fresh());
    }

    public function test_only_one_unresolved_upgrade_is_created_for_a_ticket(): void
    {
        ['ticket' => $ticket, 'vip' => $vip] = $this->scenario();
        $service = app(TicketUpgradeService::class);

        $first = $service->create($ticket, $vip);
        $second = $service->create($ticket, $vip);

        $this->assertTrue($first->is($second));
        $this->assertSame(1, TicketUpgrade::query()->where('ticket_id', $ticket->id)->count());
    }
}
