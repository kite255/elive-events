<?php

namespace App\Services;

use App\Jobs\RetryCommunicationLogJob;
use App\Models\CommunicationLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CommunicationRetryService
{
    public function canRetry(User $actor, CommunicationLog $log): bool
    {
        return ! $actor->is_gate_staff
            && ! $actor->isTicketOrganizer()
            && $log->event !== null
            && $actor->canManageEventCommunication($log->event)
            && $log->isSms()
            && $log->canRetry()
            && blank($log->provider_message_id)
            && $log->sent_at === null
            && $log->delivered_at === null
            && ($log->communication_campaign_id === null
                || ($log->campaign !== null && ! $log->campaign->isCancelled()
                    && (int) $log->campaign->event_id === (int) $log->event_id));
    }

    public function queue(User $actor, int $logId): bool
    {
        return DB::transaction(function () use ($actor, $logId): bool {
            $actor = User::query()->find($actor->getKey());
            $log = CommunicationLog::query()->whereKey($logId)->lockForUpdate()->first();
            if (! $actor || ! $log || ! $this->canRetry($actor, $log)) {
                return false;
            }

            $log->markQueued();
            RetryCommunicationLogJob::dispatch($log->id)->afterCommit();

            return true;
        }, 3);
    }
}
