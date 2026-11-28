<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventCertificate;
use App\Models\EventCertificateAudit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

final class EventCertificateRevocation
{
    public function revoke(User $user, int $eventId, int $certificateId, string $reason): EventCertificate
    {
        $access = app(EventCertificateAccess::class);
        $access->authorize($user, $eventId);
        $reason = trim($reason);
        Validator::make(['reason' => $reason], ['reason' => ['required', 'string', 'min:10', 'max:1000']])->validate();

        return DB::transaction(function () use ($access, $user, $eventId, $certificateId, $reason): EventCertificate {
            Event::query()->whereKey($eventId)->lockForUpdate()->firstOrFail();
            $event = $access->authorize($user, $eventId);
            $certificate = $event->certificates()->whereKey($certificateId)->lockForUpdate()->firstOrFail();
            abort_unless($certificate->status === EventCertificate::STATUS_ISSUED && $certificate->issued_at && ! $certificate->revoked_at, 422, 'Only issued certificates can be revoked.');

            $certificate->forceFill([
                'status' => EventCertificate::STATUS_REVOKED,
                'revoked_at' => now(),
                'revocation_reason' => $reason,
            ])->save();

            $audit = new EventCertificateAudit;
            $audit->forceFill([
                'event_certificate_id' => $certificate->id,
                'actor_id' => $user->id,
                'action' => 'revoked',
                'metadata' => ['reason' => $reason, 'previous_status' => EventCertificate::STATUS_ISSUED],
            ])->save();

            return $certificate;
        });
    }
}
