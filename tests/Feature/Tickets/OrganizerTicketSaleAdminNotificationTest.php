<?php

namespace Tests\Feature\Tickets;

use App\Jobs\SendAutomaticCommunicationJob;
use App\Models\CommunicationLog;
use App\Models\Event;
use App\Models\Organization;
use App\Models\TicketOrder;
use App\Models\User;
use App\Services\Tickets\OrganizerTicketSaleNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrganizerTicketSaleAdminNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmed_sale_notifies_super_admin_once_with_buyer_details(): void
    {
        Queue::fake();

        $organization = Organization::query()->create([
            'name' => 'Sales Organizer',
            'slug' => 'sales-organizer',
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'eLive Sales Event',
            'slug' => 'elive-sales-event',
            'status' => Event::STATUS_ACTIVE,
            'starts_at' => now()->addDays(5),
        ]);

        $admin = User::factory()->create([
            'email' => 'superadmin@example.com',
            'is_super_admin' => true,
        ]);

        User::factory()->create([
            'email' => 'ordinary@example.com',
            'is_super_admin' => false,
        ]);

        $order = TicketOrder::query()->create([
            'event_id' => $event->id,
            'order_number' => 'ELV-ADMIN-SALE-001',
            'buyer_name' => 'Jane Buyer',
            'buyer_email' => 'jane@example.com',
            'buyer_phone' => '+255712345678',
            'quantity' => 2,
            'subtotal' => 50000,
            'total' => 50000,
            'organizer_net_amount' => 47500,
            'currency' => 'TZS',
            'status' => TicketOrder::STATUS_PAID,
            'paid_at' => now(),
            'financial_snapshot_at' => now(),
        ]);

        $service = app(OrganizerTicketSaleNotificationService::class);
        $service->queueForPaidOrder($order->id);
        $service->queueForPaidOrder($order->id);

        $logs = CommunicationLog::query()
            ->where('ticket_order_id', $order->id)
            ->where('purpose', 'organizer_ticket_sale')
            ->get();

        $this->assertCount(1, $logs);
        $this->assertSame($admin->email, $logs->first()->recipient);
        $this->assertStringContainsString('Jane Buyer', $logs->first()->message);
        $this->assertStringContainsString('jane@example.com', $logs->first()->message);
        $this->assertStringContainsString('+255712345678', $logs->first()->message);
        $this->assertStringContainsString('47,500.00', $logs->first()->message);
        Queue::assertPushed(SendAutomaticCommunicationJob::class, 1);
    }

    public function test_branded_email_includes_buyer_and_financial_summary(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Email Preview Organization',
            'slug' => 'email-preview-organization',
        ]);
        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Preview Concert',
            'slug' => 'preview-concert',
            'status' => Event::STATUS_ACTIVE,
            'starts_at' => now()->addDays(5),
        ]);
        $order = TicketOrder::query()->create([
            'event_id' => $event->id,
            'order_number' => 'ELV-EMAIL-001',
            'buyer_name' => 'Jane Buyer',
            'buyer_email' => 'jane@example.com',
            'buyer_phone' => '+255712345678',
            'quantity' => 2,
            'subtotal' => 50000,
            'total' => 50000,
            'organizer_net_amount' => 47500,
            'currency' => 'TZS',
            'status' => TicketOrder::STATUS_PAID,
            'paid_at' => now(),
            'financial_snapshot_at' => now(),
        ]);

        $html = view('emails.ticket-sale-notification', [
            'subject' => 'New ticket sale confirmed - Preview Concert',
            'order' => $order->load('items.ticketType'),
            'event' => $event,
        ])->render();

        $this->assertStringContainsString('Jane Buyer', $html);
        $this->assertStringContainsString('jane@example.com', $html);
        $this->assertStringContainsString('+255712345678', $html);
        $this->assertStringContainsString('ELV-EMAIL-001', $html);
        $this->assertStringContainsString('TZS 47,500.00', $html);
        $this->assertStringContainsString('not a settlement or payout confirmation', $html);
        $this->assertStringContainsString(url('/admin'), $html);
        $this->assertStringContainsString('eLive Events', $html);
    }

    public function test_unpaid_order_does_not_send_admin_notification(): void
    {
        Queue::fake();

        $organization = Organization::query()->create([
            'name' => 'Unpaid Organizer',
            'slug' => 'unpaid-organizer',
        ]);
        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Unpaid Event',
            'slug' => 'unpaid-event',
            'status' => Event::STATUS_ACTIVE,
            'starts_at' => now()->addDays(2),
        ]);
        User::factory()->create(['is_super_admin' => true]);

        $order = TicketOrder::query()->create([
            'event_id' => $event->id,
            'order_number' => 'ELV-UNPAID-001',
            'buyer_name' => 'Unpaid Buyer',
            'quantity' => 1,
            'subtotal' => 5000,
            'total' => 5000,
            'currency' => 'TZS',
            'status' => TicketOrder::STATUS_PENDING,
        ]);

        app(OrganizerTicketSaleNotificationService::class)->queueForPaidOrder($order->id);

        $this->assertDatabaseCount('communication_logs', 0);
        Queue::assertNothingPushed();
    }
}
