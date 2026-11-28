<?php

namespace App\Services;

use App\Jobs\IssueEventCertificate;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class EventCertificateBulkIssuance
{
    public function queue(User $user, int $eventId, int $templateId, array $attendeeIds): int
    {
        $access = app(EventCertificateAccess::class);
        $event = $access->authorize($user, $eventId);
        Validator::make(['attendees' => $attendeeIds], [
            'attendees' => ['required', 'array', 'min:1', 'max:100'],
            'attendees.*' => ['required', 'integer', 'min:1', 'distinct'],
        ])->validate();

        $settings = $event->certificateSettings()->first();
        abort_unless($settings?->enabled && ! $event->isCancelled(), 422, 'Certificates are unavailable.');
        $event->certificateTemplates()->whereKey($templateId)->where('is_active', true)->firstOrFail();
        $ids = $access->eligibleAttendees($user, $eventId, $attendeeIds)
            ->whereIn('attendees.id', $attendeeIds)->whereDoesntHave('certificates')
            ->pluck('attendees.id')->all();
        if (count($ids) !== count($attendeeIds)) {
            throw ValidationException::withMessages(['attendees' => 'One or more selected attendees are no longer eligible or already have a certificate. Refresh the list.']);
        }

        $connection = config('queue.default');
        abort_unless(in_array(config('queue.connections.'.$connection.'.driver'), ['database', 'redis'], true), 503, 'Bulk issuance requires an asynchronous queue connection.');
        foreach ($ids as $id) {
            IssueEventCertificate::dispatch($user->id, $eventId, (int) $id, $templateId, $settings->eligibility_rule)->afterCommit();
        }

        return count($ids);
    }
}
