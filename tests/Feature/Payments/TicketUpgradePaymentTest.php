<?php

namespace Tests\Feature\Payments;

use App\Models\Event;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketOrderItem;
use App\Models\TicketType;
use App\Models\TicketUpgrade;
use App\Services\Payments\TicketUpgradePaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketUpgradePaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_payment_for_exact_upgrade_balance_without_linking_original_order_as_payment_owner(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Upgrade Payment Organization',
            'slug' => 'upgrade-payment-' . uniqid(),
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Upgrade Payment Event',
            'slug' => 'upgrade-payment-event-' . uniqid(),
            'status' => Event::STATUS_ACTIVE,
        ]);

        PaymentGateway::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Pesapal',
            'code' => 'pesapal',
            'is_enabled' => true,
            'is_default' => true,
        ]);

        $regular = TicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'Regular', 'code' => 'REG', 'price' => 30000, 'currency' => 'TZS',
            'capacity' => 100, 'min_per_order' => 1, 'max_per_order' => 10,
            'is_active' => true, 'is_public' => true,
        ]);

        $vip = TicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'VIP', 'code' => 'VIP', 'price' => 50000, 'currency' => 'TZS',
            'capacity' => 50, 'min_per_order' => 1, 'max_per_order' => 10,
            'is_active' => true, 'is_public' => true,
        ]);

        $order = TicketOrder::query()->create([
            'event_id' => $event->id,
            'order_number' => 'PAY-UPG-ORD-' . uniqid(),
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
            'ticket_number' => 'PAY-UPG-TKT-' . uniqid(),
            'public_token' => str_repeat('g', 40),
            'qr_token_hash' => hash('sha256', uniqid('qr-', true)),
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
            'reference' => 'ELV-UPG-PAY-' . uniqid(),
            'original_price' => 30000,
            'target_price' => 50000,
            'upgrade_amount' => 20000,
            'currency' => 'TZS',
            'status' => TicketUpgrade::STATUS_PENDING,
            'initiated_at' => now(),
            'expires_at' => now()->addHour(),
        ]);

        $service = app(TicketUpgradePaymentService::class);
        $payment = $service->createForTicketUpgrade($upgrade);
        $second = $service->createForTicketUpgrade($upgrade);

        $this->assertSame($payment->id, $second->id);
        $this->assertSame($upgrade->id, $payment->ticket_upgrade_id);
        $this->assertNull($payment->ticket_order_id);
        $this->assertSame('20000.00', $payment->amount);
        $this->assertSame('TZS', $payment->currency);
        $this->assertSame('ticket_upgrade', data_get($payment->metadata, 'payment_purpose'));
        $this->assertSame(1, Payment::query()->where('ticket_upgrade_id', $upgrade->id)->count());
    }
}
