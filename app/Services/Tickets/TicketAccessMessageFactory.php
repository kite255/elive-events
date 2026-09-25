<?php

namespace App\Services\Tickets;

use App\Models\CommunicationTemplate;
use App\Models\Event;
use App\Models\TicketOrder;
use App\Models\TicketUpgrade;

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
        string $ticketsUrl,
        ?TicketUpgrade $upgrade = null
    ): array {
        $order->loadMissing([
            'event.ticketDeliveryEmailTemplate',
            'event.ticketDeliverySmsTemplate',
            'items.ticketType',
        ]);

        $upgrade?->loadMissing([
            'fromTicketType',
            'toTicketType',
        ]);

        $event = $order->event;
        $placeholders = $this->placeholders($order, $ticketsUrl, $upgrade);

        if ($upgrade !== null) {
            return [
                'email_subject' => $this->render(
                    'Your upgraded ticket for #EVENT_NAME# is ready',
                    $placeholders
                ),
                'email_body' => $this->render(
                    $this->defaultUpgradeEmailBody(),
                    $placeholders
                ),
                'sms_body' => $this->render(
                    $this->defaultUpgradeSmsBody(),
                    $placeholders
                ),
            ];
        }

        $emailTemplate = $event?->ticketDeliveryEmailTemplate;
        $smsTemplate = $event?->ticketDeliverySmsTemplate;

        $emailSubject = $this->isValidTemplate(
            $emailTemplate,
            $event,
            CommunicationTemplate::CHANNEL_EMAIL,
            CommunicationTemplate::KEY_TICKET_DELIVERY_EMAIL
        )
            ? (string) ($emailTemplate->subject ?: $this->defaultEmailSubject())
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
            'email_subject' => $this->render($emailSubject, $placeholders),
            'email_body' => $this->render($emailBody, $placeholders),
            'sms_body' => $this->render($smsBody, $placeholders),
        ];
    }

    /** @return array<string, string> */
    private function placeholders(
        TicketOrder $order,
        string $ticketsUrl,
        ?TicketUpgrade $upgrade = null
    ): array {
        $event = $order->event;
        $buyerName = trim((string) $order->buyer_name) ?: 'Customer';
        $eventName = trim((string) $event?->name) ?: 'eLive Event';
        $ticketCount = (string) ((int) $order->quantity);
        $orderNumber = (string) $order->order_number;

        $ticketTypes = $order->items
            ->map(function ($item): ?string {
                $name = trim((string) $item->ticketType?->name);
                if ($name === '') {
                    return null;
                }

                return (int) $item->quantity > 1
                    ? $name . ' × ' . (int) $item->quantity
                    : $name;
            })
            ->filter()
            ->implode(', ');

        $ticketTypes = $ticketTypes !== '' ? $ticketTypes : 'Ticket';

        if ($upgrade !== null) {
            $ticketTypes = trim((string) $upgrade->toTicketType?->name) ?: $ticketTypes;
            $ticketCount = '1';
        }

        $eventVenue = trim((string) $event?->venue) ?: 'Venue to be confirmed';
        $eventDate = $event?->starts_at ? $event->starts_at->format('d M Y') : 'Date to be confirmed';
        $eventTime = $event?->starts_at ? $event->starts_at->format('h:i A') : 'Time to be confirmed';

        return [
            '{{buyer_name}}' => $buyerName,
            '{{event_name}}' => $eventName,
            '{{ticket_count}}' => $ticketCount,
            '{{order_number}}' => $orderNumber,
            '{{tickets_url}}' => $ticketsUrl,
            '#PURCHASER_NAME#' => $buyerName,
            '#EVENT_NAME#' => $eventName,
            '#ORDER_REFERENCE#' => $orderNumber,
            '#TICKET_TYPE#' => $ticketTypes,
            '#TICKET_COUNT#' => $ticketCount,
            '#EVENT_DATE#' => $eventDate,
            '#EVENT_TIME#' => $eventTime,
            '#EVENT_VENUE#' => $eventVenue,
            '#TICKET_LINK#' => $ticketsUrl,
            '#OLD_TICKET_TYPE#' => trim((string) $upgrade?->fromTicketType?->name) ?: '',
            '#NEW_TICKET_TYPE#' => trim((string) $upgrade?->toTicketType?->name) ?: '',
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
            && (int) $template->organization_id === (int) $event->organization_id;
    }

    /** @param array<string, string> $placeholders */
    private function render(string $message, array $placeholders): string
    {
        return strtr($message, $placeholders);
    }

    private function defaultEmailSubject(): string
    {
        return 'Your tickets for #EVENT_NAME# are ready';
    }

    private function defaultEmailBody(): string
    {
        return implode(PHP_EOL, [
            'Hello #PURCHASER_NAME#,',
            '',
            'Your payment has been confirmed, and your tickets for #EVENT_NAME# are ready.',
            '',
            'Order reference: #ORDER_REFERENCE#',
            'Ticket type: #TICKET_TYPE#',
            'Number of tickets: #TICKET_COUNT#',
            'Event date: #EVENT_DATE#',
            'Event time: #EVENT_TIME#',
            'Venue: #EVENT_VENUE#',
            '',
            'Use the secure link below to view and download your tickets:',
            '#TICKET_LINK#',
            '',
            'Each ticket contains a unique QR code that will be scanned at the entrance.',
            'Do not share your ticket link or QR codes publicly.',
            '',
            'We look forward to welcoming you.',
            '',
            'Kind regards,',
            'eLive Events',
        ]);
    }

    private function defaultSmsBody(): string
    {
        return 'eLive Events: Payment confirmed for #EVENT_NAME#. '
            . 'Your #TICKET_COUNT# ticket(s) are ready. '
            . 'Order: #ORDER_REFERENCE#. '
            . 'View tickets: #TICKET_LINK#';
    }

    private function defaultUpgradeEmailBody(): string
    {
        return implode(PHP_EOL, [
            'Hello #PURCHASER_NAME#,',
            '',
            'Your ticket upgrade for #EVENT_NAME# has been confirmed.',
            '',
            'Order reference: #ORDER_REFERENCE#',
            'Previous ticket: #OLD_TICKET_TYPE#',
            'New ticket: #NEW_TICKET_TYPE#',
            '',
            'Your existing ticket has been updated. Use the same secure link below to view or download it:',
            '#TICKET_LINK#',
            '',
            'Your ticket number and QR access remain tied to the same ticket.',
            'Do not share your ticket link or QR code publicly.',
            '',
            'Kind regards,',
            'eLive Events',
        ]);
    }

    private function defaultUpgradeSmsBody(): string
    {
        return 'eLive Events: Your #EVENT_NAME# ticket has been upgraded from '
            . '#OLD_TICKET_TYPE# to #NEW_TICKET_TYPE#. '
            . 'Order: #ORDER_REFERENCE#. View ticket: #TICKET_LINK#';
    }
}
