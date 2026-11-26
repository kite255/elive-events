<?php

namespace App\Services;

use App\Models\Attendee;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RegistrationStatusService
{
    public function transition(Attendee $attendee, User $user, string $expected, string $target): Attendee
    {
        return DB::transaction(function () use ($attendee, $user, $expected, $target): Attendee {
            $event = Event::query()->whereKey($attendee->event_id)->lockForUpdate()->firstOrFail();
            abort_unless($event->isAccessibleBy($user) && $event->canManageRegistrationBy($user) && ! $user->is_gate_staff, 403);

            $record = Attendee::query()->where('event_id', $event->id)->whereKey($attendee->id)->lockForUpdate()->firstOrFail();

            if ($record->status !== $expected) {
                throw ValidationException::withMessages(['status' => 'Registration status has changed. Refresh and try again.']);
            }

            if (in_array($target, ['registered', 'pending_approval'], true) && $expected === 'waitlisted' && $event->isRegistrationFull()) {
                throw ValidationException::withMessages(['status' => 'The event is at capacity. This attendee must remain on the waitlist.']);
            }

            if (! (($target === 'registered' && in_array($expected, ['pending_approval', 'waitlisted'], true))
                || ($target === 'rejected' && in_array($expected, ['pending_approval', 'registered', 'waitlisted'], true)))) {
                throw ValidationException::withMessages(['status' => 'This registration status change is not allowed.']);
            }

            // Promotion reserves capacity but does not bypass an event's approval policy.
            // A subsequent explicit approval can move pending_approval to registered.
            if ($expected === 'waitlisted' && $target === 'registered' && $event->registration_requires_approval) {
                $target = 'pending_approval';
            }

            $record->forceFill(['status' => $target])->save();

            return $record;
        }, attempts: 3);
    }
}
