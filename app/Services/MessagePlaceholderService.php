<?php

namespace App\Services;

use App\Models\Attendee;
use App\Models\Event;

class MessagePlaceholderService
{
    public function render(
        string $content,
        Attendee $attendee
    ): string {
        $attendee->loadMissing([
            'event',
            'category',
            'badgeType',
        ]);

        $event = $attendee->event;

        return strtr(
            $content,
            $this->replacements(
                $attendee,
                $event
            )
        );
    }

    public function renderNullable(
        ?string $content,
        Attendee $attendee
    ): ?string {
        if (blank($content)) {
            return null;
        }

        return $this->render(
            $content,
            $attendee
        );
    }

    public function placeholders(): array
    {
        return [
            '#NAME#',
            '#PHONE#',
            '#EMAIL#',
            '#ORGANIZATION#',
            '#POSITION#',
            '#CATEGORY#',
            '#PARTICIPANT_TYPE#',
            '#BADGE_TYPE#',
            '#BADGE_NUMBER#',
            '#EVENT_NAME#',
            '#EVENT_VENUE#',
            '#EVENT_DATE#',
            '#EVENT_TIME#',
            '#PUBLIC_LINK#',
            '#BADGE_LINK#',
            '#REGISTRATION_LINK#',
        ];
    }

    protected function replacements(
        Attendee $attendee,
        ?Event $event
    ): array {
        return [
            '#NAME#' =>
                $attendee->full_name ?? '',

            '#PHONE#' =>
                $attendee->phone ?? '',

            '#EMAIL#' =>
                $attendee->email ?? '',

            '#ORGANIZATION#' =>
                $attendee->organization_name ?? '',

            '#POSITION#' =>
                $attendee->position ?? '',

            '#CATEGORY#' =>
                $attendee->category?->name ?? '',

            '#PARTICIPANT_TYPE#' =>
                $attendee->category?->name ?? '',

            '#BADGE_TYPE#' =>
                $attendee->badgeType?->name ?? '',

            '#BADGE_NUMBER#' =>
                $attendee->badge_number ?? '',

            '#EVENT_NAME#' =>
                $event?->name ?? '',

            '#EVENT_VENUE#' =>
                $event?->venue ?? '',

            '#EVENT_DATE#' =>
                $this->eventDate($event),

            '#EVENT_TIME#' =>
                $this->eventTime($event),

            '#PUBLIC_LINK#' =>
                $this->publicLink($attendee),

            '#BADGE_LINK#' =>
                $this->badgeLink($attendee),

            '#REGISTRATION_LINK#' =>
                $this->registrationLink($event),
        ];
    }

    protected function eventDate(
        ?Event $event
    ): string {
        if (! $event?->starts_at) {
            return '';
        }

        return $event->starts_at->format(
            'd M Y'
        );
    }

    protected function eventTime(
        ?Event $event
    ): string {
        if (! $event?->starts_at) {
            return '';
        }

        return $event->starts_at->format(
            'H:i'
        );
    }

    protected function publicLink(
        Attendee $attendee
    ): string {
        if (blank($attendee->public_token)) {
            return '';
        }

        return $attendee->publicUrl();
    }

    protected function badgeLink(
        Attendee $attendee
    ): string {
        return $attendee->badgeUrl() ?? '';
    }

    protected function registrationLink(
        ?Event $event
    ): string {
        if (
            ! $event
            || blank($event->slug)
        ) {
            return '';
        }

        return url(
            '/events/'
            . $event->slug
            . '/register'
        );
    }
}