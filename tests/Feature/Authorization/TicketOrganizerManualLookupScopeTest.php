<?php

namespace Tests\Feature\Authorization;

use App\Models\Event;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketType;
use App\Models\User;
use App\Services\Tickets\AdminTicketLookupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketOrganizerManualLookupScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticketing_manager_only_finds_tickets_from_assigned_events(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Organizer A',
            'email' => 'orga@example.com',
        ]);

        $organizer = User::query()->create([
            'name' => 'Ticket Manager A',
            'email' => 'organizer-a@example.com',
            'password' => 'password',
            'is_super_admin' => false,
        ]);

        $organizer->organizations()->attach(
            $organization->id,
            [
                'role' => User::ORGANIZATION_ROLE_TICKET_ORGANIZER,
                'status' => User::ORGANIZATION_STATUS_ACTIVE,
                'is_owner' => false,
                'joined_at' => now(),
            ]
        );

        $assignedEvent = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Assigned Event',
            'venue' => 'Venue A',
            'starts_at' => now()->addDay(),
            'status' => Event::STATUS_ACTIVE,
            'registration_is_open' => true,
        ]);

        $unassignedEvent = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Unassigned Event',
            'venue' => 'Venue B',
            'starts_at' => now()->addDays(2),
            'status' => Event::STATUS_ACTIVE,
            'registration_is_open' => true,
        ]);

        $organizer->assignToEvent(
            $assignedEvent,
            User::ORGANIZATION_ROLE_TICKET_ORGANIZER
        );

        $assignedTicketType = TicketType::query()->create([
            'event_id' => $assignedEvent->id,
            'name' => 'General Assigned',
            'code' => 'GEN-A',
            'price' => 50000,
            'currency' => 'TZS',
        ]);

        $unassignedTicketType = TicketType::query()->create([
            'event_id' => $unassignedEvent->id,
            'name' => 'General Unassigned',
            'code' => 'GEN-B',
            'price' => 75000,
            'currency' => 'TZS',
        ]);

        $assignedOrder = TicketOrder::query()->create([
            'event_id' => $assignedEvent->id,
            'order_number' => 'ORD-A-001',
            'buyer_name' => 'Shared Buyer',
            'buyer_phone' => '0711111111',
            'buyer_email' => 'shared@example.com',
            'quantity' => 1,
            'subtotal' => 50000,
            'discount_amount' => 0,
            'total' => 50000,
            'currency' => 'TZS',
            'status' => TicketOrder::STATUS_PAID,
        ]);

        $unassignedOrder = TicketOrder::query()->create([
            'event_id' => $unassignedEvent->id,
            'order_number' => 'ORD-B-001',
            'buyer_name' => 'Shared Buyer',
            'buyer_phone' => '0722222222',
            'buyer_email' => 'shared@example.com',
            'quantity' => 1,
            'subtotal' => 75000,
            'discount_amount' => 0,
            'total' => 75000,
            'currency' => 'TZS',
            'status' => TicketOrder::STATUS_PAID,
        ]);

        $assignedTicket = Ticket::query()->create([
            'event_id' => $assignedEvent->id,
            'ticket_order_id' => $assignedOrder->id,
            'ticket_type_id' => $assignedTicketType->id,
            'ticket_number' => 'TKT-A-001',
            'public_token' => 'public-token-a',
            'qr_token_hash' => hash(
                'sha256',
                'qr-token-a'
            ),
            'holder_name' => 'Shared Buyer',
            'holder_phone' => '0711111111',
            'holder_email' => 'shared@example.com',
            'price' => 50000,
            'currency' => 'TZS',
            'status' => Ticket::STATUS_ISSUED,
        ]);

        $unassignedTicket = Ticket::query()->create([
            'event_id' => $unassignedEvent->id,
            'ticket_order_id' => $unassignedOrder->id,
            'ticket_type_id' => $unassignedTicketType->id,
            'ticket_number' => 'TKT-B-001',
            'public_token' => 'public-token-b',
            'qr_token_hash' => hash(
                'sha256',
                'qr-token-b'
            ),
            'holder_name' => 'Shared Buyer',
            'holder_phone' => '0722222222',
            'holder_email' => 'shared@example.com',
            'price' => 75000,
            'currency' => 'TZS',
            'status' => Ticket::STATUS_ISSUED,
        ]);

        $results = app(
            AdminTicketLookupService::class
        )->search(
            $organizer,
            'shared@example.com'
        );

        $ticketIds = collect($results)
            ->pluck('ticket_id')
            ->all();

        $this->assertContains(
            $assignedTicket->id,
            $ticketIds
        );

        $this->assertNotContains(
            $unassignedTicket->id,
            $ticketIds
        );

        $this->assertCount(
            1,
            $results
        );

        $this->assertSame(
            $assignedEvent->id,
            $results[0]['event_id']
        );
    }
}
