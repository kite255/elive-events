<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventCertificate;
use App\Models\EventCertificateAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class EventCertificateIssuance
{
    /**
     * Prepare a pending record only. No PDF, public verification or delivery
     * is authorized until a separate finalization workflow is implemented.
     */
    public function prepare(User $user, int $eventId, int $attendeeId, int $templateId, bool $manualSelection = false): EventCertificate
    {
        $access = app(EventCertificateAccess::class);
        $access->authorize($user, $eventId);

        return DB::transaction(function () use ($access, $user, $eventId, $attendeeId, $templateId, $manualSelection): EventCertificate {
            Event::query()->whereKey($eventId)->lockForUpdate()->firstOrFail();
            $event = $access->authorize($user, $eventId);
            $settings = $event->certificateSettings()->first();
            abort_unless($settings?->enabled && ! $event->isCancelled(), 422, 'Certificates are unavailable for this event.');
            $template = $event->certificateTemplates()->whereKey($templateId)
                ->where('is_active', true)->lockForUpdate()->firstOrFail();
            $attendee = $access->eligibleAttendees($user, $eventId, $manualSelection ? [$attendeeId] : [])
                ->whereKey($attendeeId)->lockForUpdate()->firstOrFail();

            if ($event->certificates()->where('attendee_id', $attendeeId)->exists()) {
                throw ValidationException::withMessages(['attendee' => 'A certificate record already exists for this attendee.']);
            }

            $definition = $template->definition ?? [];
            abort_unless(($definition['version'] ?? null) === 1 && is_array($definition['elements'] ?? null), 422, 'Invalid published layout.');
            $types = array_column($definition['elements'], 'type');
            abort_if(array_diff(['attendee_name', 'certificate_number', 'qr'], $types), 422, 'Incomplete published layout.');
            abort_if(trim((string) $attendee->full_name) === '', 422, 'Attendee name is required.');

            $assets = [];
            $disk = Storage::disk('local');
            $prefix = 'certificate-drafts/event-'.$eventId.'/template-'.$templateId.'/';
            foreach ($definition['elements'] as $element) {
                if ($element['type'] !== 'image') {
                    continue;
                }
                $asset = $template->assets()->whereKey($element['asset_id'] ?? 0)->first();
                $path = $asset?->file_path;
                abort_unless(is_string($path) && str_starts_with($path, $prefix) && ! str_contains($path, '..')
                    && $disk->exists($path) && $disk->size($path) <= 2097152, 422, 'Certificate image unavailable.');
                $mime = $disk->mimeType($path);
                $size = @getimagesize($disk->path($path));
                abort_unless(in_array($mime, ['image/png', 'image/jpeg'], true) && $size
                    && $size[0] >= 1 && $size[1] >= 1 && $size[0] <= 4000 && $size[1] <= 4000, 422, 'Invalid certificate image.');
                $assets[$asset->id] = [
                    'path' => $path, 'mime_type' => $mime,
                    'sha256' => hash('sha256', $disk->get($path)),
                    'width' => $size[0], 'height' => $size[1],
                ];
            }

            $certificate = new EventCertificate;
            $certificate->forceFill([
                'event_id' => $eventId,
                'attendee_id' => $attendeeId,
                'event_certificate_template_id' => $templateId,
                'certificate_number' => 'ELC-'.$eventId.'-'.strtoupper(bin2hex(random_bytes(10))),
                'verification_token' => bin2hex(random_bytes(32)),
                'status' => EventCertificate::STATUS_PENDING,
                'issuance_snapshot' => [
                    'version' => 1,
                    'attendee_name' => $attendee->full_name,
                    'event_name' => $event->name,
                    'event_date' => $event->starts_at?->format('d M Y'),
                    'organization_name' => $event->organization?->name,
                    'eligibility_rule' => $settings->eligibility_rule,
                    'attendee_status' => $attendee->status,
                    'checked_in_at' => $attendee->checked_in_at?->toIso8601String(),
                    'prepared_at' => now()->toIso8601String(),
                    'template' => [
                        'name' => $template->name, 'width' => $template->width,
                        'height' => $template->height, 'definition' => $definition,
                        'assets' => $assets,
                    ],
                ],
            ])->save();

            $audit = new EventCertificateAudit;
            $audit->forceFill([
                'event_certificate_id' => $certificate->id,
                'actor_id' => $user->id,
                'action' => 'prepared',
                'metadata' => ['eligibility_rule' => $settings->eligibility_rule, 'manual_selection' => $manualSelection],
            ])->save();

            return $certificate;
        });
    }
}
