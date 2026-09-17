<?php

namespace App\Services\Tickets\Rendering;

use App\Data\Tickets\TicketRenderContext;
use App\Models\Ticket;
use App\Services\Tickets\TicketTemplateResolver;
use DateTimeZone;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

final class TicketRenderContextFactory
{
    public function __construct(
        private readonly TicketTemplateResolver $templateResolver,
    ) {
    }

    public function make(Ticket $ticket): TicketRenderContext
    {
        $ticket->loadMissing([
            'event.organization',
            'ticketType',
            'order',
        ]);

        $resolved = $this->templateResolver
            ->resolve($ticket);

        $template = $resolved->template;

        if ($template) {
            $template->loadMissing('pages');

            $pages = new Collection(
                $template->pages
                    ->sortBy('page_number')
                    ->values()
                    ->all()
            );
        } else {
            $pages = new Collection();
        }

        $event = $ticket->event;
        $organization = $event?->organization;
        $timezone = $this->validTimezone(
            $organization?->timezone
        );
        $startsAt = $event?->starts_at
            ?->copy()
            ->setTimezone($timezone);

        return new TicketRenderContext(
            ticketNumber: (string) $ticket->ticket_number,
            holderName: (string) $ticket->holder_name,
            ticketType: (string) ($ticket->ticketType?->name ?? ''),
            eventName: (string) ($event?->name ?? ''),
            eventDate: $startsAt?->format(
                $organization?->date_format ?: 'd/m/Y'
            ) ?? '',
            eventTime: $startsAt?->format(
                $organization?->time_format ?: 'H:i'
            ) ?? '',
            venue: (string) ($event?->venue ?? ''),
            orderNumber: (string) ($ticket->order?->order_number ?? ''),
            ticketStatus: (string) $ticket->status,
            qrCredential: (string) $ticket->qr_token_encrypted,
            templateSource: $resolved->source,
            template: $template,
            width: (int) ($template?->width ?? 1080),
            height: (int) ($template?->height ?? 1350),
            pages: $pages,
        );
    }

    private function validTimezone(?string $timezone): string
    {
        $fallback = (string) config('app.timezone', 'UTC');

        try {
            new DateTimeZone($timezone ?: $fallback);

            return $timezone ?: $fallback;
        } catch (Throwable) {
            return $fallback;
        }
    }
}
