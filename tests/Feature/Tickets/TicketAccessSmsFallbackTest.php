<?php

namespace Tests\Feature\Tickets;

use App\Jobs\SendTicketAccessLinkJob;
use App\Models\CommunicationLog;
use App\Models\Event;
use App\Models\Organization;
use App\Models\TicketOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class TicketAccessSmsFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_permanent_whatsapp_failure_queues_exactly_one_sms_fallback(): void
    {
        Queue::fake();

        $organization = Organization::query()->create([
            'name' => 'SMS Fallback Organization',
            'slug' => 'sms-fallback-' . uniqid(),
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'SMS Fallback Concert',
            'slug' => 'sms-fallback-concert-' . uniqid(),
            'event_type' => 'concert',
            'status' => Event::STATUS_ACTIVE,
            'starts_at' => now()->addDay(),
        ]);

        $order = TicketOrder::query()->create([
            'event_id' => $event->id,
            'order_number' => 'ELV-FALLBACK-001',
            'buyer_name' => 'Ticket Buyer',
            'buyer_phone' => '0712345678',
            'buyer_email' => 'buyer@example.com',
            'quantity' => 1,
            'subtotal' => 1000,
            'discount_amount' => 0,
            'total' => 1000,
            'currency' => 'TZS',
            'status' => TicketOrder::STATUS_PAID,
            'paid_at' => now(),
        ]);

        $whatsAppLog = CommunicationLog::query()->create([
            'event_id' => $event->id,
            'ticket_order_id' => $order->id,
            'purpose' => CommunicationLog::PURPOSE_TICKET_ACCESS,
            'channel' => CommunicationLog::CHANNEL_WHATSAPP,
            'recipient' => '255712345678',
            'message' => 'Secure ticket access link for order ELV-FALLBACK-001',
            'status' => CommunicationLog::STATUS_QUEUED,
            'queued_at' => now(),
        ]);

        $job = new SendTicketAccessLinkJob(
            $whatsAppLog->id
        );

        $job->failed(
            new RuntimeException(
                'Meta rejected the ticket template.'
            )
        );

        $job->failed(
            new RuntimeException(
                'Duplicate permanent failure callback.'
            )
        );

        $whatsAppLog->refresh();

        $this->assertSame(
            CommunicationLog::STATUS_FAILED,
            $whatsAppLog->status
        );

        $smsLogs = CommunicationLog::query()
            ->where('ticket_order_id', $order->id)
            ->where(
                'purpose',
                CommunicationLog::PURPOSE_TICKET_ACCESS
            )
            ->where(
                'channel',
                CommunicationLog::CHANNEL_SMS
            )
            ->get();

        $this->assertCount(
            1,
            $smsLogs
        );

        $this->assertSame(
            '255712345678',
            $smsLogs->first()->recipient
        );

        Queue::assertPushed(
            SendTicketAccessLinkJob::class,
            1
        );
    }
}
