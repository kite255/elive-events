<?php

namespace App\Services;

use App\Models\Event;

class EventPublicationReadiness
{
    /** Return configuration blockers, not claims about live provider availability. */
    public function blockers(Event $event): array
    {
        $errors = [];

        if (blank($event->name) || blank($event->event_type) || ! $event->organization()->exists()) {
            $errors[] = 'Complete the event name, type and organization.';
        }
        if (blank($event->slug)) {
            $errors[] = 'The public event URL is missing.';
        }
        if (! $event->starts_at || ! $event->ends_at || $event->ends_at->lt($event->starts_at)) {
            $errors[] = 'Set valid start and end dates.';
        }
        if ($event->capacity !== null && (int) $event->capacity < 1) {
            $errors[] = 'Capacity must be positive or left empty.';
        }

        $event->loadMissing(['ticketSetting', 'paymentSetting']);

        if ($event->registration_is_open) {
            if ($event->paymentsAreEnabled() && (float) $event->registrationFee() <= 0) {
                $errors[] = 'Paid registration requires a positive registration fee.';
            }

            foreach (['phone', 'email', 'organization', 'position', 'category', 'badge_type'] as $field) {
                if ($event->{'registration_require_'.$field} && ! $event->{'registration_show_'.$field}) {
                    $errors[] = 'A required registration field is hidden: '.str_replace('_', ' ', $field).'.';
                }
            }

            if ($event->registration_require_category && ! $event->attendeeCategories()->exists()) {
                $errors[] = 'Create attendee categories before requiring category selection.';
            }
            if ($event->registration_require_badge_type && ! $event->badgeTypes()->exists()) {
                $errors[] = 'Create badge types before requiring badge type selection.';
            }
        }

        if ($event->ticketSetting?->ticket_sales_enabled && ! $event->publicTicketTypes()->exists()) {
            $errors[] = 'Create at least one active public ticket type before opening sales.';
        }

        return $errors;
    }
}
