<?php

namespace App\Http\Controllers;

use App\Models\EventCertificate;
use Illuminate\Http\Response;

final class PublicCertificateVerificationController extends Controller
{
    public function __invoke(string $token): Response
    {
        $certificate = EventCertificate::query()
            ->where('verification_token', $token)
            ->whereIn('status', [EventCertificate::STATUS_ISSUED, EventCertificate::STATUS_REVOKED])
            ->whereNotNull('issued_at')
            ->firstOrFail();

        $snapshot = $certificate->issuance_snapshot;
        return response()->view('public.certificates.verify', [
            'valid' => $certificate->status === EventCertificate::STATUS_ISSUED,
            'number' => $certificate->certificate_number,
            'attendeeName' => $snapshot['attendee_name'] ?? '',
            'eventName' => $snapshot['event_name'] ?? '',
            'organizationName' => $snapshot['organization_name'] ?? '',
            'issuedDate' => $certificate->issued_at->format('d M Y'),
        ])->header('Cache-Control', 'private, no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
