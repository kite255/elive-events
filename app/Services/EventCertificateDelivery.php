<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventCertificate;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class EventCertificateDelivery
{
    /**
     * Reserve one delivery per channel. This does not dispatch or send a message.
     */
    public function reserve(User $user, int $eventId, int $certificateId, string $channel): int
    {
        $access = app(EventCertificateAccess::class);
        $access->authorize($user, $eventId);
        Validator::make(['channel' => $channel], ['channel' => ['required', 'in:email,sms']])->validate();

        return DB::transaction(function () use ($access, $user, $eventId, $certificateId, $channel): int {
            Event::query()->whereKey($eventId)->lockForUpdate()->firstOrFail();
            $event = $access->authorize($user, $eventId);
            $certificate = $event->certificates()->whereKey($certificateId)->lockForUpdate()->firstOrFail();
            abort_unless($certificate->status === EventCertificate::STATUS_ISSUED && $certificate->issued_at && $certificate->pdf_path, 422, 'Only issued certificates can be delivered.');
            $attendee = $event->attendees()->whereKey($certificate->attendee_id)->lockForUpdate()->firstOrFail();
            abort_unless($attendee->full_name === ($certificate->issuance_snapshot['attendee_name'] ?? null), 422, 'Attendee identity changed after issuance.');
            $recipient = trim((string) ($channel === 'email' ? $attendee->email : $attendee->phone));
            if ($channel === 'email') {
                Validator::make(['recipient' => $recipient], ['recipient' => ['required', 'email:rfc', 'max:254']])->validate();
            } else {
                // Conservative initial scope: Tanzanian mobile numbers only.
                $recipient = ltrim($recipient, '+');
                abort_unless(preg_match('/^255[67][0-9]{8}$/D', $recipient), 422, 'A valid Tanzanian mobile number is required.');
            }
            if (DB::table('event_certificate_deliveries')->where('event_certificate_id', $certificateId)->where('channel', $channel)->exists()) {
                throw ValidationException::withMessages(['channel' => 'A delivery record already exists for this certificate and channel. Review its status before sending again.']);
            }

            return DB::table('event_certificate_deliveries')->insertGetId([
                'event_certificate_id' => $certificateId,
                'actor_id' => $user->id,
                'channel' => $channel,
                'recipient_encrypted' => Crypt::encryptString($recipient),
                'recipient_hash' => hash_hmac('sha256', $recipient, (string) config('app.key')),
                'status' => 'queued',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }
}
