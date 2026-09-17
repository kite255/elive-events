<?php

namespace App\Services\Tickets;

use App\Models\CommunicationTemplate;
use App\Models\Event;
use App\Models\TicketOrder;

class TicketAccessMessageFactory
{
    /**
     * @return array{
     *     email_subject: string,
     *     email_body: string,
     *     sms_body: string
     * }
     */
    public function make(
        TicketOrder $order,
        string $ticketsUrl
    ): array {
        $order->loadMissing([
            'event.ticketDeliveryEmailTemplate',
            'event.ticketDeliverySmsTemplate',
        ]);

        $event = $order->event;

        $placeholders = $this->placeholders(
            $order,
            $ticketsUrl
        );

        $emailTemplate =
            $event?->ticketDeliveryEmailTemplate;

        $smsTemplate =
            $event?->ticketDeliverySmsTemplate;

        $emailSubject = $this->isValidTemplate(
            $emailTemplate,
            $event,
            CommunicationTemplate::CHANNEL_EMAIL,
            CommunicationTemplate::KEY_TICKET_DELIVERY_EMAIL
        )
            ? (string) (
                $emailTemplate->subject
                ?: $this->defaultEmailSubject()
            )
            : $this->defaultEmailSubject();

        $emailBody = $this->isValidTemplate(
            $emailTemplate,
            $event,
            CommunicationTemplate::CHANNEL_EMAIL,
            CommunicationTemplate::KEY_TICKET_DELIVERY_EMAIL
        )
            ? (string) $emailTemplate->body
            : $this->defaultEmailBody();

        $smsBody = $this->isValidTemplate(
            $smsTemplate,
            $event,
            CommunicationTemplate::CHANNEL_SMS,
            CommunicationTemplate::KEY_TICKET_DELIVERY_SMS
        )
            ? (string) $smsTemplate->body
            : $this->defaultSmsBody();

        return [
            'email_subject' => $this->render(
                $emailSubject,
                $placeholders
            ),
            'email_body' => $this->render(
                $emailBody,
                $placeholders
            ),
            'sms_body' => $this->render(
                $smsBody,
                $placeholders
            ),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function placeholders(
        TicketOrder $order,
        string $ticketsUrl
    ): array {
        return [
            '{{buyer_name}}' =>
                trim((string) $order->buyer_name)
                ?: 'Customer',

            '{{event_name}}' =>
                trim((string) $order->event?->name)
                ?: 'eLive Event',

            '{{ticket_count}}' =>
                (string) ((int) $order->quantity),

            '{{order_number}}' =>
                (string) $order->order_number,

            '{{tickets_url}}' =>
                $ticketsUrl,
        ];
    }

    private function isValidTemplate(
        ?CommunicationTemplate $template,
        ?Event $event,
        string $channel,
        string $key
    ): bool {
        return $template !== null
            && $event !== null
            && (bool) $template->is_active
            && filled($template->body)
            && $template->channel === $channel
            && $template->key === $key
            && (int) $template->organization_id
                === (int) $event->organization_id;
    }

    /**
     * @param array<string, string> $placeholders
     */
    private function render(
        string $message,
        array $placeholders
    ): string {
        return strtr(
            $message,
            $placeholders
        );
    }

    private function defaultEmailSubject(): string
    {
        return 'Your tickets for {{event_name}} are ready';
    }

    private function defaultEmailBody(): string
    {
        return implode(
            PHP_EOL,
            [
                'Hello {{buyer_name}},',
                '',
                'Your tickets for {{event_name}} are ready.',
                '',
                'Number of tickets: {{ticket_count}}',
                'Order: {{order_number}}',
                '',
                'View and download your tickets:',
                '{{tickets_url}}',
                '',
                'Keep this link private because it provides access to your tickets.',
                '',
                'eLive Events',
            ]
        );
    }

    private function defaultSmsBody(): string
    {
        return 'eLive Events: Payment confirmed for {{event_name}}. '
            . 'Your {{ticket_count}} ticket(s) are ready. '
            . 'Order: {{order_number}}. '
            . 'View tickets: {{tickets_url}}';
    }
}
