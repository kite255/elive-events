<?php

namespace Tests\Feature\Tickets;

use Tests\TestCase;

class TicketDeliveryConfigurationTest extends TestCase
{
    public function test_ticket_delivery_whatsapp_template_is_configured(): void
    {
        $this->assertSame(
            'concert_ticket_order_delivery_en',
            config(
                'services.whatsapp.templates.ticket_access'
            )
        );
    }

    public function test_event_form_contains_ticket_delivery_email_and_sms_template_selectors(): void
    {
        $form = file_get_contents(
            app_path(
                'Filament/Resources/Events/Schemas/EventForm.php'
            )
        );

        $this->assertIsString($form);

        $this->assertStringContainsString(
            "Select::make(\n                            'ticket_delivery_email_template_id'",
            $form
        );

        $this->assertStringContainsString(
            'CommunicationTemplate::KEY_TICKET_DELIVERY_EMAIL',
            $form
        );

        $this->assertStringContainsString(
            "Select::make(\n                            'ticket_delivery_sms_template_id'",
            $form
        );

        $this->assertStringContainsString(
            'CommunicationTemplate::KEY_TICKET_DELIVERY_SMS',
            $form
        );
    }
}
