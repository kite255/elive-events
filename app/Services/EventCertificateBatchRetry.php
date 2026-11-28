<?php

namespace App\Services;

use App\Jobs\IssueEventCertificate;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Bus;
use Throwable;

final class EventCertificateBatchRetry
{
    public function retry(User $user, int $eventId, int $batchId): int
    {
        $event = app(EventCertificateAccess::class)->authorize($user, $eventId);
        $settings = $event->certificateSettings()->first();
        $batch = DB::table('event_certificate_batches')->where('event_id', $eventId)->where('id', $batchId)->first();
        abort_unless($batch, 404);
        // Preserve the original actor and authorization context.
        abort_unless((int) $batch->actor_id === (int) $user->id, 403, 'Only the original submitting manager can retry this batch.');
        abort_unless($settings?->enabled && ! $event->isCancelled() && $settings->eligibility_rule === $batch->eligibility_rule, 422);
        $event->certificateTemplates()->whereKey($batch->event_certificate_template_id)->where('is_active', true)->firstOrFail();
        abort_unless(in_array(config('queue.connections.'.config('queue.default').'.driver'), ['redis', 'database'], true), 503);
        abort_if(DB::transactionLevel() > 0 && ! app()->runningUnitTests(), 422);

        $submitted = 0;
        $items = DB::table('event_certificate_batch_items')->where('event_certificate_batch_id', $batchId)->where('status', 'failed')->get();
        foreach ($items as $item) {
            $claimed = DB::table('event_certificate_batch_items')->where('id', $item->id)
                ->where('status', 'failed')->update(['status' => 'queued', 'failure_code' => null, 'updated_at' => now()]);
            if (! $claimed) {
                continue;
            }
            try {
                Bus::dispatch(new IssueEventCertificate((int) $user->id, $eventId, (int) $item->attendee_id,
                    (int) $batch->event_certificate_template_id, $batch->eligibility_rule, (int) $item->id));
                $submitted++;
            } catch (Throwable $exception) {
                report($exception);
                DB::table('event_certificate_batch_items')->where('id', $item->id)->where('status', 'queued')
                    ->update(['status' => 'failed', 'failure_code' => 'dispatch_failed', 'updated_at' => now()]);
            }
        }

        return $submitted;
    }
}
