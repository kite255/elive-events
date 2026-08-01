<?php

namespace App\Services;

use App\Models\Attendee;
use App\Models\CheckIn;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CheckInService
{
    public function checkIn(
        Attendee $attendee,
        ?int $checkInPointId = null,
        string $method = 'manual',
        ?string $note = null
    ): array {
        return DB::transaction(function () use ($attendee, $checkInPointId, $method, $note) {
            $alreadyCheckedIn = $this->hasCheckedIn($attendee);

            if ($alreadyCheckedIn) {
                return [
                    'success' => false,
                    'status' => 'already_checked_in',
                    'message' => $attendee->full_name . ' has already checked in.',
                    'attendee' => $attendee->fresh(),
                    'check_in' => CheckIn::query()
                        ->where('event_id', $attendee->event_id)
                        ->where('attendee_id', $attendee->id)
                        ->latest()
                        ->first(),
                ];
            }

            $checkedInAt = now();

            $checkIn = CheckIn::create([
                'event_id' => $attendee->event_id,
                'attendee_id' => $attendee->id,
                'check_in_point_id' => $checkInPointId,
                'checked_in_by' => Auth::id(),
                'method' => $method,
                'checked_in_at' => $checkedInAt,
                'device_name' => request()->userAgent(),
                'ip_address' => request()->ip(),
                'note' => $note,
            ]);

            $attendee->update([
                'status' => 'checked_in',
                'checked_in_at' => $checkedInAt,
            ]);

            return [
                'success' => true,
                'status' => 'checked_in',
                'message' => $attendee->full_name . ' checked in successfully.',
                'attendee' => $attendee->fresh(),
                'check_in' => $checkIn,
            ];
        });
    }

    public function hasCheckedIn(Attendee $attendee): bool
    {
        return CheckIn::query()
            ->where('event_id', $attendee->event_id)
            ->where('attendee_id', $attendee->id)
            ->exists();
    }
}