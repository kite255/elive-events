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
}
