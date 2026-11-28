<?php

namespace App\Services;

use App\Jobs\DeliverEventCertificate;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Throwable;

final class EventCertificateDeliveryRetry
{
    public function retry(User $user, int $eventId, int $certificateId, string $channel): void
    {
        $event = app(EventCertificateAccess::class)->authorize($user, $eventId);
        abort_unless(in_array($channel, ['email', 'sms'], true), 422);
        abort_unless(in_array(config('queue.connections.'.config('queue.default').'.driver'), ['database', 'redis'], true), 503);
        abort_if(DB::transactionLevel() > 0 && ! app()->runningUnitTests(), 422);

        $certificate = $event->certificates()->whereKey($certificateId)->where('status', 'issued')->firstOrFail();
        $delivery = DB::table('event_certificate_deliveries')->where('event_certificate_id', $certificate->id)
            ->where('channel', $channel)->where('actor_id', $user->id)->firstOrFail();

        $claimed = DB::table('event_certificate_deliveries')->where('id', $delivery->id)
            ->where('status', 'failed')->where('failure_code', 'preflight_failed')
            ->whereNull('submitted_at')->whereNull('provider_reference')
            ->update(['status' => 'queued', 'failure_code' => null, 'updated_at' => now()]);
        abort_unless($claimed, 422, 'Only confirmed pre-submission failures can be retried.');

        try {
            Bus::dispatch(new DeliverEventCertificate((int) $delivery->id));
        } catch (Throwable $exception) {
            DB::table('event_certificate_deliveries')->where('id', $delivery->id)->where('status', 'queued')
                ->update(['status' => 'uncertain', 'failure_code' => 'dispatch_unconfirmed', 'updated_at' => now()]);
            throw $exception;
        }
    }
}
