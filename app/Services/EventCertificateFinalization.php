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

        try {
            return DB::transaction(function () use ($access, $user, $eventId, $certificateId, $manualSelection, $disk, &$path): EventCertificate {
                Event::query()->whereKey($eventId)->lockForUpdate()->firstOrFail();
                $event = $access->authorize($user, $eventId);
                $settings = $event->certificateSettings()->first();
                abort_unless($settings?->enabled && ! $event->isCancelled(), 422, 'Certificates are unavailable for this event.');
                $certificate = $event->certificates()->whereKey($certificateId)->lockForUpdate()->firstOrFail();
                abort_unless($certificate->status === EventCertificate::STATUS_PENDING && ! $certificate->pdf_path && ! $certificate->issued_at, 422, 'Only pending certificates can be finalized.');
                $event->certificateTemplates()->whereKey($certificate->event_certificate_template_id)
                    ->where('is_active', true)->lockForUpdate()->firstOrFail();
                $attendee = $access->eligibleAttendees($user, $eventId, $manualSelection ? [$certificate->attendee_id] : [])
                    ->whereKey($certificate->attendee_id)->lockForUpdate()->firstOrFail();
                $snapshot = $certificate->issuance_snapshot;
                abort_unless($snapshot['attendee_name'] === $attendee->full_name
                    && $snapshot['eligibility_rule'] === $settings->eligibility_rule, 422, 'Attendee name or eligibility settings changed after preparation. Review the pending record.');
                $issuedAt = now();
                $pdf = app(EventCertificatePdf::class)->render($certificate, $issuedAt);
                $path = 'certificates/event-'.$eventId.'/certificate-'.$certificate->id.'/'.Str::uuid().'.pdf';
                abort_unless($disk->put($path, $pdf, 'private'), 503, 'Certificate storage failed.');
                $stored = $disk->get($path);
                $hash = hash('sha256', $pdf);
                abort_unless(is_string($stored) && hash_equals($hash, hash('sha256', $stored)), 503, 'Certificate storage integrity check failed.');

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
                    'metadata' => ['pdf_sha256' => $hash, 'eligibility_rule' => $settings->eligibility_rule, 'manual_selection' => $manualSelection],
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
}
