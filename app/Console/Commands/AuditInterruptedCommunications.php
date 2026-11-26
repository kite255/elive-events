<?php

namespace App\Console\Commands;

use App\Models\CommunicationLog;
use Illuminate\Console\Command;

class AuditInterruptedCommunications extends Command
{
    protected $signature = 'communications:audit-interrupted
        {--minutes=15 : Minimum age of a sending record in minutes}
        {--event= : Restrict to an event ID}
        {--limit=100 : Maximum detail rows}';

    protected $description = 'Audit stale sending communication logs without modifying records or resending messages';

    public function handle(): int
    {
        $minutes = filter_var($this->option('minutes'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 5, 'max_range' => 525600]]);
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        $event = $this->option('event');
        if ($minutes === false || $limit === false || ($event !== null
            && filter_var($event, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false)) {
            $this->error('Invalid options. Minutes: 5–525600; limit: 1–1000; event: positive ID.');

            return self::INVALID;
        }

        $query = CommunicationLog::query()
            ->where('status', CommunicationLog::STATUS_SENDING)
            ->where('updated_at', '<=', now()->subMinutes($minutes))
            ->when($event !== null, fn ($query) => $query->where('event_id', (int) $event));

        $total = (clone $query)->count();
        $withEvidence = (clone $query)->where(function ($query): void {
            $query->where(function ($query): void {
                $query->whereNotNull('provider_message_id')->where('provider_message_id', '<>', '');
            })->orWhereNotNull('sent_at')->orWhereNotNull('delivered_at');
        })->count();

        $this->table(['Check', 'Count'], [
            ['Stale sending records', $total],
            ['Recorded provider ID or send/delivery timestamp', $withEvidence],
            ['No recorded submission evidence', $total - $withEvidence],
        ]);

        $rows = (clone $query)->orderBy('updated_at')->orderBy('id')->limit($limit)->get([
            'id', 'event_id', 'communication_campaign_id', 'channel', 'updated_at',
            'provider_message_id', 'sent_at', 'delivered_at',
        ])->map(fn (CommunicationLog $log) => [
            $log->id,
            $log->event_id,
            $log->communication_campaign_id ?? '—',
            $log->channel,
            $log->updated_at?->toDateTimeString(),
            filled($log->provider_message_id) || $log->sent_at || $log->delivered_at
                ? 'Reconcile recorded evidence' : 'Unknown outcome — investigate',
        ])->all();

        if ($rows !== []) {
            $this->table(['Log ID', 'Event', 'Campaign', 'Channel', 'Last updated', 'Review'], $rows);
        }
        if ($total > count($rows)) {
            $this->warn('Detail output is limited; totals include all matching records.');
        }

        $this->info('Read-only audit. No records modified, no provider queried, and no messages resent.');

        return self::SUCCESS;
    }
}
