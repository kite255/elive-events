<?php

namespace App\Jobs;

use App\Models\EventCertificate;
use App\Models\User;
use App\Services\EventCertificateAccess;
use App\Services\EventCertificateIssuance;
use App\Services\EventCertificateFinalization;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

final class IssueEventCertificate implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;
    public int $timeout = 60;
    public array $backoff = [15, 30, 60, 120];

    public function __construct(
        public int $actorId,
        public int $eventId,
        public int $attendeeId,
        public int $templateId,
        public string $eligibilityRule,
        public ?int $batchItemId = null,
    ) {
        $this->onQueue(config('queue.certificate_queue', 'default'));
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('certificate:'.$this->eventId.':'.$this->attendeeId))->releaseAfter(15)->expireAfter(120)];
    }

    public function handle(EventCertificateAccess $access, EventCertificateIssuance $issuance, EventCertificateFinalization $finalization): void
    {
        if ($this->batchItemId !== null) {
            $item = \Illuminate\Support\Facades\DB::table('event_certificate_batch_items as item')
                ->join('event_certificate_batches as batch', 'batch.id', '=', 'item.event_certificate_batch_id')
                ->where('item.id', $this->batchItemId)->where('item.attendee_id', $this->attendeeId)
                ->where('batch.event_id', $this->eventId)->where('batch.event_certificate_template_id', $this->templateId)
                ->where('batch.actor_id', $this->actorId)->where('batch.eligibility_rule', $this->eligibilityRule)
                ->select('item.status')->first();
            abort_unless($item, 422, 'Invalid batch item.');
            if (in_array($item->status, ['issued', 'skipped'], true)) {
                return;
            }
            $claimed = \Illuminate\Support\Facades\DB::table('event_certificate_batch_items')->where('id', $this->batchItemId)
                ->whereIn('status', ['queued', 'processing'])
                ->update(['status' => 'processing', 'attempts' => \Illuminate\Support\Facades\DB::raw('attempts + 1'), 'failure_code' => null, 'updated_at' => now()]);
            if (! $claimed) {
                return;
            }
        }
        $actor = User::query()->findOrFail($this->actorId);
        $event = $access->authorize($actor, $this->eventId);
        $settings = $event->certificateSettings()->first();
        abort_unless($settings?->enabled && ! $event->isCancelled()
            && $settings->eligibility_rule === $this->eligibilityRule, 422, 'Certificate eligibility settings changed.');
        $manual = $this->eligibilityRule === 'manual';
        $certificate = $event->certificates()->where('attendee_id', $this->attendeeId)->first();
        if ($certificate && $certificate->status !== EventCertificate::STATUS_PENDING) {
            $this->mark($certificate->status === EventCertificate::STATUS_ISSUED && (int) $certificate->event_certificate_template_id === $this->templateId ? 'issued' : 'skipped');
            return;
        }
        if (! $certificate) {
            $certificate = $issuance->prepare($actor, $this->eventId, $this->attendeeId, $this->templateId, $manual);
        }
        abort_unless((int) $certificate->event_certificate_template_id === $this->templateId, 422, 'Pending certificate uses another template.');
        $finalization->finalize($actor, $this->eventId, $certificate->id, $manual);
        $this->mark('issued');
    }

    private function mark(string $status, ?string $failureCode = null): void
    {
        if ($this->batchItemId === null) {
            return;
        }
        \Illuminate\Support\Facades\DB::table('event_certificate_batch_items')->where('id', $this->batchItemId)
            ->whereNotIn('status', ['issued', 'skipped'])
            ->update(['status' => $status, 'failure_code' => $failureCode, 'updated_at' => now()]);
    }

    public function failed(?\Throwable $exception): void
    {
        $this->mark('failed', 'processing_failed');
    }
}
