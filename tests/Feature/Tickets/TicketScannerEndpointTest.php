<?php

namespace Tests\Feature\Tickets;

use App\Models\Event;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketOrderItem;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TicketScannerEndpointTest extends TestCase
{
    use RefreshDatabase;

    private function createIssuedTicket(): array
    {
        $organization = Organization::query()->create([
            'name' => 'Ticket Scanner Organization',
            'slug' => 'ticket-scanner-' . uniqid(),
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Ticket Scanner Concert',
            'slug' => 'ticket-scanner-concert-' . uniqid(),
            'status' => Event::STATUS_ACTIVE,
        ]);

        $ticketType = TicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'Regular',
            'code' => 'REG',
            'price' => 1000,
            'currency' => 'TZS',
            'capacity' => 100,
            'min_per_order' => 1,
            'max_per_order' => 10,
            'is_active' => true,
            'is_public' => true,
        ]);

        $order = TicketOrder::query()->create([
            'event_id' => $event->id,
            'order_number' => 'ORD-SCANNER-' . uniqid(),
            'buyer_name' => 'Scanner Buyer',
            'buyer_phone' => '255700000002',
            'buyer_email' => 'scanner@example.com',
            'quantity' => 1,
            'subtotal' => 1000,
            'discount_amount' => 0,
            'total' => 1000,
            'currency' => 'TZS',
            'status' => TicketOrder::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $item = TicketOrderItem::query()->create([
            'ticket_order_id' => $order->id,
            'ticket_type_id' => $ticketType->id,
            'quantity' => 1,
            'unit_price' => 1000,
            'subtotal' => 1000,
            'discount_amount' => 0,
            'total' => 1000,
        ]);

        $rawQrToken = Str::random(64);

        $ticket = Ticket::query()->create([
            'event_id' => $event->id,
            'ticket_order_id' => $order->id,
            'ticket_order_item_id' => $item->id,
            'ticket_type_id' => $ticketType->id,
            'ticket_number' => 'ELV-REG-' . Str::upper(Str::random(8)),
            'public_token' => Str::random(40),
            'qr_token_encrypted' => $rawQrToken,
            'qr_token_hash' => hash(
                'sha256',
                $rawQrToken
            ),
            'holder_name' => 'Scanner Buyer',
            'holder_phone' => '255700000002',
            'holder_email' => 'scanner@example.com',
            'price' => 1000,
            'currency' => 'TZS',
            'status' => Ticket::STATUS_ISSUED,
            'issued_at' => now(),
        ]);

        return compact(
            'organization',
            'event',
            'ticketType',
            'order',
            'item',
            'ticket',
            'rawQrToken'
        );
    }

    private function createScannerUser(): User
    {
        return User::factory()->create([
            'is_super_admin' => true,
        ]);
    }

    public function test_authenticated_staff_can_scan_valid_ticket_qr(): void
    {
        $user = $this->createScannerUser();

        [
            'ticket' => $ticket,
            'rawQrToken' => $rawQrToken,
        ] = $this->createIssuedTicket();

        $response = $this
            ->actingAs($user)
            ->postJson(
                '/admin/ticket-scanner/scan',
                [
                    'qr_token' => $rawQrToken,
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'status',
                'checked_in'
            )
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'ticket.ticket_number',
                $ticket->ticket_number
            );
    }

    public function test_duplicate_ticket_scan_returns_already_used_without_second_entry(): void
    {
        $user = $this->createScannerUser();

        [
            'ticket' => $ticket,
            'rawQrToken' => $rawQrToken,
        ] = $this->createIssuedTicket();

        $this
            ->actingAs($user)
            ->postJson(
                '/admin/ticket-scanner/scan',
                [
                    'qr_token' => $rawQrToken,
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'status',
                'checked_in'
            );

        $response = $this
            ->actingAs($user)
            ->postJson(
                '/admin/ticket-scanner/scan',
                [
                    'qr_token' => $rawQrToken,
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'status',
                'already_used'
            )
            ->assertJsonPath(
                'success',
                false
            );

        $this->assertDatabaseCount(
            'ticket_check_ins',
            1
        );

        $ticket->refresh();

        $this->assertSame(
            Ticket::STATUS_USED,
            $ticket->status
        );
    }

    public function test_ordinary_authenticated_user_cannot_scan_ticket(): void
    {
        $user = User::factory()->create([
            'is_super_admin' => false,
        ]);

        [
            'ticket' => $ticket,
            'rawQrToken' => $rawQrToken,
        ] = $this->createIssuedTicket();

        $response = $this
            ->actingAs($user)
            ->postJson(
                '/admin/ticket-scanner/scan',
                [
                    'qr_token' => $rawQrToken,
                ]
            );

        $response->assertForbidden();

        $ticket->refresh();

        $this->assertSame(
            Ticket::STATUS_ISSUED,
            $ticket->status
        );

        $this->assertDatabaseCount(
            'ticket_check_ins',
            0
        );
    }
    public function test_invalid_qr_returns_ticket_not_found_without_creating_entry(): void
    {
        $user = $this->createScannerUser();

        $response = $this
            ->actingAs($user)
            ->postJson(
                '/admin/ticket-scanner/scan',
                [
                    'qr_token' => 'invalid-ticket-credential',
                ]
            );

        $response
            ->assertOk()
            ->assertJsonPath(
                'status',
                'ticket_not_found'
            )
            ->assertJsonPath(
                'success',
                false
            );

        $this->assertDatabaseCount(
            'ticket_check_ins',
            0
        );
    }

    public function test_unauthenticated_user_cannot_use_ticket_scanner_endpoint(): void
    {
        [
            'ticket' => $ticket,
            'rawQrToken' => $rawQrToken,
        ] = $this->createIssuedTicket();

        $response = $this->postJson(
            '/admin/ticket-scanner/scan',
            [
                'qr_token' => $rawQrToken,
            ]
        );

        $response->assertUnauthorized();

        $ticket->refresh();

        $this->assertSame(
            Ticket::STATUS_ISSUED,
            $ticket->status
        );

        $this->assertDatabaseCount(
            'ticket_check_ins',
            0
        );
    }

    public function test_authenticated_staff_can_check_in_using_ticket_number(): void
{
    $user = $this->createScannerUser();

    [
        'ticket' => $ticket,
    ] = $this->createIssuedTicket();

    $response = $this
        ->actingAs($user)
        ->postJson(
            '/admin/ticket-scanner/scan',
            [
                'qr_token' => $ticket->ticket_number,
            ]
        );

    $response
        ->assertOk()
        ->assertJsonPath(
            'status',
            'checked_in'
        )
        ->assertJsonPath(
            'success',
            true
        )
        ->assertJsonPath(
            'ticket.ticket_number',
            $ticket->ticket_number
        );

    $ticket->refresh();

    $this->assertSame(
        Ticket::STATUS_USED,
        $ticket->status
    );

    $this->assertNotNull(
        $ticket->used_at
    );

    $this->assertDatabaseHas(
        'ticket_check_ins',
        [
            'ticket_id' => $ticket->id,
            'event_id' => $ticket->event_id,
        ]
    );
}

public function test_duplicate_ticket_number_check_in_is_rejected_without_second_entry(): void
{
    $user = $this->createScannerUser();

    [
        'ticket' => $ticket,
    ] = $this->createIssuedTicket();

    $this
        ->actingAs($user)
        ->postJson(
            '/admin/ticket-scanner/scan',
            [
                'qr_token' => $ticket->ticket_number,
            ]
        )
        ->assertOk()
        ->assertJsonPath(
            'status',
            'checked_in'
        );

    $response = $this
        ->actingAs($user)
        ->postJson(
            '/admin/ticket-scanner/scan',
            [
                'qr_token' => $ticket->ticket_number,
            ]
        );

    $response
        ->assertOk()
        ->assertJsonPath(
            'status',
            'already_used'
        )
        ->assertJsonPath(
            'success',
            false
        );

    $this->assertDatabaseCount(
        'ticket_check_ins',
        1
    );
}

public function test_invalid_ticket_number_returns_ticket_not_found_without_creating_entry(): void
{
    $user = $this->createScannerUser();

    $response = $this
        ->actingAs($user)
        ->postJson(
            '/admin/ticket-scanner/scan',
            [
                'qr_token' =>
                    'ELV-REG-INVALID-TICKET',
            ]
        );

    $response
        ->assertOk()
        ->assertJsonPath(
            'status',
            'ticket_not_found'
        )
        ->assertJsonPath(
            'success',
            false
        );

    $this->assertDatabaseCount(
        'ticket_check_ins',
        0
    );
}

}
