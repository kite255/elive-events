<?php

namespace App\Data\Tickets;

use App\Models\EventTicketTemplate;
use App\Models\OrganizationTicketTemplate;
use App\Models\TicketTemplatePage;
use Illuminate\Database\Eloquent\Collection;

final readonly class TicketRenderContext
{
    /**
     * @param Collection<int, TicketTemplatePage> $pages
     */
    public function __construct(
        public string $ticketNumber,
        public string $holderName,
        public string $ticketType,
        public string $eventName,
        public string $eventDate,
        public string $eventTime,
        public string $venue,
        public string $orderNumber,
        public string $ticketStatus,
        public string $qrCredential,
        public string $templateSource,
        public EventTicketTemplate|OrganizationTicketTemplate|null $template,
        public int $width,
        public int $height,
        public Collection $pages,
    ) {
    }

    public function binding(string $name): string
    {
        return $this->bindings()[$name] ?? '';
    }

    /**
     * @return array<string, string>
     */
    public function bindings(): array
    {
        return [
            'holder_name' => $this->holderName,
            'ticket_number' => $this->ticketNumber,
            'ticket_type' => $this->ticketType,
            'event_name' => $this->eventName,
            'event_date' => $this->eventDate,
            'event_time' => $this->eventTime,
            'venue' => $this->venue,
            'order_number' => $this->orderNumber,
        ];
    }
}
