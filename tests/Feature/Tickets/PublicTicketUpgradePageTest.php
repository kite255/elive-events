<?php

namespace Tests\Feature\Tickets;

use App\Models\Event;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketOrderItem;
use App\Models\TicketType;
use App\Models\TicketUpgrade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicTicketUpgradePageTest extends TestCase
{
    use RefreshDatabase;

    private function upgrade(): TicketUpgrade
    {
        $organization = Organization::query()->create([
            'name' => 'Public Upgrade Organization',
            'slug' => 'public-upgrade-' . uniqid(),
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Public Upgrade Event',
            'slug' => 'public-upgrade-event-' . uniqid(),
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
            'capacity' => 20,
            'min_per_order' => 1,
            'max_per_order' => 10,
            'is_active' => true,
            'is_public' => true,
        ]);

        $order = TicketOrder::query()->create([
            'event_id' => $event->id,
            'order_number' => 'PUB-UPG-' . uniqid(),
            'buyer_name' => 'Jane Customer',
            'buyer_phone' => '255712345678',
            'buyer_email' => 'jane.customer@example.com',
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
            'ticket_number' => 'PUB-TKT-' . uniqid(),
            'public_token' => str_repeat('f', 40),
            'qr_token_hash' => hash('sha256', uniqid('public-qr-', true)),
            'holder_name' => 'Jane Customer',
            'price' => 30000,
            'currency' => 'TZS',
            'status' => Ticket::STATUS_ISSUED,
            'issued_at' => now(),
        ]);

        return TicketUpgrade::query()->create([
            'event_id' => $event->id,
            'ticket_order_id' => $order->id,
            'ticket_id' => $ticket->id,
            'from_ticket_type_id' => $regular->id,
            'to_ticket_type_id' => $vip->id,
            'reference' => 'ELV-UPG-PUB-' . uniqid(),
            'original_price' => 30000,
            'target_price' => 50000,
            'upgrade_amount' => 20000,
            'currency' => 'TZS',
            'status' => TicketUpgrade::STATUS_PENDING,
            'initiated_at' => now(),
            'expires_at' => now()->addHour(),
        ]);
    }

    public function test_upgrade_page_uses_opaque_token_and_masks_contact_details(): void
    {
        $upgrade = $this->upgrade();

        $response = $this->get(route('tickets.upgrades.show', $upgrade->public_token));

        $response->assertOk()
            ->assertSee('Regular')
            ->assertSee('VIP')
            ->assertSee('20,000')
            ->assertDontSee('jane.customer@example.com')
            ->assertDontSee('255712345678')
            ->assertSee(
                route(
                    'tickets.upgrades.pay',
                    ['token' => $upgrade->public_token]
                ),
                false
            )
            ->assertDontSee('ticket_id=')
            ->assertDontSee('ticket_order_id=');
    }

    public function test_invalid_upgrade_token_returns_404(): void
    {
        $this->get('/upgrade/not-a-real-secure-token')->assertNotFound();
    }
}
