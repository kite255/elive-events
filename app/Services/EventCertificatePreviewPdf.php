<?php

namespace App\Services;

use App\Models\EventCertificateTemplate;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

final class EventCertificatePreviewPdf
{
    public function render(User $user, int $eventId, int $templateId): string
    {
        $event = app(EventCertificateAccess::class)->authorize($user, $eventId);
        $template = $event->certificateTemplates()->whereKey($templateId)
            ->where('is_active', false)->firstOrFail();
        abort_if($template->certificates()->exists(), 422);
        $definition = $template->definition ?? [];
        abort_unless(($definition['version'] ?? null) === 1 && ! empty($definition['elements']), 422, 'Save a non-empty certificate design first.');
        $disk = Storage::disk('local');
        $prefix = 'certificate-drafts/event-'.$eventId.'/template-'.$templateId.'/';
        $images = [];
        foreach ($template->assets()->get() as $asset) {
            $path = $asset->file_path;
            if (! str_starts_with($path, $prefix) || str_contains($path, '..')
                || ! $disk->exists($path) || $disk->size($path) > 2097152) {
                continue;
            }
            $mime = $disk->mimeType($path);
            $size = @getimagesize($disk->path($path));
            if (! in_array($mime, ['image/png', 'image/jpeg'], true) || ! $size
                || $size[0] < 1 || $size[1] < 1 || $size[0] > 4000 || $size[1] > 4000) {
                continue;
            }
            $images[$asset->id] = ['src' => 'data:'.$mime.';base64,'.base64_encode($disk->get($path)), 'width' => $size[0], 'height' => $size[1]];
        }
        foreach ($definition['elements'] as $element) {
            abort_if($element['type'] === 'image' && ! isset($images[$element['asset_id'] ?? 0]), 422, 'A certificate image is unavailable.');
        }
        $values = [
            'attendee_name' => 'Sample Attendee',
            'event_name' => $event->name,
            'event_date' => $event->starts_at?->format('d M Y') ?? 'Event date',
            'organization_name' => $event->organization?->name ?? 'Organization name',
            'certificate_number' => 'SAMPLE-NOT-ISSUED',
            'issued_date' => now()->format('d M Y'),
        ];

        return Pdf::loadView('exports.certificate-preview', compact('template', 'definition', 'images', 'values'))
            ->setOptions(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false, 'dpi' => 96])
            ->setPaper([0, 0, $template->width * 0.75, $template->height * 0.75])
            ->output();
    }
}
