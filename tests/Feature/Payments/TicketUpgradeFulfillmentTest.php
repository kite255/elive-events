<?php

namespace Tests\Feature\Payments;

use App\Models\Event;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketOrderItem;
use App\Models\TicketType;
use App\Models\TicketUpgrade;
use App\Services\Tickets\TicketUpgradeFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class TicketUpgradeFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    private function scenario(): array
    {
        $organization = Organization::query()->create([
            'name' => 'Upgrade Fulfillment Organization',
            'slug' => 'upgrade-fulfillment-' . uniqid(),
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Upgrade Fulfillment Event',
            'slug' => 'upgrade-fulfillment-event-' . uniqid(),
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
            'order_number' => 'FUL-UPG-' . uniqid(),
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
            'public_token' => str_repeat('d', 40),
            'qr_token_encrypted' => 'secret-upgrade-qr',
            'qr_token_hash' => hash('sha256', 'secret-upgrade-qr'),
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
            'reference' => 'ELV-UPG-' . uniqid(),
            'original_price' => 30000,
            'target_price' => 50000,
            'upgrade_amount' => 20000,
            'currency' => 'TZS',
            'status' => TicketUpgrade::STATUS_PROCESSING,
            'initiated_at' => now()->subMinutes(5),
            'expires_at' => now()->addHour(),
        ]);

        $payment = Payment::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'ticket_upgrade_id' => $upgrade->id,
            'reference' => 'PAY-UPG-' . uniqid(),
            'amount' => 20000,
            'currency' => 'TZS',
            'status' => Payment::STATUS_COMPLETED,
            'paid_at' => now(),
        ]);

        return compact('event', 'regular', 'vip', 'order', 'item', 'ticket', 'upgrade', 'payment');
    }

    public function test_completed_upgrade_changes_same_ticket_and_preserves_access_identity(): void
    {
        $data = $this->scenario();
        $ticket = $data['ticket'];
        $originalTicketNumber = $ticket->ticket_number;
        $originalPublicToken = $ticket->public_token;
        $originalQrHash = $ticket->qr_token_hash;

        app(TicketUpgradeFulfillmentService::class)->fulfill($data['payment']);

        $ticket->refresh();
        $data['upgrade']->refresh();
        $data['item']->refresh();

        $this->assertSame($data['vip']->id, $ticket->ticket_type_id);
        $this->assertSame('50000.00', $ticket->price);
        $this->assertSame($originalTicketNumber, $ticket->ticket_number);
        $this->assertSame($originalPublicToken, $ticket->public_token);
        $this->assertSame($originalQrHash, $ticket->qr_token_hash);
        $this->assertSame($data['regular']->id, $data['item']->ticket_type_id);
        $this->assertSame(TicketUpgrade::STATUS_COMPLETED, $data['upgrade']->status);
        $this->assertNotNull($data['upgrade']->completed_at);
    }

    public function test_fulfillment_is_idempotent(): void
    {
        $data = $this->scenario();
        $service = app(TicketUpgradeFulfillmentService::class);

        $service->fulfill($data['payment']);
        $service->fulfill($data['payment']->fresh());

        $this->assertSame($data['vip']->id, $data['ticket']->fresh()->ticket_type_id);
        $this->assertSame(1, TicketUpgrade::query()->whereKey($data['upgrade']->id)->count());
    }

    public function test_used_ticket_cannot_be_fulfilled(): void
    {
        $data = $this->scenario();
        $data['ticket']->update(['status' => Ticket::STATUS_USED, 'used_at' => now()]);

        $this->expectException(RuntimeException::class);
        app(TicketUpgradeFulfillmentService::class)->fulfill($data['payment']);
    }

    public function test_amount_mismatch_is_rejected(): void
    {
        $data = $this->scenario();
        $data['payment']->update(['amount' => 10000]);

        $this->expectException(RuntimeException::class);
        app(TicketUpgradeFulfillmentService::class)->fulfill($data['payment']->fresh());
    }

    public function test_capacity_is_rechecked_at_fulfillment(): void
    {
        $data = $this->scenario();
        $data['vip']->update(['capacity' => 1]);

        Ticket::query()->create([
            'event_id' => $data['event']->id,
            'ticket_order_id' => $data['order']->id,
            'ticket_order_item_id' => $data['item']->id,
            'ticket_type_id' => $data['vip']->id,
            'ticket_number' => 'EXISTING-VIP-' . uniqid(),
            'public_token' => str_repeat('e', 40),
            'qr_token_hash' => hash('sha256', uniqid('vip-', true)),
            'holder_name' => 'Existing VIP',
            'price' => 50000,
            'currency' => 'TZS',
            'status' => Ticket::STATUS_ISSUED,
            'issued_at' => now(),
        ]);

        $this->expectException(RuntimeException::class);
        app(TicketUpgradeFulfillmentService::class)->fulfill($data['payment']);

        $this->assertSame($data['regular']->id, $data['ticket']->fresh()->ticket_type_id);
    }
}
