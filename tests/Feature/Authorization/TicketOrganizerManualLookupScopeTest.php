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

    public function test_ticket_organizer_only_finds_tickets_from_own_organization(): void
    {
        $organizationA = Organization::query()->create([
            'name' => 'Organizer A',
            'email' => 'orga@example.com',
        ]);

        $organizationB = Organization::query()->create([
            'name' => 'Organizer B',
            'email' => 'orgb@example.com',
        ]);

        $organizer = User::query()->create([
            'name' => 'Ticket Organizer A',
            'email' => 'organizer-a@example.com',
            'password' => 'password',
            'is_super_admin' => false,
        ]);

        $organizer->organizations()->attach(
            $organizationA->id,
            [
                'role' => User::ORGANIZATION_ROLE_TICKET_ORGANIZER,
                'status' => User::ORGANIZATION_STATUS_ACTIVE,
                'is_owner' => false,
                'joined_at' => now(),
            ]
        );

        $eventA = Event::query()->create([
            'organization_id' => $organizationA->id,
            'name' => 'Event A',
            'venue' => 'Venue A',
            'starts_at' => now()->addDay(),
            'status' => Event::STATUS_ACTIVE,
            'registration_is_open' => true,
        ]);

        $eventB = Event::query()->create([
            'organization_id' => $organizationB->id,
            'name' => 'Event B',
            'venue' => 'Venue B',
            'starts_at' => now()->addDays(2),
            'status' => Event::STATUS_ACTIVE,
            'registration_is_open' => true,
        ]);

        $ticketTypeA = TicketType::query()->create([
            'event_id' => $eventA->id,
            'name' => 'General A',
            'code' => 'GEN-A',
            'price' => 50000,
            'currency' => 'TZS',
        ]);

        $ticketTypeB = TicketType::query()->create([
            'event_id' => $eventB->id,
            'name' => 'General B',
            'code' => 'GEN-B',
            'price' => 75000,
            'currency' => 'TZS',
        ]);

        $orderA = TicketOrder::query()->create([
            'event_id' => $eventA->id,
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

        $orderB = TicketOrder::query()->create([
            'event_id' => $eventB->id,
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

        $ticketA = Ticket::query()->create([
            'event_id' => $eventA->id,
            'ticket_order_id' => $orderA->id,
            'ticket_type_id' => $ticketTypeA->id,
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

        Ticket::query()->create([
            'event_id' => $eventB->id,
            'ticket_order_id' => $orderB->id,
            'ticket_type_id' => $ticketTypeB->id,
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

        $this->assertCount(
            1,
            $results
        );

        $this->assertSame(
            $ticketA->id,
            $results[0]['ticket_id']
        );

        $this->assertSame(
            $eventA->id,
            $results[0]['event_id']
        );
    }
}