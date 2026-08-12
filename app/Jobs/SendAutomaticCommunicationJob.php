<?php

namespace App\Jobs;

use App\Models\CommunicationLog;
use App\Services\SmsService;
use App\Services\WhatsAppService;
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

    public array $backoff = [
        60,
        300,
        900,
    ];

    public function __construct(
        public int $communicationLogId
    ) {
        $this->onQueue(
            'communications'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Handle Job
    |--------------------------------------------------------------------------
    */

    public function handle(): void
    {
        $communicationLog =
            CommunicationLog::query()
                ->with([
                    'event',
                    'attendee.event',
                    'attendee.category',
                    'attendee.badgeType',
                ])
                ->find(
                    $this->communicationLogId
                );

        if (! $communicationLog) {
            return;
        }

        /*
        |--------------------------------------------------------------------------
        | Duplicate Send Protection
        |--------------------------------------------------------------------------
        */

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
            /*
            |--------------------------------------------------------------------------
            | Mark Sending
            |--------------------------------------------------------------------------
            */

            $communicationLog->update([
                'status' =>
                    CommunicationLog::STATUS_SENDING,

                'error' =>
                    null,
            ]);

            /*
            |--------------------------------------------------------------------------
            | Send Based On Channel
            |--------------------------------------------------------------------------
            */

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

            /*
            |--------------------------------------------------------------------------
            | Mark Sent
            |--------------------------------------------------------------------------
            */

            $communicationLog->markSent(
                $providerMessageId
            );

            Log::info(
                'Automatic communication sent successfully.',
                [
                    'communication_log_id' =>
                        $communicationLog->id,

                    'event_id' =>
                        $communicationLog->event_id,

                    'attendee_id' =>
                        $communicationLog->attendee_id,

                    'channel' =>
                        $communicationLog->channel,

                    'recipient' =>
                        $communicationLog->recipient,

                    'provider_message_id' =>
                        $providerMessageId,
                ]
            );
        } catch (Throwable $exception) {
            report(
                $exception
            );

            /*
            |--------------------------------------------------------------------------
            | Mark Failed
            |--------------------------------------------------------------------------
            */

            $communicationLog->markFailed(
                $exception->getMessage()
            );

            Log::error(
                'Automatic communication failed.',
                [
                    'communication_log_id' =>
                        $communicationLog->id,

                    'event_id' =>
                        $communicationLog->event_id,

                    'attendee_id' =>
                        $communicationLog->attendee_id,

                    'channel' =>
                        $communicationLog->channel,

                    'recipient' =>
                        $communicationLog->recipient,

                    'attempt' =>
                        $this->attempts(),

                    'error' =>
                        $exception->getMessage(),
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | Throw Again
            |--------------------------------------------------------------------------
            |
            | Laravel will retry according to:
            |
            | tries   = 3
            | backoff = 60, 300, 900 seconds
            |
            */

            throw $exception;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Send SMS
    |--------------------------------------------------------------------------
    */

    protected function sendSms(
        CommunicationLog $communicationLog
    ): ?string {
        if (
            blank(
                $communicationLog->recipient
            )
        ) {
            throw new RuntimeException(
                'SMS recipient is missing.'
            );
        }

        if (
            blank(
                $communicationLog->message
            )
        ) {
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

        if (
            blank(
                $providerMessageId
            )
        ) {
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

    /*
    |--------------------------------------------------------------------------
    | Send Email
    |--------------------------------------------------------------------------
    |
    | Email is intentionally still in local/test mode.
    |
    | We will connect SMTP / Laravel Mail after WhatsApp registration
    | confirmation is working correctly.
    |
    */

    protected function sendEmail(
        CommunicationLog $communicationLog
    ): ?string {
        if (
            blank(
                $communicationLog->recipient
            )
        ) {
            throw new RuntimeException(
                'Email recipient is missing.'
            );
        }

        if (
            blank(
                $communicationLog->message
            )
        ) {
            throw new RuntimeException(
                'Email message is missing.'
            );
        }

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

    /*
    |--------------------------------------------------------------------------
    | Send WhatsApp
    |--------------------------------------------------------------------------
    |
    | Uses the approved Meta template:
    |
    | event_registration_confirmation
    |
    | Header:
    | Digital attendee badge
    |
    | Body:
    |
    | {{1}} Attendee full name
    | {{2}} Event name
    | {{3}} Attendee category
    | {{4}} Event venue
    |
    |--------------------------------------------------------------------------
    */

    protected function sendWhatsApp(
        CommunicationLog $communicationLog
    ): ?string {
        if (
            blank(
                $communicationLog->recipient
            )
        ) {
            throw new RuntimeException(
                'WhatsApp recipient is missing.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Attendee Required
        |--------------------------------------------------------------------------
        */

        $attendee =
            $communicationLog->attendee;

        if (! $attendee) {
            throw new RuntimeException(
                'WhatsApp communication attendee could not be found.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Event Required
        |--------------------------------------------------------------------------
        */

        if (! $attendee->event) {
            throw new RuntimeException(
                'WhatsApp communication event could not be found.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Badge Required
        |--------------------------------------------------------------------------
        |
        | Registration confirmation uses the digital badge as the Meta
        | template image header.
        |
        */

        if (
            blank(
                $attendee->badge_path
            )
        ) {
            throw new RuntimeException(
                'Attendee badge has not been generated yet.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Verify WhatsApp Configuration
        |--------------------------------------------------------------------------
        */

        $whatsAppService = app(
            WhatsAppService::class
        );

        if (
            ! $whatsAppService->isConfigured()
        ) {
            throw new RuntimeException(
                'WhatsApp Cloud API is not configured.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Send Meta Template
        |--------------------------------------------------------------------------
        */

        Log::info(
            'Sending automatic WhatsApp registration confirmation.',
            [
                'communication_log_id' =>
                    $communicationLog->id,

                'event_id' =>
                    $communicationLog->event_id,

                'attendee_id' =>
                    $communicationLog->attendee_id,

                'recipient' =>
                    $communicationLog->recipient,

                'template' =>
                    config(
                        'services.whatsapp.templates.registration_confirmation'
                    ),

                'badge_path' =>
                    $attendee->badge_path,
            ]
        );

        $result =
            $whatsAppService
                ->sendRegistrationConfirmation(
                    $attendee
                );

        /*
        |--------------------------------------------------------------------------
        | Validate Provider Response
        |--------------------------------------------------------------------------
        */

        if (
            ! ($result['success'] ?? false)
        ) {
            throw new RuntimeException(
                'WhatsApp provider did not confirm message submission.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Provider Message ID
        |--------------------------------------------------------------------------
        |
        | Meta should return a WAMID such as:
        |
        | wamid.HBgM...
        |
        | We save this in CommunicationLog so the webhook can later update:
        |
        | sent
        | delivered
        | read
        | failed
        |
        */

        $providerMessageId =
            $result['provider_message_id']
            ?? null;

        if (
            blank(
                $providerMessageId
            )
        ) {
            Log::warning(
                'WhatsApp message was submitted but Meta returned no message ID.',
                [
                    'communication_log_id' =>
                        $communicationLog->id,

                    'attendee_id' =>
                        $communicationLog->attendee_id,

                    'recipient' =>
                        $communicationLog->recipient,

                    'response' =>
                        $result['response']
                        ?? null,
                ]
            );
        }

        return $providerMessageId;
    }

    /*
    |--------------------------------------------------------------------------
    | Permanently Failed Job
    |--------------------------------------------------------------------------
    |
    | Laravel calls this after all retry attempts have been exhausted.
    |
    */

    public function failed(
        ?Throwable $exception
    ): void {
        $communicationLog =
            CommunicationLog::query()
                ->find(
                    $this->communicationLogId
                );

        if (! $communicationLog) {
            return;
        }

        $communicationLog->markFailed(
            $exception?->getMessage()
                ?: 'Automatic communication job failed.'
        );

        Log::error(
            'Automatic communication job permanently failed.',
            [
                'communication_log_id' =>
                    $communicationLog->id,

                'event_id' =>
                    $communicationLog->event_id,

                'attendee_id' =>
                    $communicationLog->attendee_id,

                'channel' =>
                    $communicationLog->channel,

                'recipient' =>
                    $communicationLog->recipient,

                'error' =>
                    $exception?->getMessage(),
            ]
        );
    }
}