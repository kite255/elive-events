<?php

namespace App\Jobs;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationCampaignRecipient;
use App\Models\CommunicationLog;
use App\Models\EventCommunication;
use App\Services\SmsService;
use App\Services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
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
        /*
         * Queue selection is handled by the dispatching service.
         *
         * AutomaticCommunicationService routes:
         * email    -> communications-email
         * sms      -> communications-sms
         * whatsapp -> communications-whatsapp
         *
         * Do not force the legacy shared "communications" queue here.
         */
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
                    'campaign',
                    'campaignRecipient',
                ])
                ->find(
                    $this->communicationLogId
                );

        if (! $communicationLog) {
            Log::warning(
                'Automatic communication log could not be found.',
                [
                    'communication_log_id' =>
                        $this->communicationLogId,
                ]
            );

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
            $this->syncCampaignLifecycle(
                $communicationLog
            );

            return;
        }

        $this->incrementRecipientAttempts(
            $communicationLog
        );

        try {
            $communicationLog->markSending();

            $this->markCampaignProcessing(
                $communicationLog
            );

            $this->syncCampaignLifecycle(
                $communicationLog
            );

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

            $this->syncCampaignLifecycle(
                $communicationLog
            );

            Log::info(
                'Automatic communication sent successfully.',
                [
                    'communication_log_id' =>
                        $communicationLog->id,

                    'communication_campaign_id' =>
                        $communicationLog
                            ->communication_campaign_id,

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

                    'provider_message_id' =>
                        $providerMessageId,
                ]
            );
        } catch (Throwable $exception) {
            report(
                $exception
            );

            $isFinalAttempt =
                $this->attempts()
                >= $this->tries;

            if (! $isFinalAttempt) {
                $communicationLog->forceFill([
                    'status' =>
                        CommunicationLog::STATUS_QUEUED,

                    'error' =>
                        $exception->getMessage(),

                    'queued_at' =>
                        now(),

                    'failed_at' =>
                        null,
                ])->save();

                $communicationLog
                    ->syncCampaignRecipient();

                $this->syncCampaignLifecycle(
                    $communicationLog
                );

                Log::warning(
                    'Automatic communication attempt failed and will be retried.',
                    [
                        'communication_log_id' =>
                            $communicationLog->id,

                        'communication_campaign_id' =>
                            $communicationLog
                                ->communication_campaign_id,

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

                        'max_attempts' =>
                            $this->tries,

                        'error' =>
                            $exception->getMessage(),
                    ]
                );

                throw $exception;
            }

            $communicationLog->markFailed(
                $exception->getMessage()
            );

            $this->syncCampaignLifecycle(
                $communicationLog
            );

            Log::error(
                'Automatic communication failed after final attempt.',
                [
                    'communication_log_id' =>
                        $communicationLog->id,

                    'communication_campaign_id' =>
                        $communicationLog
                            ->communication_campaign_id,

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

                    'max_attempts' =>
                        $this->tries,

                    'error' =>
                        $exception->getMessage(),
                ]
            );

            throw $exception;
        }
    }

    protected function incrementRecipientAttempts(
        CommunicationLog $communicationLog
    ): void {
        $recipient =
            $communicationLog
                ->campaignRecipient;

        if (! $recipient) {
            return;
        }

        $recipient->forceFill([
            'attempts' =>
                ((int) $recipient->attempts)
                + 1,
        ])->save();

        $communicationLog
            ->unsetRelation(
                'campaignRecipient'
            );
    }

    protected function markCampaignProcessing(
        CommunicationLog $communicationLog
    ): void {
        $campaign =
            $communicationLog
                ->campaign;

        if (! $campaign) {
            return;
        }

        if (
            $campaign->status
            !== CommunicationCampaign::STATUS_CANCELLED
        ) {
            $campaign->forceFill([
                'status' =>
                    CommunicationCampaign::STATUS_PROCESSING,

                'started_at' =>
                    $campaign->started_at
                    ?? now(),

                'completed_at' =>
                    null,
            ])->save();
        }
    }

    protected function syncCampaignLifecycle(
        CommunicationLog $communicationLog
    ): void {
        $campaignId =
            $communicationLog
                ->communication_campaign_id;

        if (! $campaignId) {
            return;
        }

        $campaign =
            CommunicationCampaign::query()
                ->find(
                    $campaignId
                );

        if (! $campaign) {
            return;
        }

        $counts =
            CommunicationCampaignRecipient::query()
                ->where(
                    'communication_campaign_id',
                    $campaign->id
                )
                ->selectRaw(
                    "
                    COUNT(*) AS total_count,
                    COUNT(*) FILTER (
                        WHERE status = 'pending'
                    ) AS pending_count,
                    COUNT(*) FILTER (
                        WHERE status = 'queued'
                    ) AS queued_count,
                    COUNT(*) FILTER (
                        WHERE status = 'processing'
                    ) AS processing_count,
                    COUNT(*) FILTER (
                        WHERE status = 'sent'
                    ) AS sent_count,
                    COUNT(*) FILTER (
                        WHERE status = 'delivered'
                    ) AS delivered_count,
                    COUNT(*) FILTER (
                        WHERE status = 'failed'
                    ) AS failed_count,
                    COUNT(*) FILTER (
                        WHERE status = 'skipped'
                    ) AS skipped_count
                    "
                )
                ->first();

        if (! $counts) {
            return;
        }

        $totalCount =
            (int) $counts->total_count;

        $pendingCount =
            (int) $counts->pending_count;

        $queuedCount =
            (int) $counts->queued_count;

        $processingCount =
            (int) $counts->processing_count;

        $sentCount =
            (int) $counts->sent_count;

        $deliveredCount =
            (int) $counts->delivered_count;

        $failedCount =
            (int) $counts->failed_count;

        $skippedCount =
            (int) $counts->skipped_count;

        $effectiveFailedCount =
            $failedCount
            + $skippedCount;

        $activeCount =
            $pendingCount
            + $queuedCount
            + $processingCount;

        $successfulCount =
            $sentCount
            + $deliveredCount;

        if (
            $campaign->status
            === CommunicationCampaign::STATUS_CANCELLED
        ) {
            $status =
                CommunicationCampaign::STATUS_CANCELLED;
        } elseif ($activeCount > 0) {
            $status =
                $processingCount > 0
                    ? CommunicationCampaign::STATUS_PROCESSING
                    : CommunicationCampaign::STATUS_QUEUED;
        } elseif (
            $totalCount > 0
            && $successfulCount === 0
            && $effectiveFailedCount > 0
        ) {
            $status =
                CommunicationCampaign::STATUS_FAILED;
        } elseif ($totalCount > 0) {
            $status =
                CommunicationCampaign::STATUS_COMPLETED;
        } else {
            $status =
                CommunicationCampaign::STATUS_FAILED;
        }

        $isFinished =
            in_array(
                $status,
                [
                    CommunicationCampaign::STATUS_COMPLETED,
                    CommunicationCampaign::STATUS_FAILED,
                    CommunicationCampaign::STATUS_CANCELLED,
                ],
                true
            );

        $campaign->forceFill([
            'total_recipients' =>
                $totalCount,

            'queued_count' =>
                $queuedCount,

            'sent_count' =>
                $sentCount,

            'delivered_count' =>
                $deliveredCount,

            'failed_count' =>
                $effectiveFailedCount,

            'status' =>
                $status,

            'sent_at' =>
                $successfulCount > 0
                    ? (
                        $campaign->sent_at
                        ?? now()
                    )
                    : $campaign->sent_at,

            'completed_at' =>
                $isFinished
                    ? (
                        $campaign->completed_at
                        ?? now()
                    )
                    : null,
        ])->save();
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
    | Sends branded HTML email through resources/views/emails/elive.blade.php.
    |
    | Local:
    | MAIL_HOST=mailpit
    | MAIL_PORT=1025
    |
    | Production:
    | Configure the real SMTP credentials through the production environment.
    |
    | If the attendee has a generated digital badge and the file exists on the
    | public disk, the badge is attached automatically.
    |
    */

    protected function sendEmail(
        CommunicationLog $communicationLog
    ): ?string {
        /*
        |--------------------------------------------------------------------------
        | Validate Recipient
        |--------------------------------------------------------------------------
        */

        $recipient =
            strtolower(
                trim(
                    (string) $communicationLog->recipient
                )
            );

        if (
            blank($recipient)
            || ! filter_var(
                $recipient,
                FILTER_VALIDATE_EMAIL
            )
        ) {
            throw new RuntimeException(
                'Email recipient is missing or invalid.'
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

        /*
        |--------------------------------------------------------------------------
        | Resolve Subject
        |--------------------------------------------------------------------------
        */

        $subject =
            trim(
                (string) $communicationLog->subject
            );

        if (blank($subject)) {
            $eventName =
                $communicationLog->event?->name
                ?? $communicationLog->attendee?->event?->name
                ?? null;

            $subject =
                filled($eventName)
                    ? "Registration Confirmed – {$eventName}"
                    : 'eLive Events Notification';
        }

        $attendee =
            $communicationLog->attendee;

        $event =
            $communicationLog->event
            ?? $attendee?->event;

        /*
        |--------------------------------------------------------------------------
        | Resolve Event Communication
        |--------------------------------------------------------------------------
        |
        | Event Communication campaigns include their public URL in the rendered
        | message. Resolve the communication from that URL first because it is
        | stable and avoids guessing from campaign names.
        |
        | Fallback:
        | event_id + subject/title match.
        |
        */

        $eventCommunication =
            $this->resolveEventCommunication(
                $communicationLog
            );

        /*
        |--------------------------------------------------------------------------
        | Registration / Generic Badge Attachment
        |--------------------------------------------------------------------------
        |
        | Event Communication newsletters should reproduce the newsletter
        | itself. They do not automatically attach the attendee badge.
        |
        | Registration and other normal automatic emails keep the existing
        | badge attachment behaviour.
        |
        */

        $badgeAbsolutePath = null;
        $badgeAttachmentName = null;

        if (
            ! $eventCommunication
            && $attendee
            && filled(
                $attendee->badge_path
            )
        ) {
            $badgePath =
                trim(
                    (string) $attendee->badge_path
                );

            $normalizedBadgePath =
                ltrim(
                    $badgePath,
                    '/'
                );

            if (
                str_starts_with(
                    $normalizedBadgePath,
                    'storage/'
                )
            ) {
                $normalizedBadgePath =
                    substr(
                        $normalizedBadgePath,
                        strlen('storage/')
                    );
            }

            try {
                if (
                    Storage::disk(
                        'public'
                    )->exists(
                        $normalizedBadgePath
                    )
                ) {
                    $badgeAbsolutePath =
                        Storage::disk(
                            'public'
                        )->path(
                            $normalizedBadgePath
                        );

                    $extension =
                        pathinfo(
                            $normalizedBadgePath,
                            PATHINFO_EXTENSION
                        );

                    $safeExtension =
                        filled($extension)
                            ? strtolower($extension)
                            : 'png';

                    $badgeNumber =
                        filled(
                            $attendee->badge_number
                        )
                            ? preg_replace(
                                '/[^A-Za-z0-9_-]+/',
                                '-',
                                (string) $attendee->badge_number
                            )
                            : (string) $attendee->id;

                    $badgeAttachmentName =
                        'eLive-Event-Badge-'
                        . $badgeNumber
                        . '.'
                        . $safeExtension;
                } else {
                    Log::warning(
                        'Email badge attachment was not found on the public disk.',
                        [
                            'communication_log_id' =>
                                $communicationLog->id,

                            'attendee_id' =>
                                $attendee->id,

                            'badge_path' =>
                                $attendee->badge_path,

                            'normalized_badge_path' =>
                                $normalizedBadgePath,
                        ]
                    );
                }
            } catch (Throwable $exception) {
                Log::warning(
                    'Email badge attachment could not be resolved.',
                    [
                        'communication_log_id' =>
                            $communicationLog->id,

                        'attendee_id' =>
                            $attendee->id,

                        'badge_path' =>
                            $attendee->badge_path,

                        'error' =>
                            $exception->getMessage(),
                    ]
                );
            }
        }

        Log::info(
            'Sending automatic email communication.',
            [
                'communication_log_id' =>
                    $communicationLog->id,

                'communication_campaign_id' =>
                    $communicationLog
                        ->communication_campaign_id,

                'event_communication_id' =>
                    $eventCommunication?->id,

                'event_id' =>
                    $communicationLog->event_id,

                'attendee_id' =>
                    $communicationLog->attendee_id,

                'recipient' =>
                    $recipient,

                'subject' =>
                    $subject,

                'email_view' =>
                    $eventCommunication
                        ? 'emails.event-communication'
                        : 'emails.elive',

                'has_badge_attachment' =>
                    filled(
                        $badgeAbsolutePath
                    ),
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Event Communication Newsletter
        |--------------------------------------------------------------------------
        |
        | When the log belongs to a published Event Communication campaign,
        | send the full visual newsletter using:
        |
        | resources/views/emails/event-communication.blade.php
        |
        */

        if ($eventCommunication) {
            $eventCommunication->loadMissing([
                'event.organization',
                'sections',
                'links',
                'images',
                'attachments',
            ]);

            Mail::send(
                'emails.event-communication',
                [
                    'subject' =>
                        $subject,

                    'communication' =>
                        $eventCommunication,

                    'event' =>
                        $eventCommunication->event
                        ?? $event,
                ],
                function ($mail) use (
                    $recipient,
                    $subject
                ): void {
                    $mail
                        ->to(
                            $recipient
                        )
                        ->subject(
                            $subject
                        );
                }
            );

            return 'smtp-email-'
                . $communicationLog->id;
        }

        /*
        |--------------------------------------------------------------------------
        | Standard eLive Email
        |--------------------------------------------------------------------------
        */

        $emailLabel =
            str_contains(
                strtolower($subject),
                'registration'
            )
                ? 'Registration Confirmed'
                : 'Event Communication';

        Mail::send(
            'emails.elive',
            [
                'subject' =>
                    $subject,

                'messageBody' =>
                    $communicationLog->message,

                'attendee' =>
                    $attendee,

                'event' =>
                    $event,

                'emailLabel' =>
                    $emailLabel,

                'alertTitle' =>
                    null,

                'alertMessage' =>
                    null,

                'actionUrl' =>
                    null,

                'actionLabel' =>
                    null,

                'actionIntro' =>
                    null,

                'actionNote' =>
                    null,
            ],
            function ($mail) use (
                $recipient,
                $subject,
                $badgeAbsolutePath,
                $badgeAttachmentName
            ): void {
                $mail
                    ->to(
                        $recipient
                    )
                    ->subject(
                        $subject
                    );

                if (
                    filled(
                        $badgeAbsolutePath
                    )
                    && is_file(
                        $badgeAbsolutePath
                    )
                ) {
                    $mail->attach(
                        $badgeAbsolutePath,
                        [
                            'as' =>
                                $badgeAttachmentName
                                ?? basename(
                                    $badgeAbsolutePath
                                ),
                        ]
                    );
                }
            }
        );

        return 'smtp-email-'
            . $communicationLog->id;
    }

    /*
    |--------------------------------------------------------------------------
    | Resolve Event Communication
    |--------------------------------------------------------------------------
    */

    protected function resolveEventCommunication(
        CommunicationLog $communicationLog
    ): ?EventCommunication {
        if (
            ! $communicationLog
                ->communication_campaign_id
        ) {
            return null;
        }

        $message =
            (string) $communicationLog->message;

        /*
         * Prefer the communication slug embedded in the public URL:
         *
         * /events/{event}/communications/{communication}
         */
        if (
            preg_match(
                '#/communications/([^?\s/]+)#u',
                $message,
                $matches
            )
        ) {
            $slug =
                urldecode(
                    trim(
                        (string) (
                            $matches[1]
                            ?? ''
                        )
                    )
                );

            if ($slug !== '') {
                $communication =
                    EventCommunication::query()
                        ->where(
                            'event_id',
                            $communicationLog
                                ->event_id
                        )
                        ->where(
                            'slug',
                            $slug
                        )
                        ->first();

                if ($communication) {
                    return $communication;
                }
            }
        }

        /*
         * Fallback for older campaign records where the public URL was not
         * included in the message.
         */
        $subject =
            trim(
                (string) $communicationLog
                    ->subject
            );

        if ($subject === '') {
            return null;
        }

        return EventCommunication::query()
            ->where(
                'event_id',
                $communicationLog->event_id
            )
            ->where(
                'title',
                $subject
            )
            ->latest('id')
            ->first();
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
                ->with([
                    'campaign',
                    'campaignRecipient',
                ])
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

        $this->syncCampaignLifecycle(
            $communicationLog
        );

        Log::error(
            'Automatic communication job permanently failed.',
            [
                'communication_log_id' =>
                    $communicationLog->id,

                'communication_campaign_id' =>
                    $communicationLog
                        ->communication_campaign_id,

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
