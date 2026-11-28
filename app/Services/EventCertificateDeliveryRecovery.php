<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

final class EventCertificateDeliveryRecovery
{
    public function inspect(int $eventId, bool $apply = false): array
    {
        $result = ['stale_queued' => 0, 'stale_processing' => 0, 'marked_uncertain' => 0];
        $items = DB::table('event_certificate_deliveries as delivery')
            ->join('event_certificates as certificate', 'certificate.id', '=', 'delivery.event_certificate_id')
            ->where('certificate.event_id', $eventId)
            ->whereIn('delivery.status', ['queued', 'processing'])
            ->where('delivery.updated_at', '<=', now()->subHours(2))
            ->select('delivery.id', 'delivery.status', 'delivery.updated_at')
            ->orderBy('delivery.id')->limit(500)->get();

        foreach ($items as $item) {
            $result['stale_'.$item->status]++;
            if (! $apply) {
                continue;
            }
            // A stale timestamp cannot establish that a provider did not receive a message.
            // Do not modify an actively processing claim: a worker may still record its result.
            if ($item->status === 'processing') {
                continue;
            }
            $result['marked_uncertain'] += DB::table('event_certificate_deliveries')
                ->where('id', $item->id)->where('status', 'queued')
                ->where('updated_at', $item->updated_at)
                ->update(['status' => 'uncertain', 'failure_code' => 'stale_dispatch_unconfirmed', 'updated_at' => now()]);
        }

        return $result;
    }
}
