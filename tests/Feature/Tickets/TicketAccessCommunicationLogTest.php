<?php

namespace Tests\Feature\Tickets;

use App\Models\CommunicationLog;
use App\Models\Event;
use App\Models\Organization;
use App\Models\TicketOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketAccessCommunicationLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_delivery_log_belongs_to_ticket_order_and_tracks_attempts(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Delivery Test Organization',
            'slug' => 'delivery-test-' . uniqid(),
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Delivery Test Event',
            'slug' => 'delivery-event-' . uniqid(),
            'event_type' => 'concert',
            'status' => Event::STATUS_ACTIVE,
            'starts_at' => now()->addDay(),
        ]);

        $order = TicketOrder::query()->create([
            'event_id' => $event->id,
            'order_number' => 'ELV-LOG-' . strtoupper(uniqid()),
            'buyer_name' => 'Ticket Buyer',
            'buyer_phone' => '255712345678',
            'quantity' => 1,
            'subtotal' => 1000,
            'discount_amount' => 0,
            'total' => 1000,
            'currency' => 'TZS',
            'status' => TicketOrder::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $log = CommunicationLog::query()->create([
            'event_id' => $event->id,
            'ticket_order_id' => $order->id,
            'purpose' => CommunicationLog::PURPOSE_TICKET_ACCESS,
            'channel' => CommunicationLog::CHANNEL_WHATSAPP,
            'recipient' => '255712345678',
            'message' => "Secure ticket access link for order {$order->order_number}",
            'status' => CommunicationLog::STATUS_QUEUED,
            'attempt_count' => 2,
            'queued_at' => now(),
        ]);

        $this->assertTrue(
            $log->ticketOrder->is($order)
        );

        $this->assertSame(
            2,
            $log->attempt_count
        );

        $this->assertTrue(
            $order
                ->communicationLogs()
                ->whereKey($log)
                ->exists()
        );
    }
}
