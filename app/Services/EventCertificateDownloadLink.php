<?php

namespace App\Services;

use App\Models\EventCertificate;
use Illuminate\Support\Facades\URL;

final class EventCertificateDownloadLink
{
    public function generate(EventCertificate $certificate): string
    {
        abort_unless($certificate->exists && $certificate->status === EventCertificate::STATUS_ISSUED
            && $certificate->issued_at && $certificate->pdf_path, 422, 'Only issued certificates can be shared.');

        return URL::temporarySignedRoute('public.certificates.download', now()->addDays(30), [
            'certificate' => $certificate->id,
        ]);
    }

    public function short(EventCertificate $certificate): string
    {
        abort_unless($certificate->exists && $certificate->status === EventCertificate::STATUS_ISSUED
            && $certificate->issued_at && $certificate->pdf_path, 422);
        $token = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
        \Illuminate\Support\Facades\DB::table('event_certificate_download_tokens')->insert([
            'event_certificate_id' => $certificate->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(30),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return route('public.certificates.short-download', ['token' => $token]);
    }
}
