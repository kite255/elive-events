<?php

namespace Tests\Feature\Tickets;

use App\Models\CommunicationTemplate;
use App\Models\Event;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketDeliveryEventTemplateSelectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_can_select_dedicated_ticket_delivery_email_and_sms_templates(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Template Selection Organization',
            'slug' => 'template-selection-' . uniqid(),
        ]);

        $emailTemplate = CommunicationTemplate::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Concert Ticket Email',
            'key' => CommunicationTemplate::KEY_TICKET_DELIVERY_EMAIL,
            'channel' => CommunicationTemplate::CHANNEL_EMAIL,
            'subject' => 'Your tickets for {{event_name}} are ready',
            'body' => 'Hello {{buyer_name}}, view {{ticket_count}} ticket(s): {{tickets_url}}',
            'is_active' => true,
        ]);

        $smsTemplate = CommunicationTemplate::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Concert Ticket SMS',
            'key' => CommunicationTemplate::KEY_TICKET_DELIVERY_SMS,
            'channel' => CommunicationTemplate::CHANNEL_SMS,
            'body' => 'Tickets for {{event_name}}: {{tickets_url}}',
            'is_active' => true,
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'ticket_delivery_email_template_id' => $emailTemplate->id,
            'ticket_delivery_sms_template_id' => $smsTemplate->id,
            'name' => 'Template Selection Concert',
            'slug' => 'template-selection-concert-' . uniqid(),
            'event_type' => 'concert',
            'status' => Event::STATUS_ACTIVE,
            'starts_at' => now()->addDay(),
        ]);

        $this->assertTrue(
            $event->ticketDeliveryEmailTemplate->is($emailTemplate)
        );

        $this->assertTrue(
            $event->ticketDeliverySmsTemplate->is($smsTemplate)
        );
    }
}
