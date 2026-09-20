<?php

namespace Tests\Unit\Tickets;

use App\Models\Event;
use App\Models\EventTicketSetting;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketType;
use App\Services\Tickets\Rendering\PublicTicketAccess;
use App\Services\Tickets\TicketIssuanceService;
use App\Services\Tickets\TicketOrderService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicTicketAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_an_eligible_ticket_with_required_relations(): void
    {
        $ticket = $this->createIssuedTicket();

        $resolved = app(PublicTicketAccess::class)
            ->findEligible($ticket->public_token);

        $this->assertTrue($resolved->relationLoaded('event'));
        $this->assertTrue($resolved->event->relationLoaded('organization'));
        $this->assertTrue($resolved->relationLoaded('ticketType'));
        $this->assertTrue($resolved->relationLoaded('order'));
        $this->assertSame($ticket->id, $resolved->id);
    }

    public function test_it_rejects_an_unknown_public_token(): void
    {
        $this->expectException(ModelNotFoundException::class);

        app(PublicTicketAccess::class)
            ->findEligible(str_repeat('x', 48));
    }

    public function test_it_rejects_a_ticket_with_an_unpaid_order(): void
    {
        $ticket = $this->createIssuedTicket();

        $ticket->order->update([
            'status' => TicketOrder::STATUS_PENDING,
            'paid_at' => null,
        ]);

        $this->expectException(ModelNotFoundException::class);

        app(PublicTicketAccess::class)
            ->findEligible($ticket->public_token);
    }

    public function test_it_rejects_a_cancelled_ticket(): void
    {
        $ticket = $this->createIssuedTicket();

        $ticket->update([
            'status' => Ticket::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        $this->expectException(ModelNotFoundException::class);

        app(PublicTicketAccess::class)
            ->findEligible($ticket->public_token);
    }

    public function test_it_rejects_a_refunded_ticket(): void
    {
        $ticket = $this->createIssuedTicket();

        $ticket->update([
            'status' => Ticket::STATUS_REFUNDED,
            'refunded_at' => now(),
        ]);

        $this->expectException(ModelNotFoundException::class);

        app(PublicTicketAccess::class)
            ->findEligible($ticket->public_token);
    }

    public function test_it_rejects_a_corrupted_encrypted_qr_credential(): void
    {
        $ticket = $this->createIssuedTicket();

        DB::table('tickets')
            ->where('id', $ticket->id)
            ->update([
                'qr_token_encrypted' => 'not-a-valid-encrypted-value',
            ]);

        $this->expectException(ModelNotFoundException::class);

        app(PublicTicketAccess::class)
            ->findEligible($ticket->public_token);
    }

    private function createIssuedTicket(): Ticket
    {
        $organization = Organization::query()->create([
            'name' => 'Renderer Access Organizer',
            'slug' => 'renderer-access-organizer-' . uniqid(),
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Renderer Access Event',
            'slug' => 'renderer-access-event-' . uniqid(),
            'status' => Event::STATUS_ACTIVE,
            'starts_at' => now()->addDays(3),
        ]);

        EventTicketSetting::query()->create([
            'event_id' => $event->id,
            'ticket_sales_enabled' => true,
            'reservation_minutes' => 15,
            'max_tickets_per_order' => 10,
            'allow_guest_checkout' => true,
        ]);

        $ticketType = TicketType::query()->create([
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
        ]);

        $order = app(TicketOrderService::class)->createOrder(
            event: $event,
            buyer: [
                'name' => 'Renderer Buyer',
                'phone' => '255700000001',
                'email' => 'renderer@example.com',
            ],
            items: [[
                'ticket_type_id' => $ticketType->id,
                'quantity' => 1,
            ]],
        );

        $order->update([
            'status' => TicketOrder::STATUS_PAID,
            'paid_at' => now(),
        ]);

        return app(TicketIssuanceService::class)
            ->issueForOrder($order->fresh())
            ->firstOrFail();
    }
}
