<?php

namespace Tests\Feature\Authorization;

use App\Filament\Resources\TicketOrders\TicketOrderResource;
use App\Models\Event;
use App\Models\Organization;
use App\Models\TicketOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketOrganizerTicketOrderScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_organizer_only_sees_ticket_orders_from_own_organization(): void
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
            'starts_at' => now()->addDay(),
            'status' => Event::STATUS_ACTIVE,
            'registration_is_open' => true,
        ]);

        $orderA = TicketOrder::query()->create([
            'event_id' => $eventA->id,
            'order_number' => 'ORD-A-001',
            'buyer_name' => 'Buyer A',
            'buyer_phone' => '0711111111',
            'buyer_email' => 'buyer-a@example.com',
            'quantity' => 1,
            'subtotal' => 50000,
            'discount_amount' => 0,
            'total' => 50000,
            'currency' => 'TZS',
            'status' => TicketOrder::STATUS_PENDING,
        ]);

        TicketOrder::query()->create([
            'event_id' => $eventB->id,
            'order_number' => 'ORD-B-001',
            'buyer_name' => 'Buyer B',
            'buyer_phone' => '0722222222',
            'buyer_email' => 'buyer-b@example.com',
            'quantity' => 1,
            'subtotal' => 75000,
            'discount_amount' => 0,
            'total' => 75000,
            'currency' => 'TZS',
            'status' => TicketOrder::STATUS_PENDING,
        ]);

        $this->actingAs($organizer);

        $visibleIds = TicketOrderResource::getEloquentQuery()
            ->pluck('ticket_orders.id')
            ->all();

        $this->assertSame(
            [$orderA->id],
            $visibleIds
        );
    }
}