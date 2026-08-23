<?php

namespace App\Services;

use App\Jobs\SendAutomaticCommunicationJob;
use App\Models\Attendee;
use App\Models\CommunicationCampaign;
use App\Models\CommunicationCampaignRecipient;
use App\Models\CommunicationLog;
use App\Models\CommunicationTemplate;
use App\Models\Event;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CommunicationCampaignService
{
    /*
    |--------------------------------------------------------------------------
    | Audience Preview
    |--------------------------------------------------------------------------
    |
    | Preview is channel-aware:
    |
    | SMS      -> valid mobile number
    | Email    -> valid email address
    | WhatsApp -> valid mobile number
    |
    |--------------------------------------------------------------------------
    */

    public function preview(
        Event $event,
        string $audience = 'all',
        ?int $categoryId = null,
        string $channel = CommunicationTemplate::CHANNEL_SMS
    ): array {
        $query =
            $this->audienceQuery(
                $event,
                $audience,
                $categoryId
            );

        $total =
            (clone $query)->count();

        $valid = 0;
        $invalid = 0;

        (clone $query)
            ->select([
                'id',
                'phone',
                'email',
            ])
            ->orderBy(
                'id'
            )
            ->chunkById(
                500,
                function (
                    Collection $attendees
                ) use (
                    &$valid,
                    &$invalid,
                    $channel
                ): void {
                    foreach ($attendees as $attendee) {
                        if (
                            $this->hasValidRecipientForChannel(
                                $attendee,
                                $channel
                            )
                        ) {
                            $valid++;
                        } else {
                            $invalid++;
                        }
                    }
                }
            );

        return [
            'total' =>
                $total,

            'valid' =>
                $valid,

            'invalid' =>
                $invalid,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Queue SMS Campaign
    |--------------------------------------------------------------------------
    */

    public function queueSmsCampaign(
        Event $event,
        string $name,
        string $message,
        string $audience = 'all',
        ?int $categoryId = null,
        ?CommunicationTemplate $template = null,
        ?int $createdBy = null
    ): CommunicationCampaign {
        return $this->queueCampaign(
            event: $event,
            name: $name,
            channel: CommunicationTemplate::CHANNEL_SMS,
            subject: null,
            message: $message,
            audience: $audience,
            categoryId: $categoryId,
            template: $template,
            createdBy: $createdBy
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Queue Email Campaign
    |--------------------------------------------------------------------------
    */

    public function queueEmailCampaign(
        Event $event,
        string $name,
        string $subject,
        string $message,
        string $audience = 'all',
        ?int $categoryId = null,
        ?CommunicationTemplate $template = null,
        ?int $createdBy = null
    ): CommunicationCampaign {
        $subject =
            trim(
                $subject
            );

        if ($subject === '') {
            throw new RuntimeException(
                'Email campaign subject cannot be empty.'
            );
        }

        return $this->queueCampaign(
            event: $event,
            name: $name,
            channel: CommunicationTemplate::CHANNEL_EMAIL,
            subject: $subject,
            message: $message,
            audience: $audience,
            categoryId: $categoryId,
            template: $template,
            createdBy: $createdBy
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Generic Campaign Queue
    |--------------------------------------------------------------------------
    */

    protected function queueCampaign(
        Event $event,
        string $name,
        string $channel,
        ?string $subject,
        string $message,
        string $audience = 'all',
        ?int $categoryId = null,
        ?CommunicationTemplate $template = null,
        ?int $createdBy = null
    ): CommunicationCampaign {
        $name =
            trim(
                $name
            );

        $message =
            trim(
                $message
            );

        if ($name === '') {
            throw new RuntimeException(
                'Campaign name cannot be empty.'
            );
        }

        if ($message === '') {
            throw new RuntimeException(
                'Campaign message cannot be empty.'
            );
        }

        if (
            ! in_array(
                $channel,
                [
                    CommunicationTemplate::CHANNEL_SMS,
                    CommunicationTemplate::CHANNEL_EMAIL,
                    CommunicationTemplate::CHANNEL_WHATSAPP,
                ],
                true
            )
        ) {
            throw new RuntimeException(
                'Unsupported communication campaign channel.'
            );
        }

        if ($template) {
            if (! $template->isUsable()) {
                throw new RuntimeException(
                    'The selected communication template is not usable.'
                );
            }

            if (
                $template->channel
                !== $channel
            ) {
                throw new RuntimeException(
                    'The selected communication template does not match the campaign channel.'
                );
            }

            if (
                (int) $template->organization_id
                !== (int) $event->organization_id
            ) {
                throw new RuntimeException(
                    'The selected communication template does not belong to this event organization.'
                );
            }
        }

        if (
            $channel
            === CommunicationTemplate::CHANNEL_EMAIL
            && blank($subject)
        ) {
            throw new RuntimeException(
                'Email campaign subject cannot be empty.'
            );
        }

        return DB::transaction(
            function () use (
                $event,
                $name,
                $channel,
                $subject,
                $message,
                $audience,
                $categoryId,
                $template,
                $createdBy
            ): CommunicationCampaign {
                /*
                |--------------------------------------------------------------------------
                | Create Campaign
                |--------------------------------------------------------------------------
                */

                $campaign =
                    CommunicationCampaign::create([
                        'event_id' =>
                            $event->id,

                        'communication_template_id' =>
                            $template?->id,

                        'created_by' =>
                            $createdBy,

                        'name' =>
                            $name,

                        'channel' =>
                            $channel,

                        'type' =>
                            'manual_campaign',

                        'subject' =>
                            $channel
                            === CommunicationTemplate::CHANNEL_EMAIL
                                ? $subject
                                : null,

                        'message' =>
                            $message,

                        'status' =>
                            CommunicationCampaign::STATUS_QUEUED,

                        'recipient_filter' => [
                            'audience' =>
                                $audience,

                            'category_id' =>
                                $categoryId,
                        ],

                        'total_recipients' =>
                            0,

                        'queued_count' =>
                            0,

                        'sent_count' =>
                            0,

                        'delivered_count' =>
                            0,

                        'failed_count' =>
                            0,

                        'started_at' =>
                            now(),
                    ]);

                $totalRecipients = 0;
                $queuedCount = 0;
                $failedCount = 0;

                /*
                |--------------------------------------------------------------------------
                | Resolve Audience In Batches
                |--------------------------------------------------------------------------
                */

                $this->audienceQuery(
                    $event,
                    $audience,
                    $categoryId
                )
                    ->with([
                        'event.organization',
                        'category:id,name',
                        'badgeType:id,name',
                    ])
                    ->orderBy(
                        'id'
                    )
                    ->chunkById(
                        250,
                        function (
                            Collection $attendees
                        ) use (
                            $campaign,
                            $channel,
                            $subject,
                            $message,
                            &$totalRecipients,
                            &$queuedCount,
                            &$failedCount
                        ): void {
                            foreach ($attendees as $attendee) {
                                $totalRecipients++;

                                /*
                                |--------------------------------------------------------------------------
                                | Render Message / Subject
                                |--------------------------------------------------------------------------
                                */

                                $renderedMessage =
                                    $this->renderMessage(
                                        $message,
                                        $attendee
                                    );

                                $renderedSubject =
                                    $channel
                                    === CommunicationTemplate::CHANNEL_EMAIL
                                        ? $this->renderNullableMessage(
                                            $subject,
                                            $attendee
                                        )
                                        : null;

                                /*
                                |--------------------------------------------------------------------------
                                | Resolve Recipient
                                |--------------------------------------------------------------------------
                                */

                                $recipient =
                                    $this->recipientForChannel(
                                        $attendee,
                                        $channel
                                    );

                                if (blank($recipient)) {
                                    CommunicationCampaignRecipient::create([
                                        'communication_campaign_id' =>
                                            $campaign->id,

                                        'attendee_id' =>
                                            $attendee->id,

                                        'status' =>
                                            CommunicationCampaignRecipient::STATUS_SKIPPED,

                                        'recipient' =>
                                            $this->rawRecipientForChannel(
                                                $attendee,
                                                $channel
                                            ),

                                        'rendered_subject' =>
                                            $renderedSubject,

                                        'rendered_message' =>
                                            $renderedMessage,

                                        'error_message' =>
                                            $this->invalidRecipientMessage(
                                                $channel
                                            ),

                                        'metadata' => [
                                            'reason' =>
                                                $this->invalidRecipientReason(
                                                    $channel
                                                ),

                                            'channel' =>
                                                $channel,
                                        ],
                                    ]);

                                    $failedCount++;

                                    continue;
                                }

                                /*
                                |--------------------------------------------------------------------------
                                | Communication Log
                                |--------------------------------------------------------------------------
                                */

                                $log =
                                    CommunicationLog::create([
                                        'event_id' =>
                                            $campaign->event_id,

                                        'attendee_id' =>
                                            $attendee->id,

                                        'communication_campaign_id' =>
                                            $campaign->id,

                                        'channel' =>
                                            $channel,

                                        'recipient' =>
                                            $recipient,

                                        'subject' =>
                                            $renderedSubject,

                                        'message' =>
                                            $renderedMessage,

                                        'status' =>
                                            CommunicationLog::STATUS_QUEUED,

                                        'queued_at' =>
                                            now(),
                                    ]);

                                /*
                                |--------------------------------------------------------------------------
                                | Campaign Recipient
                                |--------------------------------------------------------------------------
                                */

                                CommunicationCampaignRecipient::create([
                                    'communication_campaign_id' =>
                                        $campaign->id,

                                    'attendee_id' =>
                                        $attendee->id,

                                    'communication_log_id' =>
                                        $log->id,

                                    'status' =>
                                        CommunicationCampaignRecipient::STATUS_QUEUED,

                                    'recipient' =>
                                        $recipient,

                                    'rendered_subject' =>
                                        $renderedSubject,

                                    'rendered_message' =>
                                        $renderedMessage,

                                    'attempts' =>
                                        0,

                                    'queued_at' =>
                                        now(),

                                    'metadata' => [
                                        'channel' =>
                                            $channel,
                                    ],
                                ]);

                                /*
                                |--------------------------------------------------------------------------
                                | Dispatch
                                |--------------------------------------------------------------------------
                                */

                                $queue = match ($channel) {
                                    CommunicationTemplate::CHANNEL_EMAIL =>
                                        'communications-email',

                                    CommunicationTemplate::CHANNEL_SMS =>
                                        'communications-sms',

                                    CommunicationTemplate::CHANNEL_WHATSAPP =>
                                        'communications-whatsapp',

                                    default =>
                                        'default',
                                };

                                SendAutomaticCommunicationJob::dispatch(
                                    $log->id
                                )->onQueue(
                                    $queue
                                );

                                $queuedCount++;
                            }
                        }
                    );

                /*
                |--------------------------------------------------------------------------
                | Final Campaign Counters
                |--------------------------------------------------------------------------
                */

                $campaign->update([
                    'total_recipients' =>
                        $totalRecipients,

                    'queued_count' =>
                        $queuedCount,

                    'failed_count' =>
                        $failedCount,

                    'status' =>
                        $queuedCount > 0
                            ? CommunicationCampaign::STATUS_QUEUED
                            : CommunicationCampaign::STATUS_FAILED,

                    'completed_at' =>
                        $queuedCount > 0
                            ? null
                            : now(),
                ]);

                return $campaign->fresh();
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Audience Query
    |--------------------------------------------------------------------------
    */

    public function audienceQuery(
        Event $event,
        string $audience = 'all',
        ?int $categoryId = null
    ): Builder {
        return Attendee::query()
            ->where(
                'event_id',
                $event->id
            )
            ->whereNotIn(
                'status',
                [
                    'rejected',
                    'cancelled',
                ]
            )
            ->when(
                $categoryId,
                fn (
                    Builder $query
                ): Builder =>
                    $query->where(
                        'category_id',
                        $categoryId
                    )
            )
            ->when(
                $audience === 'registered',
                fn (
                    Builder $query
                ): Builder =>
                    $query->where(
                        'status',
                        'registered'
                    )
            )
            ->when(
                $audience === 'confirmed',
                fn (
                    Builder $query
                ): Builder =>
                    $query->where(
                        'status',
                        'confirmed'
                    )
            )
            ->when(
                $audience === 'approved',
                fn (
                    Builder $query
                ): Builder =>
                    $query->whereIn(
                        'status',
                        [
                            'registered',
                            'confirmed',
                            'approved',
                            'checked_in',
                        ]
                    )
            )
            ->when(
                $audience === 'pending_approval',
                fn (
                    Builder $query
                ): Builder =>
                    $query->where(
                        'status',
                        'pending_approval'
                    )
            )
            ->when(
                $audience === 'waitlisted',
                fn (
                    Builder $query
                ): Builder =>
                    $query->where(
                        'status',
                        'waitlisted'
                    )
            )
            ->when(
                $audience === 'checked_in',
                fn (
                    Builder $query
                ): Builder =>
                    $query->where(
                        function (
                            Builder $query
                        ): void {
                            $query
                                ->where(
                                    'status',
                                    'checked_in'
                                )
                                ->orWhereNotNull(
                                    'checked_in_at'
                                );
                        }
                    )
            )
            ->when(
                $audience === 'not_checked_in',
                fn (
                    Builder $query
                ): Builder =>
                    $query
                        ->whereNull(
                            'checked_in_at'
                        )
                        ->where(
                            'status',
                            '!=',
                            'checked_in'
                        )
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Recipient Resolution
    |--------------------------------------------------------------------------
    */

    protected function hasValidRecipientForChannel(
        Attendee $attendee,
        string $channel
    ): bool {
        return filled(
            $this->recipientForChannel(
                $attendee,
                $channel
            )
        );
    }

    protected function recipientForChannel(
        Attendee $attendee,
        string $channel
    ): ?string {
        return match ($channel) {
            CommunicationTemplate::CHANNEL_EMAIL =>
                $this->emailRecipient(
                    $attendee
                ),

            CommunicationTemplate::CHANNEL_SMS,
            CommunicationTemplate::CHANNEL_WHATSAPP =>
                $this->phoneRecipient(
                    $attendee
                ),

            default =>
                null,
        };
    }

    protected function rawRecipientForChannel(
        Attendee $attendee,
        string $channel
    ): ?string {
        return match ($channel) {
            CommunicationTemplate::CHANNEL_EMAIL =>
                filled($attendee->email)
                    ? trim(
                        (string) $attendee->email
                    )
                    : null,

            CommunicationTemplate::CHANNEL_SMS,
            CommunicationTemplate::CHANNEL_WHATSAPP =>
                filled($attendee->phone)
                    ? trim(
                        (string) $attendee->phone
                    )
                    : null,

            default =>
                null,
        };
    }

    protected function phoneRecipient(
        Attendee $attendee
    ): ?string {
        $phoneService =
            app(
                PhoneNumberService::class
            );

        if (
            ! $phoneService->isValid(
                $attendee->phone
            )
        ) {
            return null;
        }

        return $phoneService->normalize(
            $attendee->phone
        );
    }

    protected function emailRecipient(
        Attendee $attendee
    ): ?string {
        $email =
            strtolower(
                trim(
                    (string) $attendee->email
                )
            );

        if (
            $email === ''
            || ! filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {
            return null;
        }

        return $email;
    }

    protected function invalidRecipientReason(
        string $channel
    ): string {
        return match ($channel) {
            CommunicationTemplate::CHANNEL_EMAIL =>
                'invalid_email',

            CommunicationTemplate::CHANNEL_SMS =>
                'invalid_phone',

            CommunicationTemplate::CHANNEL_WHATSAPP =>
                'invalid_whatsapp_phone',

            default =>
                'invalid_recipient',
        };
    }

    protected function invalidRecipientMessage(
        string $channel
    ): string {
        return match ($channel) {
            CommunicationTemplate::CHANNEL_EMAIL =>
                'Missing or invalid email address.',

            CommunicationTemplate::CHANNEL_SMS =>
                'Missing or invalid mobile number.',

            CommunicationTemplate::CHANNEL_WHATSAPP =>
                'Missing or invalid WhatsApp mobile number.',

            default =>
                'Missing or invalid recipient.',
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Placeholder Rendering
    |--------------------------------------------------------------------------
    */

    public function renderMessage(
        string $message,
        Attendee $attendee
    ): string {
        return app(
            MessagePlaceholderService::class
        )->render(
            $message,
            $attendee
        );
    }

    public function renderNullableMessage(
        ?string $message,
        Attendee $attendee
    ): ?string {
        return app(
            MessagePlaceholderService::class
        )->renderNullable(
            $message,
            $attendee
        );
    }
}
