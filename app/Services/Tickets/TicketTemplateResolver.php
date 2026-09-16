<?php

namespace App\Services\Tickets;

use App\Models\Event;
use App\Models\EventTicketTemplate;
use App\Models\OrganizationTicketTemplate;
use App\Models\Ticket;

final class TicketTemplateResolver
{
    public function resolve(
        Ticket $ticket
    ): ResolvedTicketTemplate {
        $event = Event::query()
            ->find($ticket->event_id);

        if (! $event) {
            return new ResolvedTicketTemplate(
                source: 'standard',
                template: null,
            );
        }

        if ($ticket->ticket_type_id) {
            $ticketTypeTemplate =
                EventTicketTemplate::query()
                    ->where(
                        'event_id',
                        $event->id
                    )
                    ->where(
                        'ticket_type_id',
                        $ticket->ticket_type_id
                    )
                    ->where(
                        'is_active',
                        true
                    )
                    ->orderByDesc('id')
                    ->first();

            if ($ticketTypeTemplate) {
                return new ResolvedTicketTemplate(
                    source: 'event_ticket_type',
                    template: $ticketTypeTemplate,
                );
            }
        }

        $eventDefault =
            EventTicketTemplate::query()
                ->where(
                    'event_id',
                    $event->id
                )
                ->whereNull(
                    'ticket_type_id'
                )
                ->where(
                    'is_active',
                    true
                )
                ->where(
                    'is_default',
                    true
                )
                ->orderByDesc('id')
                ->first();

        if ($eventDefault) {
            return new ResolvedTicketTemplate(
                source: 'event_default',
                template: $eventDefault,
            );
        }

        if ($event->organization_id) {
            $organizationDefault =
                OrganizationTicketTemplate::query()
                    ->where(
                        'organization_id',
                        $event->organization_id
                    )
                    ->where(
                        'is_active',
                        true
                    )
                    ->where(
                        'is_default',
                        true
                    )
                    ->orderByDesc('id')
                    ->first();

            if ($organizationDefault) {
                return new ResolvedTicketTemplate(
                    source: 'organization_default',
                    template: $organizationDefault,
                );
            }
        }

        return new ResolvedTicketTemplate(
            source: 'standard',
            template: null,
        );
    }
}
