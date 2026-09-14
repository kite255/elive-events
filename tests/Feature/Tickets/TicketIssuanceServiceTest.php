<?php

namespace Tests\Feature\Tickets;

use App\Models\Event;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketOrderItem;
use App\Models\TicketType;
use App\Services\Tickets\TicketIssuanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketIssuanceServiceTest extends TestCase
{
    use RefreshDatabase;

    private function createScenario(): array
    {
        $organization = Organization::query()->create([
            'name' => 'Ticket Issuance Organization',
            'slug' => 'ticket-issuance-' . uniqid(),
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'eLive Concert',
            'slug' => 'elive-concert-' . uniqid(),
            'status' => Event::STATUS_ACTIVE,
        ]);

        $ticketType = TicketType::query()->create([
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

        $order = TicketOrder::query()->create([
            'event_id' => $event->id,
            'order_number' => 'ORD-' . uniqid(),
            'buyer_name' => 'Lucas Buyer',
            'buyer_phone' => '255700000001',
            'buyer_email' => 'buyer@example.com',
            'quantity' => 2,
            'subtotal' => 100000,
            'discount_amount' => 0,
            'total' => 100000,
            'currency' => 'TZS',
            'status' => TicketOrder::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $item = TicketOrderItem::query()->create([
            'ticket_order_id' => $order->id,
            'ticket_type_id' => $ticketType->id,
            'quantity' => 2,
            'unit_price' => 50000,
            'subtotal' => 100000,
            'discount_amount' => 0,
            'total' => 100000,
        ]);

        return compact(
            'organization',
            'event',
            'ticketType',
            'order',
            'item'
        );
    }

    public function test_it_issues_correct_number_of_tickets(): void
    {
        [
            'order' => $order,
        ] = $this->createScenario();

        $tickets = app(TicketIssuanceService::class)
            ->issueForOrder($order);

        $this->assertCount(
            2,
            $tickets
        );

        $this->assertSame(
            2,
            Ticket::query()
                ->where(
                    'ticket_order_id',
                    $order->id
                )
                ->count()
        );
    }

    public function test_issued_tickets_have_secure_identifiers(): void
    {
        [
            'order' => $order,
        ] = $this->createScenario();

        $tickets = app(TicketIssuanceService::class)
            ->issueForOrder($order);

        foreach ($tickets as $ticket) {
            $this->assertNotEmpty(
                $ticket->ticket_number
            );

            $this->assertNotEmpty(
                $ticket->public_token
            );

            $this->assertNotEmpty(
                $ticket->getRawOriginal(
                    'qr_token_hash'
                )
            );

            $this->assertSame(
                Ticket::STATUS_ISSUED,
                $ticket->status
            );

            $this->assertNotNull(
                $ticket->issued_at
            );
        }
    }

    public function test_ticket_issuance_is_idempotent(): void
    {
        [
            'order' => $order,
        ] = $this->createScenario();

        $service =
            app(TicketIssuanceService::class);

        $first =
            $service->issueForOrder($order);

        $second =
            $service->issueForOrder(
                $order->fresh()
            );

        $this->assertCount(
            2,
            $first
        );

        $this->assertCount(
            2,
            $second
        );

        $this->assertSame(
            2,
            Ticket::query()
                ->where(
                    'ticket_order_id',
                    $order->id
                )
                ->count()
        );
    }
}
