<?php

namespace Tests\Feature\Tickets;

use App\Models\Event;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketType;
use App\Models\User;
use App\Services\Tickets\AdminTicketLookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTicketLookupServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_find_ticket_by_order_number(): void
    {
        [$event, $order, $ticket] = $this->makeTicketRecord();

        $user = User::factory()->create([
            'is_super_admin' => true,
        ]);

        $results = app(AdminTicketLookupService::class)
            ->search($user, $order->order_number);

        $this->assertCount(1, $results);
        $this->assertSame($ticket->ticket_number, $results[0]['ticket_number']);
        $this->assertSame($order->order_number, $results[0]['order_number']);
        $this->assertSame($event->name, $results[0]['event_name']);
    }

    public function test_super_admin_can_find_ticket_by_ticket_number(): void
    {
        [, , $ticket] = $this->makeTicketRecord();

        $user = User::factory()->create([
            'is_super_admin' => true,
        ]);

        $results = app(AdminTicketLookupService::class)
            ->search($user, $ticket->ticket_number);

        $this->assertCount(1, $results);
        $this->assertSame($ticket->ticket_number, $results[0]['ticket_number']);
    }

    public function test_super_admin_can_find_ticket_by_buyer_name_phone_and_email(): void
    {
        [, $order, $ticket] = $this->makeTicketRecord();

        $user = User::factory()->create([
            'is_super_admin' => true,
        ]);

        $service = app(AdminTicketLookupService::class);

        foreach ([
            $order->buyer_name,
            $order->buyer_phone,
            $order->buyer_email,
        ] as $term) {
            $results = $service->search($user, $term);

            $this->assertCount(1, $results);
            $this->assertSame(
                $ticket->ticket_number,
                $results[0]['ticket_number']
            );
        }
    }

    public function test_lookup_does_not_return_tickets_from_inaccessible_events(): void
    {
        [, $order] = $this->makeTicketRecord();

        $user = User::factory()->create([
            'is_super_admin' => false,
        ]);

        $results = app(AdminTicketLookupService::class)
            ->search($user, $order->order_number);

        $this->assertSame([], $results);
    }

    public function test_lookup_returns_expected_status_and_access_fields(): void
    {
        [$event, $order, $ticket, $ticketType] =
            $this->makeTicketRecord([
                'ticket_status' => Ticket::STATUS_USED,
                'used_at' => now()->subMinute(),
            ]);

        $user = User::factory()->create([
            'is_super_admin' => true,
        ]);

        $results = app(AdminTicketLookupService::class)
            ->search($user, $ticket->ticket_number);

        $this->assertCount(1, $results);

        $result = $results[0];

        $this->assertSame($event->id, $result['event_id']);
        $this->assertSame($event->name, $result['event_name']);
        $this->assertSame($ticketType->name, $result['ticket_type']);
        $this->assertSame($order->status, $result['order_status']);
        $this->assertSame($ticket->status, $result['ticket_status']);
        $this->assertSame($order->buyer_name, $result['buyer_name']);
        $this->assertSame($order->buyer_phone, $result['buyer_phone']);
        $this->assertSame($order->buyer_email, $result['buyer_email']);
        $this->assertSame(15000.0, $result['amount']);
        $this->assertSame('TZS', $result['currency']);
        $this->assertTrue($result['is_checked_in']);
        $this->assertNotNull($result['used_at']);
        $this->assertSame(
            route('public.ticket-orders.show', [
                'token' => $order->public_token,
            ]),
            $result['order_url']
        );
        $this->assertSame(
            route('public.tickets.show', [
                'token' => $ticket->public_token,
            ]),
            $result['ticket_url']
        );
    }

    public function test_blank_or_unmatched_lookup_returns_no_results(): void
    {
        $user = User::factory()->create([
            'is_super_admin' => true,
        ]);

        $service = app(AdminTicketLookupService::class);

        $this->assertSame([], $service->search($user, ''));
        $this->assertSame([], $service->search($user, '   '));
        $this->assertSame(
            [],
            $service->search($user, 'NOT-A-REAL-TICKET')
        );
    }

    private function makeTicketRecord(array $overrides = []): array
    {
        $organization = Organization::query()->create([
            'name' => 'Lookup Organization',
            'slug' => 'lookup-org-' . uniqid(),
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Lookup Event',
            'slug' => 'lookup-event-' . uniqid(),
            'status' => Event::STATUS_ACTIVE,
        ]);

        $ticketType = TicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'Regular',
            'code' => 'REG',
            'price' => 15000,
            'currency' => 'TZS',
            'capacity' => 100,
            'min_per_order' => 1,
            'max_per_order' => 10,
            'is_active' => true,
            'is_public' => true,
            'requires_holder_details' => false,
            'sort_order' => 1,
        ]);

        $order = TicketOrder::query()->create([
            'event_id' => $event->id,
            'order_number' => 'ELV-ORDER-' . strtoupper(uniqid()),
            'buyer_name' => 'Lucas Buyer',
            'buyer_phone' => '255700000001',
            'buyer_email' => 'lucas@example.com',
            'quantity' => 1,
            'subtotal' => 15000,
            'discount_amount' => 0,
            'total' => 15000,
            'currency' => 'TZS',
            'status' => TicketOrder::STATUS_PAID,
            'paid_at' => now(),
            'expires_at' => now()->addMinutes(20),
        ]);

        $ticket = Ticket::query()->create([
            'event_id' => $event->id,
            'ticket_order_id' => $order->id,
            'ticket_type_id' => $ticketType->id,
            'ticket_number' => 'ELV-TICKET-' . strtoupper(uniqid()),
            'public_token' => 'ticket-' . uniqid(),
            'qr_token_hash' => hash(
                'sha256',
                'lookup-qr-' . uniqid()
            ),
            'holder_name' => $order->buyer_name,
            'holder_phone' => $order->buyer_phone,
            'holder_email' => $order->buyer_email,
            'price' => 15000,
            'currency' => 'TZS',
            'status' => $overrides['ticket_status']
                ?? Ticket::STATUS_ISSUED,
            'issued_at' => now(),
            'used_at' => $overrides['used_at'] ?? null,
        ]);

        return [$event, $order, $ticket, $ticketType];
    }
}
