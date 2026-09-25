<?php

namespace Tests\Feature\Tickets;

use App\Jobs\SendTicketAccessLinkJob;
use App\Models\AuditLog;
use App\Models\CommunicationLog;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketType;
use App\Models\User;
use App\Services\Tickets\ManualTicketResendService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ManualTicketResendServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        config([
            'services.whatsapp.access_token' => null,
            'services.whatsapp.phone_number_id' => null,
        ]);
    }

    public function test_manual_resend_queues_a_fresh_attempt_without_changing_ticket_identity(): void
    {
        [$order, $ticket] = $this->makeTicket();
        $admin = User::factory()->create(['is_super_admin' => true]);

        CommunicationLog::query()->create([
            'event_id' => $order->event_id,
            'ticket_order_id' => $order->id,
            'purpose' => CommunicationLog::PURPOSE_TICKET_ACCESS,
            'channel' => CommunicationLog::CHANNEL_SMS,
            'recipient' => '255700000001',
            'message' => 'Original automatic delivery',
            'status' => CommunicationLog::STATUS_SENT,
            'sent_at' => now()->subMinute(),
        ]);

        $before = $ticket->only([
            'ticket_number',
            'public_token',
            'qr_token_hash',
            'ticket_type_id',
            'status',
        ]);

        $result = app(ManualTicketResendService::class)->queue(
            $order,
            [CommunicationLog::CHANNEL_SMS],
            $admin
        );

        $this->assertSame([CommunicationLog::CHANNEL_SMS], $result['queued']);
        $this->assertSame([], $result['skipped']);

        $this->assertDatabaseHas('communication_logs', [
            'ticket_order_id' => $order->id,
            'purpose' => CommunicationLog::PURPOSE_TICKET_ACCESS_RECOVERY,
            'channel' => CommunicationLog::CHANNEL_SMS,
            'status' => CommunicationLog::STATUS_QUEUED,
        ]);

        Queue::assertPushed(SendTicketAccessLinkJob::class, 1);

        $ticket->refresh();
        $this->assertSame($before, $ticket->only(array_keys($before)));
        $this->assertSame(1, Ticket::query()->where('ticket_order_id', $order->id)->count());

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'event_id' => $order->event_id,
            'action' => 'ticket.access_resent',
            'subject_type' => TicketOrder::class,
            'subject_id' => $order->id,
        ]);
    }

    public function test_available_channels_only_include_valid_configured_destinations(): void
    {
        [$order] = $this->makeTicket();

        $channels = app(ManualTicketResendService::class)->availableChannels($order);

        $this->assertSame([
            CommunicationLog::CHANNEL_EMAIL => 'Email',
            CommunicationLog::CHANNEL_SMS => 'SMS',
        ], $channels);
    }

    public function test_unrelated_user_cannot_resend_ticket_access(): void
    {
        [$order] = $this->makeTicket();
        $user = User::factory()->create(['is_super_admin' => false]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('You are not allowed to resend tickets for this event.');

        app(ManualTicketResendService::class)->queue(
            $order,
            [CommunicationLog::CHANNEL_SMS],
            $user
        );
    }

    private function makeTicket(): array
    {
        $organization = Organization::query()->create([
            'name' => 'Resend Organization',
            'slug' => 'resend-org-' . uniqid(),
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Resend Event',
            'slug' => 'resend-event-' . uniqid(),
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
            'order_number' => 'ELV-RESEND-' . strtoupper(uniqid()),
            'buyer_name' => 'Resend Buyer',
            'buyer_phone' => '0712345678',
            'buyer_email' => 'resend@example.com',
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
            'ticket_number' => 'ELV-TKT-' . strtoupper(uniqid()),
            'public_token' => 'ticket-' . uniqid(),
            'qr_token_hash' => hash('sha256', 'resend-' . uniqid()),
            'holder_name' => $order->buyer_name,
            'holder_phone' => $order->buyer_phone,
            'holder_email' => $order->buyer_email,
            'price' => 10000,
            'currency' => 'TZS',
            'status' => Ticket::STATUS_ISSUED,
            'issued_at' => now(),
        ]);

        return [$order, $ticket];
    }
}
