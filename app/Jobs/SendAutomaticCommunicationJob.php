<?php

namespace App\Jobs;

use App\Models\CommunicationLog;
use App\Services\SmsService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class SendAutomaticCommunicationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public int $communicationLogId
    ) {
        $this->onQueue('communications');
    }

    public function handle(): void
    {
        $communicationLog = CommunicationLog::query()
            ->with([
                'event',
                'attendee',
            ])
            ->find($this->communicationLogId);

        if (! $communicationLog) {
            return;
        }

        if (
            in_array(
                $communicationLog->status,
                [
                    CommunicationLog::STATUS_SENT,
                    CommunicationLog::STATUS_DELIVERED,
                ],
                true
            )
        ) {
            return;
        }

        try {
            $communicationLog->update([
                'status' => CommunicationLog::STATUS_SENDING,
                'error' => null,
            ]);

            $providerMessageId = match (
                $communicationLog->channel
            ) {
                CommunicationLog::CHANNEL_SMS =>
                    $this->sendSms(
                        $communicationLog
                    ),

                CommunicationLog::CHANNEL_EMAIL =>
                    $this->sendEmail(
                        $communicationLog
                    ),

                CommunicationLog::CHANNEL_WHATSAPP =>
                    $this->sendWhatsApp(
                        $communicationLog
                    ),

                default =>
                    throw new RuntimeException(
                        'Unsupported communication channel: '
                        . $communicationLog->channel
                    ),
            };

            $communicationLog->markSent(
                $providerMessageId
            );

            Log::info(
                'Automatic communication sent successfully.',
                [
                    'communication_log_id' =>
                        $communicationLog->id,

                    'channel' =>
                        $communicationLog->channel,

                    'provider_message_id' =>
                        $providerMessageId,
                ]
            );
        } catch (Throwable $exception) {
            report($exception);

            $communicationLog->markFailed(
                $exception->getMessage()
            );

            Log::error(
                'Automatic communication failed.',
                [
                    'communication_log_id' =>
                        $communicationLog->id,

                    'channel' =>
                        $communicationLog->channel,

                    'recipient' =>
                        $communicationLog->recipient,

                    'error' =>
                        $exception->getMessage(),
                ]
            );

            throw $exception;
        }
    }

    protected function sendSms(
        CommunicationLog $communicationLog
    ): ?string {
        if (blank($communicationLog->recipient)) {
            throw new RuntimeException(
                'SMS recipient is missing.'
            );
        }

        if (blank($communicationLog->message)) {
            throw new RuntimeException(
                'SMS message is missing.'
            );
        }

        Log::info(
            'Sending automatic SMS communication.',
            [
                'communication_log_id' =>
                    $communicationLog->id,

                'event_id' =>
                    $communicationLog->event_id,

                'attendee_id' =>
                    $communicationLog->attendee_id,

                'recipient' =>
                    $communicationLog->recipient,
            ]
        );

        $result = app(
            SmsService::class
        )->send(
            $communicationLog->recipient,
            $communicationLog->message
        );

        if (
            ! ($result['success'] ?? false)
        ) {
            throw new RuntimeException(
                'SMS provider did not confirm message submission.'
            );
        }

        $providerMessageId =
            $result['provider_message_id']
            ?? null;

        if (blank($providerMessageId)) {
            Log::warning(
                'SMS was submitted but provider message ID was missing.',
                [
                    'communication_log_id' =>
                        $communicationLog->id,

                    'recipient' =>
                        $communicationLog->recipient,
                ]
            );
        }

        return $providerMessageId;
    }

    protected function sendEmail(
        CommunicationLog $communicationLog
    ): ?string {
        /*
        |--------------------------------------------------------------------------
        | Email provider integration
        |--------------------------------------------------------------------------
        |
        | Email is still running in local/test mode.
        | SMTP / Laravel Mail will be connected next.
        |
        */

        Log::info(
            'Automatic email communication prepared.',
            [
                'communication_log_id' =>
                    $communicationLog->id,

                'event_id' =>
                    $communicationLog->event_id,

                'attendee_id' =>
                    $communicationLog->attendee_id,

                'recipient' =>
                    $communicationLog->recipient,

                'subject' =>
                    $communicationLog->subject,

                'message' =>
                    $communicationLog->message,
            ]
        );

        return 'local-email-'
            . $communicationLog->id;
    }

    protected function sendWhatsApp(
        CommunicationLog $communicationLog
    ): ?string {
        /*
        |--------------------------------------------------------------------------
        | WhatsApp integration
        |--------------------------------------------------------------------------
        |
        | WhatsApp is still running in local/test mode.
        | WhatsApp Cloud API will be connected later.
        |
        */

        Log::info(
            'Automatic WhatsApp communication prepared.',
            [
                'communication_log_id' =>
                    $communicationLog->id,

                'event_id' =>
                    $communicationLog->event_id,

                'attendee_id' =>
                    $communicationLog->attendee_id,

                'recipient' =>
                    $communicationLog->recipient,

                'message' =>
                    $communicationLog->message,
            ]
        );

        return 'local-whatsapp-'
            . $communicationLog->id;
    }

    public function failed(
        ?Throwable $exception
    ): void {
        $communicationLog = CommunicationLog::query()
            ->find($this->communicationLogId);

        if (! $communicationLog) {
            return;
        }

        if (
            $communicationLog->status
            === CommunicationLog::STATUS_FAILED
        ) {
            return;
        }

        $communicationLog->markFailed(
            $exception?->getMessage()
                ?: 'Automatic communication job failed.'
        );
    }
}