<?php

namespace App\Services;

use App\Jobs\DeliverEventCertificate;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Throwable;

final class EventCertificateDeliveryQueue
{
    public function queue(User $user, int $eventId, int $certificateId, string $channel): int
    {
        abort_unless(in_array(config('queue.connections.'.config('queue.default').'.driver'), ['database', 'redis'], true), 503, 'An asynchronous queue is required.');
        abort_if(DB::transactionLevel() > 0 && ! app()->runningUnitTests(), 422);
        $id = app(EventCertificateDelivery::class)->reserve($user, $eventId, $certificateId, $channel);
        try {
            Bus::dispatch(new DeliverEventCertificate($id));
        } catch (Throwable $exception) {
            DB::table('event_certificate_deliveries')->where('id', $id)->where('status', 'queued')
                ->update(['status' => 'uncertain', 'failure_code' => 'dispatch_unconfirmed', 'updated_at' => now()]);
            throw $exception;
        }

        return $id;
    }
}
