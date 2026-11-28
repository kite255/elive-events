<?php

namespace App\Http\Controllers;

use App\Models\EventCertificate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PublicCertificateDownloadController extends Controller
{
    public function __invoke(int $certificate): StreamedResponse
    {
        $record = EventCertificate::query()->whereKey($certificate)
            ->where('status', EventCertificate::STATUS_ISSUED)->whereNotNull('issued_at')->firstOrFail();
        $path = $record->pdf_path;
        $prefix = 'certificates/event-'.$record->event_id.'/certificate-'.$record->id.'/';
        $disk = Storage::disk('local');
        abort_unless(is_string($path) && str_starts_with($path, $prefix) && ! str_contains($path, '..') && $disk->exists($path), 404);
        $audit = $record->audits()->where('action', 'issued')->latest('id')->first();
        $hash = $audit?->metadata['pdf_sha256'] ?? '';
        $bytes = $disk->get($path);
        abort_unless(is_string($bytes) && hash_equals($hash, hash('sha256', $bytes)), 409);

        return response()->streamDownload(static function () use ($bytes): void {
            echo $bytes;
        }, $record->certificate_number.'.pdf', [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }
}
