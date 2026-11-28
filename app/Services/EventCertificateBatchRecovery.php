<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

final class EventCertificateBatchRecovery
{
    public function inspect(int $eventId, bool $apply = false): array
    {
        // A stale timestamp is not proof that a queue job has disappeared.
        // Never dispatch jobs from this recovery operation.
        $cutoff = now()->subHours(2);
        $result = ['stale' => 0, 'reconciled' => 0, 'marked_failed' => 0];
        $items = DB::table('event_certificate_batch_items as item')
            ->join('event_certificate_batches as batch', 'batch.id', '=', 'item.event_certificate_batch_id')
            ->where('batch.event_id', $eventId)
            ->whereIn('item.status', ['queued', 'processing'])
            ->where('item.updated_at', '<=', $cutoff)
            ->select('item.id', 'item.status', 'item.updated_at', 'item.attendee_id', 'batch.event_certificate_template_id')
            ->orderBy('item.id')->limit(500)->get();

        foreach ($items as $item) {
            $result['stale']++;
            $certificate = DB::table('event_certificates')->where('event_id', $eventId)
                ->where('attendee_id', $item->attendee_id)->first();
            $status = match (true) {
                $certificate && $certificate->status === 'issued'
                    && (int) $certificate->event_certificate_template_id === (int) $item->event_certificate_template_id => 'issued',
                $certificate && in_array($certificate->status, ['issued', 'revoked'], true) => 'skipped',
                default => 'failed',
            };
            if (! $apply) {
                continue;
            }
            $changed = DB::table('event_certificate_batch_items')->where('id', $item->id)
                ->where('status', $item->status)->where('updated_at', $item->updated_at)
                ->update([
                    'status' => $status,
                    'failure_code' => $status === 'failed' ? 'stale_review_required' : null,
                    'updated_at' => now(),
                ]);
            $result[$status === 'failed' ? 'marked_failed' : 'reconciled'] += $changed;
        }

        return $result;
    }
}
