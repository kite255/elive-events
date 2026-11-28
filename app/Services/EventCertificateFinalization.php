<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventCertificate;
use App\Models\EventCertificateAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final class EventCertificateFinalization
{
    public function finalize(User $user, int $eventId, int $certificateId, bool $manualSelection = false): EventCertificate
    {
        $access = app(EventCertificateAccess::class);
        $access->authorize($user, $eventId);
        // Own the outer transaction so a later caller rollback cannot orphan the PDF.
        abort_if(DB::transactionLevel() > 0 && ! app()->runningUnitTests(), 422, 'Finalize outside an existing transaction.');
        $disk = Storage::disk('local');
        $path = null;

        // Render the immutable pending snapshot before taking the event-wide lock.
        // Revalidate all mutable eligibility and authorization conditions at commit.
        $candidate = $this->validatedCandidate($user, $eventId, $certificateId, $manualSelection, false);
        $issuedAt = now();
        $pdf = app(EventCertificatePdf::class)->render($candidate, $issuedAt);
        $hash = hash('sha256', $pdf);

        try {
            $path = 'certificates/event-'.$eventId.'/certificate-'.$candidate->id.'/'.Str::uuid().'.pdf';
            abort_unless($disk->put($path, $pdf, 'private'), 503, 'Certificate storage failed.');
            $stored = $disk->get($path);
            abort_unless(is_string($stored) && hash_equals($hash, hash('sha256', $stored)), 503, 'Certificate storage integrity check failed.');
            unset($pdf, $stored);

            return DB::transaction(function () use ($user, $eventId, $certificateId, $manualSelection, $candidate, $issuedAt, $hash, $path): EventCertificate {
                $certificate = $this->validatedCandidate($user, $eventId, $certificateId, $manualSelection, true);
                abort_unless($certificate->issuance_snapshot === $candidate->issuance_snapshot
                    && $certificate->certificate_number === $candidate->certificate_number
                    && $certificate->verification_token === $candidate->verification_token
                    && (int) $certificate->attendee_id === (int) $candidate->attendee_id
                    && (int) $certificate->event_certificate_template_id === (int) $candidate->event_certificate_template_id,
                    422, 'Certificate snapshot changed during rendering.');
                abort_unless($issuedAt->toDateString() === now()->toDateString(), 422, 'Issuance date changed during rendering. Retry.');
                $certificate->forceFill([
                    'pdf_path' => $path,
                    'status' => EventCertificate::STATUS_ISSUED,
                    'issued_by' => $user->id,
                    'issued_at' => $issuedAt,
                ])->save();
                $audit = new EventCertificateAudit;
                $audit->forceFill([
                    'event_certificate_id' => $certificate->id,
                    'actor_id' => $user->id,
                    'action' => 'issued',
                    'metadata' => ['pdf_sha256' => $hash, 'eligibility_rule' => $certificate->issuance_snapshot['eligibility_rule'], 'manual_selection' => $manualSelection],
                ])->save();

                return $certificate;
            });
        } catch (Throwable $exception) {
            if ($path !== null) {
                try {
                    // Do not delete a file referenced by a committed record.
                    if (! EventCertificate::query()->where('pdf_path', $path)->exists()) {
                        $disk->delete($path);
                    }
                } catch (Throwable $cleanupException) {
                    report($cleanupException);
                }
            }
            throw $exception;
        }
    }

    private function validatedCandidate(User $user, int $eventId, int $certificateId, bool $manualSelection, bool $lock): EventCertificate
    {
        $access = app(EventCertificateAccess::class);
        if ($lock) {
            Event::query()->whereKey($eventId)->lockForUpdate()->firstOrFail();
        }
        // Reload the actor so role changes during PDF rendering are respected.
        $user = User::query()->findOrFail($user->id);
        $event = $access->authorize($user, $eventId);
        $settings = $event->certificateSettings()->first();
        abort_unless($settings?->enabled && ! $event->isCancelled(), 422, 'Certificates are unavailable for this event.');
        $query = $event->certificates()->whereKey($certificateId);
        $certificate = ($lock ? $query->lockForUpdate() : $query)->firstOrFail();
        abort_unless($certificate->status === EventCertificate::STATUS_PENDING && ! $certificate->pdf_path && ! $certificate->issued_at, 422, 'Only pending certificates can be finalized.');
        $template = $event->certificateTemplates()->whereKey($certificate->event_certificate_template_id)->where('is_active', true);
        ($lock ? $template->lockForUpdate() : $template)->firstOrFail();
        $attendee = $access->eligibleAttendees($user, $eventId, $manualSelection ? [$certificate->attendee_id] : [])
            ->whereKey($certificate->attendee_id);
        $attendee = ($lock ? $attendee->lockForUpdate() : $attendee)->firstOrFail();
        $snapshot = $certificate->issuance_snapshot;
        abort_unless($snapshot['attendee_name'] === $attendee->full_name
            && $snapshot['eligibility_rule'] === $settings->eligibility_rule, 422, 'Attendee name or eligibility settings changed after preparation. Review the pending record.');

        return $certificate;
    }

}
