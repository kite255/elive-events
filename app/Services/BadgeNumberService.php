<?php

namespace App\Services;

use App\Models\Attendee;
use App\Models\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BadgeNumberService
{
    /**
     * Generate a unique event code.
     *
     * Example:
     * Dar es Salaam Camp Meeting 2026
     * -> DSC26
     */
    public function generateEventCode(Event $event): string
    {
        $year = $event->starts_at
            ? $event->starts_at->format('y')
            : now()->format('y');

        $words = collect(
            preg_split(
                '/\s+/',
                strtoupper(trim($event->name))
            )
        )
            ->filter()
            ->reject(
                fn (string $word) => in_array(
                    $word,
                    [
                        'THE',
                        'AND',
                        'OF',
                        'FOR',
                        'IN',
                        'AT',
                        'A',
                        'AN',
                    ],
                    true
                )
            )
            ->take(3)
            ->map(
                fn (string $word) => Str::substr(
                    $word,
                    0,
                    1
                )
            )
            ->implode('');

        $baseCode =
            ($words ?: 'EVT')
            . $year;

        $code = $baseCode;
        $counter = 1;

        while (
            Event::query()
                ->where(
                    'event_code',
                    $code
                )
                ->where(
                    'id',
                    '!=',
                    $event->id
                )
                ->exists()
        ) {
            $code =
                $baseCode
                . '-'
                . $counter;

            $counter++;
        }

        return strtoupper($code);
    }

    /**
     * Ensure an event has a unique event code.
     */
    public function assignEventCode(
        Event $event
    ): Event {
        if (
            ! blank(
                $event->event_code
            )
        ) {
            $event->event_code =
                strtoupper(
                    trim(
                        $event->event_code
                    )
                );

            $event->save();

            return $event;
        }

        $event->event_code =
            $this->generateEventCode(
                $event
            );

        $event->save();

        return $event;
    }

    /**
     * Assign an event-scoped attendee badge / serial number.
     *
     * Examples:
     *
     * DCC26-000001
     * DCC26-000002
     * DCC26-000003
     */
    public function assignBadgeNumber(
        Attendee $attendee
    ): Attendee {
        return DB::transaction(
            function () use ($attendee) {
                $attendee =
                    Attendee::query()
                        ->whereKey(
                            $attendee->id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                /*
                 * Lock the event row.
                 *
                 * This serializes badge-number generation
                 * for attendees belonging to the same event.
                 */
                $event =
                    Event::query()
                        ->whereKey(
                            $attendee->event_id
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                $this->assignEventCode(
                    $event
                );

                /*
                 * Never regenerate an existing number.
                 */
                if (
                    ! blank(
                        $attendee->badge_number
                    )
                    && ! blank(
                        $attendee->event_sequence
                    )
                ) {
                    return $attendee;
                }

                /*
                 * Determine the next sequence for this event.
                 */
                $nextSequence =
                    (
                        (int) Attendee::query()
                            ->where(
                                'event_id',
                                $event->id
                            )
                            ->max(
                                'event_sequence'
                            )
                    ) + 1;

                $attendee->event_sequence =
                    $nextSequence;

                /*
                 * Human-readable attendee serial.
                 *
                 * Example:
                 * DCC26-000001
                 */
                $attendee->badge_number =
                    sprintf(
                        '%s-%06d',
                        strtoupper(
                            $event->event_code
                        ),
                        $nextSequence
                    );

                $attendee->save();

                return $attendee;
            }
        );
    }
}
