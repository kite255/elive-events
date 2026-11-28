<?php

namespace App\Jobs;

use App\Models\EventCertificate;
use App\Models\User;
use App\Services\EventCertificateAccess;
use App\Services\EventCertificateDownloadLink;
use App\Services\SmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class DeliverEventCertificate implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;
    public int $timeout = 60;

    public function __construct(public int $deliveryId) {}

    public function handle(): void
    {
        $table = DB::table('event_certificate_deliveries');
        $claimed = (clone $table)->where('id', $this->deliveryId)->where('status', 'queued')
            ->update(['status' => 'processing', 'updated_at' => now()]);
        if (! $claimed) {
            return;
        }

        $attempted = false;
        try {
            $delivery = (clone $table)->where('id', $this->deliveryId)->first();
            $certificate = EventCertificate::query()->findOrFail($delivery->event_certificate_id);
            $actor = User::query()->findOrFail($delivery->actor_id);
            $event = app(EventCertificateAccess::class)->authorize($actor, $certificate->event_id);
            abort_unless($certificate->status === EventCertificate::STATUS_ISSUED && ! $certificate->revoked_at, 422);
            $attendee = $event->attendees()->whereKey($certificate->attendee_id)->firstOrFail();
            abort_unless($attendee->full_name === ($certificate->issuance_snapshot['attendee_name'] ?? null), 422);
            $recipient = Crypt::decryptString($delivery->recipient_encrypted);
            $current = trim((string) ($delivery->channel === 'email' ? $attendee->email : $attendee->phone));
            if ($delivery->channel === 'sms') {
                $current = ltrim($current, '+');
            }
            abort_unless(hash_equals($recipient, $current) && hash_equals($delivery->recipient_hash, hash_hmac('sha256', $recipient, (string) config('app.key'))), 422);
            $path = $certificate->pdf_path;
            $prefix = 'certificates/event-'.$event->id.'/certificate-'.$certificate->id.'/';
            $disk = Storage::disk('local');
            abort_unless(is_string($path) && str_starts_with($path, $prefix) && ! str_contains($path, '..') && $disk->exists($path), 422);
            $audit = $certificate->audits()->where('action', 'issued')->latest('id')->first();
            $bytes = $disk->get($path);
            abort_unless(is_string($bytes) && hash_equals($audit?->metadata['pdf_sha256'] ?? '', hash('sha256', $bytes)), 422);
            $downloadUrl = app(EventCertificateDownloadLink::class)->generate($certificate);
            $snapshot = $certificate->issuance_snapshot;
            $reference = null;
            if ($delivery->channel === 'email') {
                // Avoid log/array/failover transports falsely reporting a live submission.
                abort_unless(config('mail.mailers.'.config('mail.default').'.transport') === 'smtp', 422);
                $data = ['attendeeName' => $snapshot['attendee_name'], 'eventName' => $snapshot['event_name'],
                    'organizationName' => $snapshot['organization_name'], 'downloadUrl' => $downloadUrl];
                $attempted = true;
                $sent = Mail::send('emails.certificate-ready', $data, fn ($message) =>
                    $message->to($recipient)->subject('Your certificate is ready | '.preg_replace('/[\r\n]+/', ' ', $snapshot['event_name'])));
                $reference = $sent?->getMessageId();
                abort_unless($sent !== null, 503);
            } elseif ($delivery->channel === 'sms') {
                $attempted = true;
                $response = app(SmsService::class)->send($recipient, 'Your event certificate is ready. Download and save: '.$downloadUrl.' (valid for 30 days).', false);
                $reference = $response['provider_message_id'] ?? null;
                abort_unless(($response['success'] ?? false) === true && filled($reference), 503);
            } else {
                abort(422);
            }

            (clone $table)->where('id', $this->deliveryId)->where('status', 'processing')->update([
                'status' => 'submitted', 'provider_reference' => $reference ? mb_substr((string) $reference, 0, 191) : null,
                'submitted_at' => now(), 'failure_code' => null, 'updated_at' => now(),
            ]);
        } catch (Throwable $exception) {
            // Do not report recipient credentials or signed URLs in exception messages.
            (clone $table)->where('id', $this->deliveryId)->where('status', 'processing')->update([
                'status' => $attempted ? 'uncertain' : 'failed',
                'failure_code' => $attempted ? 'submission_unconfirmed' : 'preflight_failed', 'updated_at' => now(),
            ]);
        }
    }

    public function failed(?Throwable $exception): void
    {
        DB::table('event_certificate_deliveries')->where('id', $this->deliveryId)->where('status', 'processing')
            ->update(['status' => 'uncertain', 'failure_code' => 'worker_interrupted', 'updated_at' => now()]);
    }
}
