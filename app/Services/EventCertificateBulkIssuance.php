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

        $connection = config('queue.default');
        abort_unless(in_array(config('queue.connections.'.$connection.'.driver'), ['database', 'redis'], true), 503, 'Bulk issuance requires an asynchronous queue connection.');
        [$items, $rule] = \Illuminate\Support\Facades\DB::transaction(function () use ($access, $user, $eventId, $templateId, $attendeeIds): array {
            \App\Models\Event::query()->whereKey($eventId)->lockForUpdate()->firstOrFail();
            $event = $access->authorize($user, $eventId);
            $settings = $event->certificateSettings()->first();
            abort_unless($settings?->enabled && ! $event->isCancelled(), 422, 'Certificates are unavailable.');
            $event->certificateTemplates()->whereKey($templateId)->where('is_active', true)->firstOrFail();
            $ids = $access->eligibleAttendees($user, $eventId, $attendeeIds)
                ->whereIn('attendees.id', $attendeeIds)->whereDoesntHave('certificates')
                ->pluck('attendees.id')->all();
            if (count($ids) !== count($attendeeIds)) {
                throw ValidationException::withMessages(['attendees' => 'One or more selected attendees are no longer eligible or already have a certificate. Refresh the list.']);
            }
            $reserved = \Illuminate\Support\Facades\DB::table('event_certificate_batch_items as item')
                ->join('event_certificate_batches as batch', 'batch.id', '=', 'item.event_certificate_batch_id')
                ->where('batch.event_id', $eventId)->whereIn('item.attendee_id', $ids)
                ->whereIn('item.status', ['queued', 'processing', 'failed'])->exists();
            if ($reserved) {
                throw ValidationException::withMessages(['attendees' => 'One or more attendees already belong to an unfinished issuance batch. Review or retry the existing batch.']);
            }
            $now = now();
            $batchId = \Illuminate\Support\Facades\DB::table('event_certificate_batches')->insertGetId([
                'event_id' => $eventId, 'event_certificate_template_id' => $templateId,
                'actor_id' => $user->id, 'eligibility_rule' => $settings->eligibility_rule,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $items = [];
            foreach ($ids as $id) {
                $items[(int) $id] = \Illuminate\Support\Facades\DB::table('event_certificate_batch_items')->insertGetId([
                    'event_certificate_batch_id' => $batchId, 'attendee_id' => $id,
                    'status' => 'queued', 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
            return [$items, $settings->eligibility_rule];
        });

        foreach ($items as $id => $itemId) {
            try {
                IssueEventCertificate::dispatch($user->id, $eventId, $id, $templateId, $rule, $itemId)->afterCommit();
            } catch (\Throwable $exception) {
                report($exception);
                \Illuminate\Support\Facades\DB::table('event_certificate_batch_items')->where('id', $itemId)
                    ->where('status', 'queued')->update(['status' => 'failed', 'failure_code' => 'dispatch_failed', 'updated_at' => now()]);
            }
        }

        return count($items);
    }
}
