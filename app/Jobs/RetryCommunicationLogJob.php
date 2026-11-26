<?php

namespace App\Jobs;

use App\Models\CommunicationLog;
use App\Services\SmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RetryCommunicationLogJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public array $backoff = [60, 300, 900];

    public function __construct(public int $communicationLogId)
    {
        $this->onQueue('communications');
    }

    public function handle(SmsService $smsService): void
    {
        $log = CommunicationLog::query()->find($this->communicationLogId);

        // Retain the legacy SMS-only retry contract. Use the normal delivery
        // path so retries cannot bypass recipient validation or atomic claims.
        if (! $log || ! $log->isSms() || ! $log->isQueued()) {
            return;
        }

        $delivery = new SendAutomaticCommunicationJob($log->id);
        if ($this->job !== null) {
            $delivery->setJob($this->job);
        }
        $delivery->handle();
    }
}
