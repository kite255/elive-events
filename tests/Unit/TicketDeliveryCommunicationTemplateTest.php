<?php

namespace Tests\Unit;

use App\Models\CommunicationTemplate;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TicketDeliveryCommunicationTemplateTest extends TestCase
{
    #[Test]
    public function ticket_delivery_uses_dedicated_email_and_sms_template_types(): void
    {
        $this->assertSame(
            'ticket_delivery_email',
            CommunicationTemplate::KEY_TICKET_DELIVERY_EMAIL
        );

        $this->assertSame(
            'Ticket Delivery',
            CommunicationTemplate::emailTemplateTypes()[
                CommunicationTemplate::KEY_TICKET_DELIVERY_EMAIL
            ]
        );

        $this->assertSame(
            'ticket_delivery_sms',
            CommunicationTemplate::KEY_TICKET_DELIVERY_SMS
        );

        $this->assertSame(
            'Ticket Delivery',
            CommunicationTemplate::smsTemplateTypes()[
                CommunicationTemplate::KEY_TICKET_DELIVERY_SMS
            ]
        );

        $this->assertNotSame(
            CommunicationTemplate::KEY_REGISTRATION_CONFIRMED_EMAIL,
            CommunicationTemplate::KEY_TICKET_DELIVERY_EMAIL
        );

        $this->assertNotSame(
            CommunicationTemplate::KEY_REGISTRATION_CONFIRMED_SMS,
            CommunicationTemplate::KEY_TICKET_DELIVERY_SMS
        );
    }
}
