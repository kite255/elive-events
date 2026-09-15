<?php

namespace Tests\Feature\Authorization;

use App\Filament\Resources\TicketOrders\TicketOrderResource;
use App\Filament\Resources\TicketTypes\TicketTypeResource;
use App\Models\Event;
use App\Models\Organization;
use App\Models\TicketOrder;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketOrganizerTicketingDirectAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_organizer_can_directly_open_ticket_type_and_ticket_order_lists(): void
    {
        [$organizer] = $this->createTicketOrganizerContext();

        $this->actingAs($organizer);

        $this->get(
            TicketTypeResource::getUrl('index')
        )->assertOk();

        $this->get(
            TicketOrderResource::getUrl('index')
        )->assertOk();
    }

    public function test_ticket_organizer_cannot_directly_open_ticket_type_create_page(): void
    {
        [$organizer] = $this->createTicketOrganizerContext();

        $this->actingAs($organizer);

        $response = $this->get(
            TicketTypeResource::getUrl('create')
        );

        $this->assertContains(
            $response->getStatusCode(),
            [
                403,
                404,
            ],
            'Ticket Organizer must not directly access the Ticket Type create page.'
        );
    }

    public function test_ticket_organizer_cannot_directly_open_ticket_order_create_page(): void
    {
        [$organizer] = $this->createTicketOrganizerContext();

        $this->actingAs($organizer);

        $response = $this->get(
            TicketOrderResource::getUrl('create')
        );

        $this->assertContains(
            $response->getStatusCode(),
            [
                403,
                404,
            ],
            'Ticket Organizer must not directly access the Ticket Order create page.'
        );
    }

    public function test_ticket_organizer_cannot_directly_open_ticket_type_edit_page(): void
    {
        [
            $organizer,
            $organization,
        ] = $this->createTicketOrganizerContext();

        $event = $this->createEvent(
            $organization,
            'Organizer Concert'
        );

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
            'requires_holder_details' => false,
            'sort_order' => 1,
        ]);

        $this->actingAs($organizer);

        $response = $this->get(
            TicketTypeResource::getUrl(
                'edit',
                [
                    'record' => $ticketType,
                ]
            )
        );

        $this->assertContains(
            $response->getStatusCode(),
            [
                403,
                404,
            ],
            'Ticket Organizer must not directly access the Ticket Type edit page.'
        );
    }

    public function test_ticket_organizer_cannot_directly_open_ticket_order_edit_page(): void
    {
        [
            $organizer,
            $organization,
        ] = $this->createTicketOrganizerContext();

        $event = $this->createEvent(
            $organization,
            'Organizer Concert'
        );

        $ticketOrder = TicketOrder::query()->create([
            'event_id' => $event->id,
            'order_number' => 'ORD-TEST-001',
            'buyer_name' => 'Test Buyer',
            'buyer_phone' => '255700000001',
            'buyer_email' => 'buyer@example.com',
            'quantity' => 1,
            'subtotal' => 50000,
            'discount_amount' => 0,
            'total' => 50000,
            'gross_amount' => 50000,
            'platform_commission_rate' => 0,
            'platform_commission_amount' => 0,
            'gateway_fee_rate' => 0,
            'gateway_fee_amount' => 0,
            'total_charges' => 0,
            'organizer_net_amount' => 50000,
            'currency' => 'TZS',
            'status' => TicketOrder::STATUS_PENDING,
        ]);

        $this->actingAs($organizer);

        $response = $this->get(
            TicketOrderResource::getUrl(
                'edit',
                [
                    'record' => $ticketOrder,
                ]
            )
        );

        $this->assertContains(
            $response->getStatusCode(),
            [
                403,
                404,
            ],
            'Ticket Organizer must not directly access the Ticket Order edit page.'
        );
    }

    private function createTicketOrganizerContext(): array
    {
        $organization = Organization::query()->create([
            'name' => 'Ticket Events Ltd',
            'email' => 'ticket-events@example.com',
        ]);

        $organizer = User::query()->create([
            'name' => 'Ticket Organizer',
            'email' => 'ticket.organizer@example.com',
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

                'is_owner' => false,
                'joined_at' => now(),
            ]
        );

        return [
            $organizer,
            $organization,
        ];
    }

    private function createEvent(
        Organization $organization,
        string $name
    ): Event {
        return Event::query()->create([
            'organization_id' => $organization->id,
            'name' => $name,
            'status' => Event::STATUS_ACTIVE,
        ]);
    }
}