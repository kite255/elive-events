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
        if (filled($event->slug) && Event::query()->where('slug', $event->slug)->whereKeyNot($event->getKey())->exists()) {
            $errors[] = 'The public event URL is already used by another event.';
        }
        if (! $event->starts_at || ! $event->ends_at || $event->ends_at->lte($event->starts_at)) {
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

            if ($event->registration_require_category && ! $event->attendeeCategories()->publiclySelectable()->exists()) {
                $errors[] = 'Create attendee categories before requiring category selection.';
            }
            if ($event->registration_require_badge_type && ! $event->badgeTypes()->where('is_active', true)->exists()) {
                $errors[] = 'Create badge types before requiring badge type selection.';
            }
        }

        if ($event->paymentSetting?->payments_enabled && ! $event->registration_is_open) {
            $errors[] = 'Enable registration or disable registration payments.';
        }

        if ($event->registration_is_open && $event->paymentsAreEnabled()
            && ! in_array($event->paymentSetting?->currency, ['TZS', 'USD'], true)) {
            $errors[] = 'Select a supported registration currency.';
        }

        if ($event->ticketSetting?->ticket_sales_enabled) {
            $setting = $event->ticketSetting;
            if ($setting->sales_start_at && $setting->sales_end_at
                && $setting->sales_end_at->lte($setting->sales_start_at)) {
                $errors[] = 'Ticket sales must end after they start.';
            }

            $types = $event->publicTicketTypes()->get();
            if ($types->isEmpty()) {
                $errors[] = 'Create at least one active public ticket type before opening sales.';
            }

            foreach ($types as $type) {
                if ((float) $type->price < 0 || ! in_array($type->currency, ['TZS', 'USD'], true)) {
                    $errors[] = 'Invalid price or currency for ticket type: '.$type->name.'.';
                }
                if ($type->min_per_order < 1 || $type->max_per_order < $type->min_per_order) {
                    $errors[] = 'Invalid order limits for ticket type: '.$type->name.'.';
                }
                if ($type->sales_start_at && $type->sales_end_at
                    && $type->sales_end_at->lte($type->sales_start_at)) {
                    $errors[] = 'Invalid sales dates for ticket type: '.$type->name.'.';
                }
            }
        }

        return $errors;
    }
}
