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

    public function test_ticketing_manager_only_sees_ticket_orders_from_assigned_events(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Organizer A',
            'email' => 'orders@example.com',
        ]);

        $organizer = User::query()->create([
            'name' => 'Ticket Manager A',
            'email' => 'order.manager@example.com',
            'password' => 'password',
            'is_super_admin' => false,
        ]);

        $organizer->organizations()->attach(
            $organization->id,
            [
                'role' =>
                    User::ORGANIZATION_ROLE_TICKET_ORGANIZER,

                'status' =>
                    User::ORGANIZATION_STATUS_ACTIVE,

                'is_owner' =>
                    false,

                'joined_at' =>
                    now(),
            ]
        );

        $assignedEvent = Event::query()->create([
            'organization_id' =>
                $organization->id,

            'name' =>
                'Assigned Concert',

            'venue' =>
                'Hall A',

            'starts_at' =>
                now()->addDay(),

            'status' =>
                Event::STATUS_ACTIVE,

            'registration_is_open' =>
                true,
        ]);

        $unassignedEvent = Event::query()->create([
            'organization_id' =>
                $organization->id,

            'name' =>
                'Unassigned Concert',

            'venue' =>
                'Hall B',

            'starts_at' =>
                now()->addDays(2),

            'status' =>
                Event::STATUS_ACTIVE,

            'registration_is_open' =>
                true,
        ]);

        $organizer->assignToEvent(
            $assignedEvent,
            User::ORGANIZATION_ROLE_TICKET_ORGANIZER
        );

        $assignedOrder = TicketOrder::query()->create([
            'event_id' =>
                $assignedEvent->id,

            'order_number' =>
                'ORD-ASSIGNED-001',

            'buyer_name' =>
                'Assigned Buyer',

            'buyer_phone' =>
                '0711000001',

            'quantity' =>
                1,

            'subtotal' =>
                50000,

            'discount_amount' =>
                0,

            'total' =>
                50000,

            'currency' =>
                'TZS',

            'status' =>
                TicketOrder::STATUS_PAID,

            'paid_at' =>
                now(),
        ]);

        $unassignedOrder = TicketOrder::query()->create([
            'event_id' =>
                $unassignedEvent->id,

            'order_number' =>
                'ORD-UNASSIGNED-001',

            'buyer_name' =>
                'Unassigned Buyer',

            'buyer_phone' =>
                '0711000002',

            'quantity' =>
                1,

            'subtotal' =>
                30000,

            'discount_amount' =>
                0,

            'total' =>
                30000,

            'currency' =>
                'TZS',

            'status' =>
                TicketOrder::STATUS_PAID,

            'paid_at' =>
                now(),
        ]);

        $this->actingAs($organizer);

        $visibleIds = TicketOrderResource::getEloquentQuery()
            ->pluck('ticket_orders.id')
            ->all();

        $this->assertContains(
            $assignedOrder->id,
            $visibleIds
        );

        $this->assertNotContains(
            $unassignedOrder->id,
            $visibleIds
        );

        $this->assertCount(
            1,
            $visibleIds
        );
    }
}