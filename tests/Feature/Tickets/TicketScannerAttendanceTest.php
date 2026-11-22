<?php

namespace Tests\Feature\Tickets;

use App\Filament\Pages\TicketScanner;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketOrderItem;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class TicketScannerAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_scanner_lists_pending_and_checked_in_tickets_separately(): void
    {
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['is_super_admin' => true]));
        ['event' => $event, 'ticket' => $ticket] = $this->createIssuedTicket();
        ['ticket' => $anotherTicket] = $this->createIssuedTicket();

        $scanner = Livewire::test(TicketScanner::class)
            ->assertSee('Checked In')
            ->assertSee('Pending')
            ->call('showAttendance', 'pending')
            ->set('attendanceEventId', (string) $event->id)
            ->assertSee($ticket->ticket_number)
            ->assertDontSee($anotherTicket->ticket_number)
            ->set('attendanceSearch', 'no matching ticket')
            ->assertDontSee($ticket->ticket_number)
            ->set('attendanceSearch', 'Ticket Buyer')
            ->assertSee($ticket->ticket_number)
            ->call('showAttendance', 'checked_in')
            ->assertDontSee($ticket->ticket_number);

        $ticket->update(['status' => Ticket::STATUS_USED, 'used_at' => now()]);

        $scanner->call('$refresh')
            ->assertSee($ticket->ticket_number)
            ->call('showAttendance', 'pending')
            ->assertDontSee($ticket->ticket_number);

        $ticket->update(['status' => Ticket::STATUS_CANCELLED, 'used_at' => null]);

        $scanner->call('$refresh')->assertDontSee($ticket->ticket_number);
    }

    public function test_gate_staff_can_only_list_tickets_for_an_authorized_event(): void
    {
        $this->withoutVite();
        ['organization' => $organization, 'event' => $event, 'ticket' => $ticket] = $this->createIssuedTicket();
        ['event' => $otherEvent, 'ticket' => $otherTicket] = $this->createIssuedTicket();

        $otherEvent->update(['name' => 'Unassigned concert']);
        $officer = User::factory()->create();
        $officer->organizations()->attach($organization->id, [
            'role' => User::ORGANIZATION_ROLE_CHECK_IN_OFFICER,
            'status' => User::ORGANIZATION_STATUS_ACTIVE,
        ]);
        $event->users()->attach($officer->id, [
            'role' => User::ORGANIZATION_ROLE_CHECK_IN_OFFICER,
            'status' => Event::STAFF_STATUS_ACTIVE,
        ]);
        $this->actingAs($officer);

        Livewire::test(TicketScanner::class)
            ->call('showAttendance', 'pending')
            ->assertSee($ticket->ticket_number)
            ->assertDontSee($otherEvent->name)
            ->set('attendanceEventId', (string) $otherEvent->id)
            ->assertDontSee($otherTicket->ticket_number)
            ->assertDontSee($ticket->ticket_number);
    }

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
}
