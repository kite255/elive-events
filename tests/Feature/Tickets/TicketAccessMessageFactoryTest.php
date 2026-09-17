<?php

namespace Tests\Feature\Tickets;

use App\Models\CommunicationTemplate;
use App\Models\Event;
use App\Models\Organization;
use App\Models\TicketOrder;
use App\Services\Tickets\TicketAccessMessageFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketAccessMessageFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uses_system_defaults_when_event_has_no_ticket_templates(): void
    {
        $order = $this->createOrder();

        $messages = app(TicketAccessMessageFactory::class)
            ->make(
                $order,
                'https://events.elive.co.tz/events/tickets/order/secure-token'
            );

        $this->assertSame(
            'Your tickets for Ticket Message Concert are ready',
            $messages['email_subject']
        );

        $this->assertStringContainsString(
            'Hello Ticket Buyer,',
            $messages['email_body']
        );

        $this->assertStringContainsString(
            'Order: ELV-MSG-001',
            $messages['email_body']
        );

        $this->assertStringContainsString(
            'Your 3 ticket(s) are ready',
            $messages['sms_body']
        );

        $this->assertStringContainsString(
            'https://events.elive.co.tz/events/tickets/order/secure-token',
            $messages['sms_body']
        );
    }

    public function test_it_uses_active_event_templates_and_replaces_ticket_placeholders(): void
    {
        $order = $this->createOrder();
        $organization = $order->event->organization;

        $emailTemplate = CommunicationTemplate::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Custom Ticket Email',
            'key' => CommunicationTemplate::KEY_TICKET_DELIVERY_EMAIL,
            'channel' => CommunicationTemplate::CHANNEL_EMAIL,
            'subject' => '{{event_name}} tickets - {{order_number}}',
            'body' => 'Dear {{buyer_name}}, your {{ticket_count}} tickets are here: {{tickets_url}}',
            'is_active' => true,
        ]);

        $smsTemplate = CommunicationTemplate::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Custom Ticket SMS',
            'key' => CommunicationTemplate::KEY_TICKET_DELIVERY_SMS,
            'channel' => CommunicationTemplate::CHANNEL_SMS,
            'body' => '{{event_name}} | {{ticket_count}} | {{order_number}} | {{tickets_url}}',
            'is_active' => true,
        ]);

        $order->event->update([
            'ticket_delivery_email_template_id' => $emailTemplate->id,
            'ticket_delivery_sms_template_id' => $smsTemplate->id,
        ]);

        $messages = app(TicketAccessMessageFactory::class)
            ->make(
                $order->fresh('event'),
                'https://events.elive.co.tz/events/tickets/order/secure-token'
            );

        $this->assertSame(
            'Ticket Message Concert tickets - ELV-MSG-001',
            $messages['email_subject']
        );

        $this->assertSame(
            'Dear Ticket Buyer, your 3 tickets are here: https://events.elive.co.tz/events/tickets/order/secure-token',
            $messages['email_body']
        );

        $this->assertSame(
            'Ticket Message Concert | 3 | ELV-MSG-001 | https://events.elive.co.tz/events/tickets/order/secure-token',
            $messages['sms_body']
        );
    }

    private function createOrder(): TicketOrder
    {
        $organization = Organization::query()->create([
            'name' => 'Ticket Message Organization',
            'slug' => 'ticket-message-' . uniqid(),
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Ticket Message Concert',
            'slug' => 'ticket-message-concert-' . uniqid(),
            'event_type' => 'concert',
            'status' => Event::STATUS_ACTIVE,
            'starts_at' => now()->addDay(),
        ]);

        return TicketOrder::query()->create([
            'event_id' => $event->id,
            'order_number' => 'ELV-MSG-001',
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
        ])->load('event.organization');
    }
}
