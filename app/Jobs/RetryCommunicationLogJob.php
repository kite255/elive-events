<?php

namespace App\Jobs;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationLog;
use App\Services\SmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class RetryCommunicationLogJob implements ShouldQueue
{
    use Queueable;

    /*
    |--------------------------------------------------------------------------
    | Queue configuration
    |--------------------------------------------------------------------------
    */

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public int $communicationLogId
    ) {
        $this->onQueue(
            'communications'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Handle
    |--------------------------------------------------------------------------
    */

    public function handle(
        SmsService $smsService
    ): void {
        $log = CommunicationLog::query()
            ->with([
                'campaignRecipient',
                'campaign',
            ])
            ->find(
                $this->communicationLogId
            );

        /*
         * The log may have been deleted after
         * the job was queued.
         */
        if (! $log) {
            return;
        }

        /*
         * This retry job currently handles SMS only.
         */
        if (! $log->isSms()) {
            return;
        }

        /*
         * A successfully sent/delivered message
         * must never be sent again accidentally.
         */
        if ($log->isSent()) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Validate recipient + message
        |--------------------------------------------------------------------------
        */

        if (
            blank($log->recipient)
            || blank($log->message)
        ) {
            $this->failLog(
                $log,
                'SMS recipient or message is missing.',
                finalFailure: true
            );

            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Start attempt
        |--------------------------------------------------------------------------
        */

        try {
            /*
             * CommunicationLog::markSending()
             * automatically synchronizes the
             * CommunicationCampaignRecipient.
             */
            $log->markSending();

            /*
             * Attempt count belongs to the campaign
             * recipient record.
             */
            if ($log->campaignRecipient) {
                $log->campaignRecipient
                    ->incrementAttempts();
            }

            /*
            |--------------------------------------------------------------------------
            | Send SMS
            |--------------------------------------------------------------------------
            */

            $result = $smsService->send(
                $log->recipient,
                $log->message
            );

            $providerMessageId =
                $result['provider_message_id']
                ?? null;

            /*
             * markSent() automatically synchronizes
             * the related campaign recipient.
             */
            $log->markSent(
                $providerMessageId
            );

            /*
            |--------------------------------------------------------------------------
            | Refresh campaign
            |--------------------------------------------------------------------------
            */

            $this->refreshCampaign(
                $log->campaign
            );
        } catch (Throwable $exception) {
            report(
                $exception
            );

            /*
             * Determine whether Laravel will still
             * make another queue attempt.
             */
            $isFinalAttempt =
                $this->attempts()
                >= $this->tries;

            $this->failLog(
                $log,
                $exception->getMessage(),
                finalFailure: $isFinalAttempt
            );

            /*
             * Re-throw so Laravel's normal queue
             * retry mechanism can retry the job
             * until $tries is reached.
             */
            throw $exception;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Failure handling
    |--------------------------------------------------------------------------
    */

    private function failLog(
        CommunicationLog $log,
        string $error,
        bool $finalFailure = false
    ): void {
        /*
         * markFailed() automatically synchronizes
         * CommunicationCampaignRecipient.
         */
        $log->markFailed(
            $error
        );

        $campaign =
            $log->campaign;

        if (! $campaign) {
            return;
        }

        /*
         * Recalculate counts immediately so the
         * admin interface shows the current state.
         */
        $campaign->refreshCounters();

        $campaign->refresh();

        /*
         * Laravel still has another job attempt.
         *
         * Keep the campaign processing rather than
         * marking the entire campaign failed.
         */
        if (! $finalFailure) {
            $campaign->forceFill([
                'status' =>
                    CommunicationCampaign::STATUS_PROCESSING,

                'completed_at' =>
                    null,
            ])->save();

            return;
        }

        /*
         * Final attempt failed.
         */
        $this->refreshCampaign(
            $campaign
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Campaign synchronization
    |--------------------------------------------------------------------------
    */

    private function refreshCampaign(
        ?CommunicationCampaign $campaign
    ): void {
        if (! $campaign) {
            return;
        }

        $campaign->refreshCounters();

        $campaign->refresh();

        /*
         * Still processing messages.
         */
        if (
            $campaign->remainingCount() > 0
        ) {
            if (
                ! $campaign->isCancelled()
            ) {
                $campaign->forceFill([
                    'status' =>
                        CommunicationCampaign::STATUS_PROCESSING,

                    'completed_at' =>
                        null,
                ])->save();
            }

            return;
        }

        /*
         * At least one message succeeded.
         *
         * A campaign can still be considered completed
         * even when some individual recipients failed.
         * Failed recipients remain retryable.
         */
        if (
            (int) $campaign->sent_count > 0
            || (int) $campaign->delivered_count > 0
        ) {
            $campaign->markAsCompleted();

            return;
        }

        /*
         * Every processed recipient failed.
         */
        if (
            (int) $campaign->failed_count > 0
        ) {
            $campaign->markAsFailed();

            return;
        }

        /*
         * Nothing definitive yet.
         */
        if (! $campaign->isCancelled()) {
            $campaign->forceFill([
                'status' =>
                    CommunicationCampaign::STATUS_PROCESSING,

                'completed_at' =>
                    null,
            ])->save();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Permanent queue failure
    |--------------------------------------------------------------------------
    */

    public function failed(
        ?Throwable $exception
    ): void {
        $log = CommunicationLog::query()
            ->with([
                'campaignRecipient',
                'campaign',
            ])
            ->find(
                $this->communicationLogId
            );

        if (! $log) {
            return;
        }

        /*
         * Do not overwrite a successful message if,
         * for any unusual reason, the failed callback
         * executes after provider acceptance.
         */
        if ($log->isSent()) {
            return;
        }

        $error =
            $exception?->getMessage()
            ?: 'SMS retry failed after all queue attempts.';

        $log->markFailed(
            $error
        );

        $this->refreshCampaign(
            $log->campaign
        );
    }
}