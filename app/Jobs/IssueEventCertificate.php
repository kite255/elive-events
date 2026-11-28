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
    ) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('certificate:'.$this->eventId.':'.$this->attendeeId))->releaseAfter(15)->expireAfter(120)];
    }

    public function handle(EventCertificateAccess $access, EventCertificateIssuance $issuance, EventCertificateFinalization $finalization): void
    {
        $actor = User::query()->findOrFail($this->actorId);
        $event = $access->authorize($actor, $this->eventId);
        $settings = $event->certificateSettings()->first();
        abort_unless($settings?->enabled && ! $event->isCancelled()
            && $settings->eligibility_rule === $this->eligibilityRule, 422, 'Certificate eligibility settings changed.');
        $manual = $this->eligibilityRule === 'manual';
        $certificate = $event->certificates()->where('attendee_id', $this->attendeeId)->first();
        if ($certificate && $certificate->status !== EventCertificate::STATUS_PENDING) {
            return;
        }
        if (! $certificate) {
            $certificate = $issuance->prepare($actor, $this->eventId, $this->attendeeId, $this->templateId, $manual);
        }
        abort_unless((int) $certificate->event_certificate_template_id === $this->templateId, 422, 'Pending certificate uses another template.');
        $finalization->finalize($actor, $this->eventId, $certificate->id, $manual);
    }
}
