<?php

namespace App\Services;

use App\Models\EventCertificate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

final class EventCertificatePdf
{
    /**
     * Internal renderer only: does not change status or publish the PDF.
     */
    public function render(EventCertificate $certificate, \DateTimeInterface $issuedAt): string
    {
        abort_unless($certificate->status === EventCertificate::STATUS_PENDING, 422);
        $snapshot = $certificate->issuance_snapshot;
        abort_unless(($snapshot['version'] ?? null) === 1, 422, 'Invalid certificate snapshot.');
        $template = (object) $snapshot['template'];
        $definition = $template->definition;
        $disk = Storage::disk('local');
        $prefix = 'certificate-drafts/event-'.$certificate->event_id.'/template-'.$certificate->event_certificate_template_id.'/';
        $images = [];
        foreach ($template->assets as $id => $asset) {
            $path = $asset['path'];
            abort_unless(str_starts_with($path, $prefix) && ! str_contains($path, '..')
                && $disk->exists($path) && $disk->size($path) <= 2097152, 422, 'Certificate image unavailable.');
            $bytes = $disk->get($path);
            abort_unless(is_string($bytes) && hash_equals($asset['sha256'], hash('sha256', $bytes))
                && in_array($asset['mime_type'], ['image/png', 'image/jpeg'], true), 422, 'Certificate image changed.');
            $images[$id] = [
                'src' => 'data:'.$asset['mime_type'].';base64,'.base64_encode($bytes),
                'width' => $asset['width'], 'height' => $asset['height'],
            ];
        }
        foreach ($definition['elements'] as $element) {
            abort_if($element['type'] === 'image' && ! isset($images[$element['asset_id'] ?? 0]), 422, 'Certificate image missing.');
        }

        $base = rtrim((string) config('app.url'), '/');
        abort_unless(filter_var($base, FILTER_VALIDATE_URL) && in_array(parse_url($base, PHP_URL_SCHEME), ['http', 'https'], true), 422, 'Invalid application URL.');
        abort_unless(preg_match('/^[a-f0-9]{64}$/D', $certificate->verification_token), 422);
        $verificationUrl = $base.'/certificates/verify/'.$certificate->verification_token;
        $svg = (string) QrCode::format('svg')->size(400)->margin(4)->errorCorrection('M')->generate($verificationUrl);
        $qrImage = 'data:image/svg+xml;base64,'.base64_encode($svg);

        $values = [
            'attendee_name' => $snapshot['attendee_name'],
            'event_name' => $snapshot['event_name'],
            'event_date' => $snapshot['event_date'],
            'organization_name' => $snapshot['organization_name'],
            'certificate_number' => $certificate->certificate_number,
            'issued_date' => $issuedAt->format('d M Y'),
        ];

        $pdf = Pdf::loadView('exports.certificate-issued', compact('template', 'definition', 'images', 'values', 'qrImage'))
            ->setOptions(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false, 'dpi' => 96])
            ->setPaper([0, 0, $template->width * 0.75, $template->height * 0.75]);
        $bytes = $pdf->output();
        abort_unless(str_starts_with($bytes, '%PDF-') && $pdf->getDomPDF()->getCanvas()->get_page_count() === 1, 422, 'Certificate must render as a single PDF page.');

        return $bytes;
    }
}
