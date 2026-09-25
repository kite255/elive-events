<?php

namespace Tests\Feature\Tickets;

use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AllTicketsResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_query_can_see_all_tickets(): void
    {
        [, $ticketA] = $this->makeTicket('Alpha');
        [, $ticketB] = $this->makeTicket('Beta');
        $admin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($admin);

        $ids = TicketResource::getEloquentQuery()->pluck('tickets.id')->all();
        sort($ids);
        $expected = [$ticketA->id, $ticketB->id];
        sort($expected);

        $this->assertSame($expected, $ids);
    }

    public function test_ticket_organizer_query_is_limited_to_assigned_event(): void
    {
        [$assignedContext, $assignedTicket] = $this->makeTicket('Assigned');
        [, $otherTicket] = $this->makeTicket('Other');
        $organizer = User::factory()->create(['is_super_admin' => false]);
        [$organization, $event] = $assignedContext;

        $organization->users()->attach($organizer->id, [
            'role' => User::ORGANIZATION_ROLE_TICKET_ORGANIZER,
            'status' => User::ORGANIZATION_STATUS_ACTIVE,
            'is_owner' => false,
            'joined_at' => now(),
        ]);
        $event->assignUser($organizer, User::ORGANIZATION_ROLE_TICKET_ORGANIZER);

        $this->actingAs($organizer);

        $ids = TicketResource::getEloquentQuery()->pluck('tickets.id')->all();

        $this->assertSame([$assignedTicket->id], $ids);
        $this->assertNotContains($otherTicket->id, $ids);
    }

    public function test_ticket_model_hides_qr_credentials_from_serialization(): void
    {
        [, $ticket] = $this->makeTicket('Secrets');

        $array = $ticket->fresh()->toArray();

        $this->assertArrayNotHasKey('qr_token_hash', $array);
        $this->assertArrayNotHasKey('qr_token_encrypted', $array);
        $this->assertArrayHasKey('public_token', $array);
    }

    private function makeTicket(string $suffix): array
    {
        $organization = Organization::query()->create([
            'name' => "{$suffix} Organization",
            'slug' => strtolower($suffix) . '-org-' . uniqid(),
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => "{$suffix} Event",
            'slug' => strtolower($suffix) . '-event-' . uniqid(),
            'status' => Event::STATUS_ACTIVE,
        ]);

        $type = TicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'Regular',
            'code' => 'REG-' . strtoupper(substr($suffix, 0, 3)),
            'price' => 10000,
            'currency' => 'TZS',
            'capacity' => 100,
            'min_per_order' => 1,
            'max_per_order' => 10,
            'is_active' => true,
            'is_public' => true,
        ]);

        $order = TicketOrder::query()->create([
            'event_id' => $event->id,
            'order_number' => 'ELV-' . strtoupper($suffix) . '-' . strtoupper(uniqid()),
            'buyer_name' => "{$suffix} Buyer",
            'buyer_phone' => '255700000001',
            'buyer_email' => strtolower($suffix) . '@example.com',
            'quantity' => 1,
            'subtotal' => 10000,
            'discount_amount' => 0,
            'total' => 10000,
            'currency' => 'TZS',
            'status' => TicketOrder::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $ticket = Ticket::query()->create([
            'event_id' => $event->id,
            'ticket_order_id' => $order->id,
            'ticket_type_id' => $type->id,
            'ticket_number' => 'ELV-TKT-' . strtoupper(uniqid()),
            'public_token' => 'public-' . uniqid(),
            'qr_token_encrypted' => 'raw-secret-' . uniqid(),
            'qr_token_hash' => hash('sha256', 'raw-secret-' . uniqid()),
            'holder_name' => $order->buyer_name,
            'holder_phone' => $order->buyer_phone,
            'holder_email' => $order->buyer_email,
            'price' => 10000,
            'currency' => 'TZS',
            'status' => Ticket::STATUS_ISSUED,
            'issued_at' => now(),
        ]);

        return [[$organization, $event, $order, $type], $ticket];
    }
}
