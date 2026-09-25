<?php

namespace Tests\Feature\Tickets;

use App\Filament\Resources\TicketCheckIns\TicketCheckInResource;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\TicketCheckIn;
use App\Models\TicketOrder;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketCheckInHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_check_in_model_exposes_ticket_and_operator_context(): void
    {
        [$event, $ticket] = $this->makeTicket();
        $officer = User::factory()->create();

        $checkIn = TicketCheckIn::query()->create([
            'event_id' => $event->id,
            'ticket_id' => $ticket->id,
            'checked_in_by' => $officer->id,
            'method' => 'qr',
            'checked_in_at' => now(),
        ]);

        $this->assertSame($ticket->id, $checkIn->ticket->id);
        $this->assertSame($event->id, $checkIn->event->id);
        $this->assertSame($officer->id, $checkIn->checkedInBy->id);
        $this->assertSame('QR Code', $checkIn->methodLabel());
    }

    public function test_unknown_check_in_method_is_humanized_instead_of_throwing(): void
    {
        [$event, $ticket] = $this->makeTicket();

        $checkIn = TicketCheckIn::query()->create([
            'event_id' => $event->id,
            'ticket_id' => $ticket->id,
            'method' => 'legacy_gate_scan',
            'checked_in_at' => now(),
        ]);

        $this->assertSame('Legacy Gate Scan', $checkIn->methodLabel());
    }

    public function test_check_in_officer_can_only_query_assigned_event_history(): void
    {
        [$assignedEvent, $assignedTicket, $organization] = $this->makeTicket('Assigned');
        [$otherEvent, $otherTicket] = $this->makeTicket('Other');
        $officer = User::factory()->create(['is_super_admin' => false]);

        $organization->users()->attach($officer->id, [
            'role' => User::ORGANIZATION_ROLE_CHECK_IN_OFFICER,
            'status' => User::ORGANIZATION_STATUS_ACTIVE,
            'is_owner' => false,
            'joined_at' => now(),
        ]);
        $assignedEvent->assignUser($officer, User::ORGANIZATION_ROLE_CHECK_IN_OFFICER);

        $assignedCheckIn = TicketCheckIn::query()->create([
            'event_id' => $assignedEvent->id,
            'ticket_id' => $assignedTicket->id,
            'checked_in_by' => $officer->id,
            'method' => 'qr',
            'checked_in_at' => now(),
        ]);

        TicketCheckIn::query()->create([
            'event_id' => $otherEvent->id,
            'ticket_id' => $otherTicket->id,
            'method' => 'qr',
            'checked_in_at' => now(),
        ]);

        $this->actingAs($officer);

        $this->assertTrue(TicketCheckInResource::canViewAny());
        $ids = TicketCheckInResource::getEloquentQuery()->pluck('ticket_check_ins.id')->all();

        $this->assertSame([$assignedCheckIn->id], $ids);
        $this->assertFalse(TicketCheckInResource::canCreate());
        $this->assertFalse(TicketCheckInResource::canEdit($assignedCheckIn));
        $this->assertFalse(TicketCheckInResource::canDelete($assignedCheckIn));
    }

    private function makeTicket(string $suffix = 'History'): array
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
            'code' => 'REG',
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
            'order_number' => 'ELV-HIST-' . strtoupper(uniqid()),
            'buyer_name' => 'History Buyer',
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
            'ticket_number' => 'ELV-HIST-TKT-' . strtoupper(uniqid()),
            'public_token' => 'history-' . uniqid(),
            'qr_token_hash' => hash('sha256', 'history-' . uniqid()),
            'holder_name' => 'History Buyer',
            'price' => 10000,
            'currency' => 'TZS',
            'status' => Ticket::STATUS_USED,
            'issued_at' => now()->subHour(),
            'used_at' => now(),
        ]);

        return [$event, $ticket, $organization];
    }
}
