<?php

namespace Tests\Feature\Tickets;

use App\Jobs\SendTicketAccessLinkJob;
use App\Models\CommunicationLog;
use App\Models\Event;
use App\Models\Organization;
use App\Models\TicketOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SendTicketAccessLinkJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_whatsapp_ticket_access_job_records_successful_delivery(): void
    {
        config([
            'services.whatsapp.access_token' => 'test-token',
            'services.whatsapp.phone_number_id' => '123456789',
            'services.whatsapp.graph_version' => 'v24.0',
            'services.whatsapp.default_language' => 'en',
            'services.whatsapp.templates.ticket_access' =>
                'concert_tickets_delivery_en',
        ]);

        Http::fake([
            '*' => Http::response([
                'messages' => [
                    [
                        'id' => 'wamid.ticket-job',
                    ],
                ],
            ]),
        ]);

        $organization = Organization::query()->create([
            'name' => 'Ticket Job Organization',
            'slug' => 'ticket-job-' . uniqid(),
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Ticket Job Concert',
            'slug' => 'ticket-job-concert-' . uniqid(),
            'event_type' => 'concert',
            'status' => Event::STATUS_ACTIVE,
            'starts_at' => now()->addDay(),
        ]);

        $order = TicketOrder::query()->create([
            'event_id' => $event->id,
            'order_number' => 'ELV-JOB-001',
            'buyer_name' => 'Ticket Buyer',
            'buyer_phone' => '255712345678',
            'buyer_email' => 'buyer@example.com',
            'quantity' => 3,
            'subtotal' => 3000,
            'discount_amount' => 0,
            'total' => 3000,
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
            'message' => 'Secure ticket access link for order ELV-JOB-001',
            'status' => CommunicationLog::STATUS_QUEUED,
            'queued_at' => now(),
        ]);

        $job = new SendTicketAccessLinkJob(
            $log->id
        );

        app()->call([
            $job,
            'handle',
        ]);

        $log->refresh();

        $this->assertSame(
            CommunicationLog::STATUS_SENT,
            $log->status
        );

        $this->assertSame(
            1,
            $log->attempt_count
        );

        $this->assertSame(
            'wamid.ticket-job',
            $log->provider_message_id
        );

        Http::assertSent(
            fn ($request): bool =>
                data_get(
                    $request->data(),
                    'template.name'
                ) === 'concert_tickets_delivery_en'
                && data_get(
                    collect(
                        data_get(
                            $request->data(),
                            'template.components'
                        )
                    )->firstWhere('type', 'button'),
                    'parameters.0.text'
                ) === $order->public_token
        );
    }
}
