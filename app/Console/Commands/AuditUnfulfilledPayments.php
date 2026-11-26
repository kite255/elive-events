<?php

namespace App\Console\Commands;

use App\Models\Payment;
use Illuminate\Console\Command;

class AuditUnfulfilledPayments extends Command
{
    protected $signature = 'payments:audit-unfulfilled
        {--minutes=15 : Minimum age since last payment update}
        {--event= : Restrict to an event ID}
        {--limit=100 : Maximum detail rows}';

    protected $description = 'Read-only audit of completed payments awaiting fulfillment';

    public function handle(): int
    {
        $minutes = filter_var($this->option('minutes'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 525600]]);
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        $event = $this->option('event');

        if ($minutes === false || $limit === false || ($event !== null
            && filter_var($event, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false)) {
            $this->error('Invalid options: minutes, event and limit must be positive integers within bounds.');

            return self::INVALID;
        }

        $query = Payment::query()
            ->where('status', Payment::STATUS_COMPLETED)
            ->whereNull('fulfilled_at')
            ->where('updated_at', '<=', now()->subMinutes($minutes))
            ->when($event !== null, fn ($query) => $query->where('event_id', (int) $event));

        $total = (clone $query)->count();
        $this->info("Completed unfulfilled payments: {$total}");

        $rows = (clone $query)->orderBy('updated_at')->orderBy('id')->limit($limit)->get([
            'id', 'event_id', 'attendee_id', 'ticket_order_id', 'ticket_upgrade_id',
            'donation_id', 'merchandise_order_id', 'amount', 'currency', 'updated_at',
        ])->map(function (Payment $payment): array {
            $target = match (true) {
                $payment->merchandise_order_id !== null => 'merchandise',
                $payment->ticket_upgrade_id !== null => 'ticket upgrade',
                $payment->donation_id !== null => 'donation',
                $payment->ticket_order_id !== null => 'ticket order',
                $payment->attendee_id !== null => 'registration',
                default => 'missing target',
            };

            return [
                $payment->id, $payment->event_id ?? '-', $target,
                $payment->amount, $payment->currency, $payment->updated_at?->toDateTimeString(),
            ];
        })->all();

        if ($rows !== []) {
            $this->table(['Payment ID', 'Event', 'Target', 'Amount', 'Currency', 'Last updated'], $rows);
        }

        if ($total > count($rows)) {
            $this->warn('Detail output limited; total includes all matching records.');
        }

        $this->info('Read-only audit. No payments modified, no gateway queried, no jobs dispatched.');

        return self::SUCCESS;
    }
}
