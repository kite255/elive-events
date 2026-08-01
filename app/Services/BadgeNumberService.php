<?php

namespace App\Services;

use App\Models\Attendee;
use App\Models\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BadgeNumberService
{
    public function generateEventCode(Event $event): string
    {
        $year = $event->starts_at
            ? $event->starts_at->format('y')
            : now()->format('y');

        $words = collect(preg_split('/\s+/', strtoupper($event->name)))
            ->filter()
            ->reject(fn (string $word) => in_array($word, [
                'THE', 'AND', 'OF', 'FOR', 'IN', 'AT', 'A', 'AN',
            ]))
            ->take(3)
            ->map(fn (string $word) => Str::substr($word, 0, 1))
            ->implode('');

        $baseCode = ($words ?: 'EVT') . $year;

        $code = $baseCode;
        $counter = 1;

        while (
            Event::where('event_code', $code)
                ->where('id', '!=', $event->id)
                ->exists()
        ) {
            $code = $baseCode . '-' . $counter;
            $counter++;
        }

        return strtoupper($code);
    }

    public function assignEventCode(Event $event): Event
    {
        if (! blank($event->event_code)) {
            $event->event_code = strtoupper($event->event_code);
            $event->save();

            return $event;
        }

        $event->event_code = $this->generateEventCode($event);
        $event->save();

        return $event;
    }

    public function assignBadgeNumber(Attendee $attendee): Attendee
    {
        return DB::transaction(function () use ($attendee) {
            $attendee = Attendee::query()
                ->whereKey($attendee->id)
                ->lockForUpdate()
                ->firstOrFail();

            $event = Event::query()
                ->whereKey($attendee->event_id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assignEventCode($event);

            if (! blank($attendee->badge_number) && ! blank($attendee->event_sequence)) {
                return $attendee;
            }

            $nextSequence = ((int) Attendee::query()
                ->where('event_id', $event->id)
                ->max('event_sequence')) + 1;

            $attendee->event_sequence = $nextSequence;
           $attendee->badge_number = sprintf(
    'ELV-%s-%04d',
    strtoupper($event->event_code),
    $nextSequence
);

            $attendee->save();

            return $attendee;
        });
    }
}