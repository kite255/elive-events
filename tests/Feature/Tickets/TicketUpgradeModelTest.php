<?php

namespace Tests\Feature\Tickets;

use App\Models\Event;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketOrderItem;
use App\Models\TicketType;
use App\Models\TicketUpgrade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketUpgradeModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_upgrade_generates_public_token_and_exposes_expected_relations(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Upgrade Model Organization',
            'slug' => 'upgrade-model-' . uniqid(),
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Upgrade Model Event',
            'slug' => 'upgrade-model-event-' . uniqid(),
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
            'capacity' => 50,
            'min_per_order' => 1,
            'max_per_order' => 10,
            'is_active' => true,
            'is_public' => true,
        ]);

        $order = TicketOrder::query()->create([
            'event_id' => $event->id,
            'order_number' => 'UPG-ORD-' . uniqid(),
            'buyer_name' => 'Upgrade Buyer',
            'buyer_phone' => '255700000001',
            'buyer_email' => 'upgrade@example.com',
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
            'public_token' => str_repeat('a', 40),
            'qr_token_hash' => hash('sha256', 'upgrade-test-token'),
            'holder_name' => 'Upgrade Buyer',
            'price' => 30000,
            'currency' => 'TZS',
            'status' => Ticket::STATUS_ISSUED,
            'issued_at' => now(),
        ]);

        $upgrade = TicketUpgrade::query()->create([
            'event_id' => $event->id,
            'ticket_order_id' => $order->id,
            'ticket_id' => $ticket->id,
            'from_ticket_type_id' => $regular->id,
            'to_ticket_type_id' => $vip->id,
            'reference' => 'ELV-UPG-TEST-' . uniqid(),
            'original_price' => 30000,
            'target_price' => 50000,
            'upgrade_amount' => 20000,
            'currency' => 'TZS',
            'status' => TicketUpgrade::STATUS_PENDING,
            'initiated_at' => now(),
            'expires_at' => now()->addHours(24),
        ]);

        $this->assertNotEmpty($upgrade->public_token);
        $this->assertSame(48, strlen($upgrade->public_token));
        $this->assertTrue($upgrade->ticket->is($ticket));
        $this->assertTrue($upgrade->order->is($order));
        $this->assertTrue($upgrade->fromTicketType->is($regular));
        $this->assertTrue($upgrade->toTicketType->is($vip));
        $this->assertTrue($ticket->upgrades->contains($upgrade));
        $this->assertTrue($order->ticketUpgrades->contains($upgrade));

        $payment = Payment::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'ticket_upgrade_id' => $upgrade->id,
            'reference' => 'UPG-PAY-' . uniqid(),
            'amount' => 20000,
            'currency' => 'TZS',
            'status' => Payment::STATUS_PENDING,
        ]);

        $this->assertTrue($payment->ticketUpgrade->is($upgrade));
        $this->assertTrue($upgrade->payments->contains($payment));
    }
}
