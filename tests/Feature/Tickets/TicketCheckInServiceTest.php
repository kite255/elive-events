<?php

namespace Tests\Feature\Tickets;

use App\Models\Event;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketOrderItem;
use App\Models\TicketType;
use App\Services\Tickets\TicketCheckInService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class TicketCheckInServiceTest extends TestCase
{
    use RefreshDatabase;

    private function createIssuedTicket(): array
    {
        $organization = Organization::query()->create([
            'name' => 'Ticket Check-In Organization',
            'slug' => 'ticket-check-in-' . uniqid(),
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Ticket Check-In Concert',
            'slug' => 'ticket-check-in-concert-' . uniqid(),
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
            'order_number' => 'ORD-CHECKIN-' . uniqid(),
            'buyer_name' => 'Ticket Buyer',
            'buyer_phone' => '255700000001',
            'buyer_email' => 'buyer@example.com',
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
            'holder_name' => 'Ticket Buyer',
            'holder_phone' => '255700000001',
            'holder_email' => 'buyer@example.com',
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

    public function test_ticket_check_ins_storage_exists_for_ticket_entry_records(): void
    {
        $this->assertTrue(
            Schema::hasTable('ticket_check_ins'),
            'Expected the ticket_check_ins table to exist.'
        );

        $this->assertTrue(
            Schema::hasColumns(
                'ticket_check_ins',
                [
                    'id',
                    'event_id',
                    'ticket_id',
                    'check_in_point_id',
                    'checked_in_by',
                    'method',
                    'checked_in_at',
                    'device_name',
                    'ip_address',
                    'note',
                    'created_at',
                    'updated_at',
                ]
            ),
            'Expected ticket_check_ins to contain the required ticket entry fields.'
        );
    }

    public function test_ticket_check_in_service_exists_before_qr_behavior_is_added(): void
    {
        $this->assertTrue(
            class_exists(TicketCheckInService::class),
            'Expected TicketCheckInService to exist.'
        );
    }

    public function test_ticket_check_in_service_exposes_qr_check_in_operation(): void
    {
        $service = app(TicketCheckInService::class);

        $this->assertTrue(
            method_exists(
                $service,
                'checkInByQrToken'
            ),
            'Expected TicketCheckInService::checkInByQrToken() to exist.'
        );
    }

    public function test_valid_qr_token_checks_in_issued_ticket(): void
    {
        [
            'ticket' => $ticket,
            'rawQrToken' => $rawQrToken,
        ] = $this->createIssuedTicket();

        $result = app(TicketCheckInService::class)
            ->checkInByQrToken(
                $rawQrToken
            );

        $this->assertSame(
            'checked_in',
            $result['status'] ?? null
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
                'event_id' => $ticket->event_id,
                'ticket_id' => $ticket->id,
                'method' => 'qr',
            ]
        );
    }

    public function test_second_scan_is_rejected_and_does_not_create_duplicate_check_in(): void
    {
        [
            'ticket' => $ticket,
            'rawQrToken' => $rawQrToken,
        ] = $this->createIssuedTicket();

        $service = app(TicketCheckInService::class);

        $firstResult = $service->checkInByQrToken(
            $rawQrToken
        );

        $secondResult = $service->checkInByQrToken(
            $rawQrToken
        );

        $this->assertSame(
            'checked_in',
            $firstResult['status'] ?? null
        );

        $this->assertSame(
            'already_used',
            $secondResult['status'] ?? null
        );

        $this->assertFalse(
            $secondResult['success'] ?? true
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

        $this->assertNotNull(
            $ticket->used_at
        );
    }


    public function test_ticket_number_checks_in_issued_ticket(): void
{
    [
        'ticket' => $ticket,
    ] = $this->createIssuedTicket();

    $result = app(
        TicketCheckInService::class
    )->checkInByCredential(
        $ticket->ticket_number
    );

    $this->assertSame(
        'checked_in',
        $result['status'] ?? null
    );

    $this->assertTrue(
        $result['success'] ?? false
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
            'ticket_id' =>
                $ticket->id,

            'event_id' =>
                $ticket->event_id,
        ]
    );
}

public function test_duplicate_ticket_number_is_rejected_without_second_check_in(): void
{
    [
        'ticket' => $ticket,
    ] = $this->createIssuedTicket();

    $service = app(
        TicketCheckInService::class
    );

    $firstResult =
        $service->checkInByCredential(
            $ticket->ticket_number
        );

    $secondResult =
        $service->checkInByCredential(
            $ticket->ticket_number
        );

    $this->assertSame(
        'checked_in',
        $firstResult['status'] ?? null
    );

    $this->assertSame(
        'already_used',
        $secondResult['status'] ?? null
    );

    $this->assertFalse(
        $secondResult['success'] ?? true
    );

    $this->assertDatabaseCount(
        'ticket_check_ins',
        1
    );
}
}
