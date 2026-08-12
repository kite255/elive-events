<?php

namespace App\Services;

use App\Models\Attendee;
use App\Models\Event;

class MessagePlaceholderService
{
    /*
    |--------------------------------------------------------------------------
    | Render Message
    |--------------------------------------------------------------------------
    */

    public function render(
        string $content,
        Attendee $attendee
    ): string {
        $attendee->loadMissing([
            'event.organization',
            'category',
            'badgeType',
        ]);

        return strtr(
            $content,
            $this->replacements(
                $attendee,
                $attendee->event
            )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Render Nullable Message
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | Available Placeholders
    |--------------------------------------------------------------------------
    */

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
            '#BADGE_LINK#',

            '#EVENT_NAME#',
            '#EVENT_VENUE#',
            '#EVENT_DATE#',
            '#EVENT_TIME#',

            '#PUBLIC_LINK#',
            '#REGISTRATION_LINK#',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Placeholder Replacements
    |--------------------------------------------------------------------------
    */

    protected function replacements(
        Attendee $attendee,
        ?Event $event
    ): array {
        return [
            '#NAME#' =>
                trim(
                    (string) (
                        $attendee->full_name
                        ?? ''
                    )
                ),

            '#PHONE#' =>
                trim(
                    (string) (
                        $attendee->phone
                        ?? ''
                    )
                ),

            '#EMAIL#' =>
                trim(
                    (string) (
                        $attendee->email
                        ?? ''
                    )
                ),

            '#ORGANIZATION#' =>
                trim(
                    (string) (
                        $attendee->organization_name
                        ?? ''
                    )
                ),

            '#POSITION#' =>
                trim(
                    (string) (
                        $attendee->position
                        ?? ''
                    )
                ),

            '#CATEGORY#' =>
                trim(
                    (string) (
                        $attendee->category?->name
                        ?? ''
                    )
                ),

            '#PARTICIPANT_TYPE#' =>
                trim(
                    (string) (
                        $attendee->category?->name
                        ?? ''
                    )
                ),

            '#BADGE_TYPE#' =>
                trim(
                    (string) (
                        $attendee->badgeType?->name
                        ?? ''
                    )
                ),

            '#BADGE_NUMBER#' =>
                trim(
                    (string) (
                        $attendee->badge_number
                        ?? ''
                    )
                ),

            '#BADGE_LINK#' =>
                $this->badgeLink(
                    $attendee
                ),

            '#EVENT_NAME#' =>
                trim(
                    (string) (
                        $event?->name
                        ?? ''
                    )
                ),

            '#EVENT_VENUE#' =>
                trim(
                    (string) (
                        $event?->venue
                        ?? ''
                    )
                ),

            '#EVENT_DATE#' =>
                $this->eventDate(
                    $event
                ),

            '#EVENT_TIME#' =>
                $this->eventTime(
                    $event
                ),

            '#PUBLIC_LINK#' =>
                $this->publicLink(
                    $attendee
                ),

            '#REGISTRATION_LINK#' =>
                $this->registrationLink(
                    $event
                ),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Event Date
    |--------------------------------------------------------------------------
    */

    protected function eventDate(
        ?Event $event
    ): string {
        if (! $event?->starts_at) {
            return '';
        }

        if (
            $event->ends_at
            && ! $event->starts_at->isSameDay(
                $event->ends_at
            )
        ) {
            return $event->starts_at->format(
                'd M Y'
            )
                . ' - '
                . $event->ends_at->format(
                    'd M Y'
                );
        }

        return $event->starts_at->format(
            'd M Y'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Event Time
    |--------------------------------------------------------------------------
    */

    protected function eventTime(
        ?Event $event
    ): string {
        if (! $event?->starts_at) {
            return '';
        }

        if ($event->ends_at) {
            return $event->starts_at->format(
                'H:i'
            )
                . ' - '
                . $event->ends_at->format(
                    'H:i'
                );
        }

        return $event->starts_at->format(
            'H:i'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Public Attendee Link
    |--------------------------------------------------------------------------
    */

    protected function publicLink(
        Attendee $attendee
    ): string {
        if (blank($attendee->public_token)) {
            return '';
        }

        if (! method_exists(
            $attendee,
            'publicUrl'
        )) {
            return '';
        }

        return (string) (
            $attendee->publicUrl()
            ?? ''
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Badge Link
    |--------------------------------------------------------------------------
    */

    protected function badgeLink(
        Attendee $attendee
    ): string {
        /*
         * The attendee must have a generated badge before
         * there is anything useful to send.
         */
        if (blank($attendee->badge_path)) {
            return '';
        }

        if (! method_exists(
            $attendee,
            'badgeUrl'
        )) {
            return '';
        }

        return (string) (
            $attendee->badgeUrl()
            ?? ''
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Registration Link
    |--------------------------------------------------------------------------
    */

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