<?php

namespace App\Services;

use App\Jobs\SendAutomaticCommunicationJob;
use App\Models\Attendee;
use App\Models\CommunicationCampaign;
use App\Models\CommunicationCampaignRecipient;
use App\Models\CommunicationLog;
use App\Models\CommunicationTemplate;
use App\Models\Event;
use App\Models\TicketOrder;
use App\Models\User;
use App\Services\Tickets\TicketAccessMessageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CommunicationCampaignService
{
    public const PAID_TICKET_BUYERS = 'paid_ticket_buyers';
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
        string $channel = CommunicationTemplate::CHANNEL_SMS,
        ?string $message = null
    ): array {
        $event = Event::query()->findOrFail($event->getKey());
        $this->authorizeCampaignEvent($event);
        $this->validateAudience($event, $audience, $categoryId);

        if ($audience === self::PAID_TICKET_BUYERS) {
            $total = $valid = $aboveThree = $segments = $maxSegments = 0;
            $countSegments = $channel === CommunicationTemplate::CHANNEL_SMS && filled($message);
            $factory = app(TicketAccessMessageFactory::class);
            $sms = $countSegments ? app(SmsService::class) : null;
            $orders = $this->ticketBuyerQuery($event)->when($countSegments, fn (Builder $query) => $query->with('items.ticketType'));

            foreach ($orders->lazyById(500) as $order) {
                $total++;
                if ($this->ticketBuyerRecipient($order, $channel) === null) {
                    continue;
                }

                $valid++;
                if ($sms) {
                    $order->setRelation('event', $event);
                    $count = $sms->segmentCount($factory->renderForOrder((string) $message, $order));
                    $segments += $count;
                    $maxSegments = max($maxSegments, $count);
                    $aboveThree += (int) ($count > 3);
                }
            }

            return [
                'total' => $total,
                'valid' => $valid,
                'invalid' => $total - $valid,
                'sms_segments' => $segments,
                'max_sms_segments' => $maxSegments,
                'above_three_sms_segments' => $aboveThree,
            ];
        }

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
        ?int $createdBy = null,
        ?string $emailPosterPath = null,
        ?string $emailActionUrl = null,
        ?string $emailActionLabel = null
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
            createdBy: $createdBy,
            emailPosterPath: $emailPosterPath,
            emailActionUrl: $emailActionUrl,
            emailActionLabel: $emailActionLabel
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
        ?int $createdBy = null,
        ?string $emailPosterPath = null,
        ?string $emailActionUrl = null,
        ?string $emailActionLabel = null
    ): CommunicationCampaign {
        $event = Event::query()->findOrFail($event->getKey());
        $this->authorizeCampaignEvent($event, $createdBy);
        $this->validateAudience($event, $audience, $categoryId);
        if ($template) {
            $template = CommunicationTemplate::query()->findOrFail($template->getKey());
        }

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

        if ($audience === self::PAID_TICKET_BUYERS && ! in_array($channel, ['sms', 'email'], true)) {
            throw new RuntimeException('Choose SMS or email for paid ticket buyers.');
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
                $createdBy,
                $emailPosterPath,
                $emailActionUrl,
                $emailActionLabel
            ): CommunicationCampaign {
                $ticketBuyerCampaign = $audience === self::PAID_TICKET_BUYERS;

                $event = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
                $this->authorizeCampaignEvent($event, $createdBy);
                $this->validateAudience($event, $audience, $categoryId);

                $duplicates = CommunicationCampaign::query()
                    ->where('event_id', $event->id)
                    ->where('type', 'manual_campaign')
                    ->where('channel', $channel)
                    ->where('recipient_filter->audience', $audience)
                    ->where('name', $name)
                    ->where('message', $message)
                    ->where('subject', $subject)
                    ->where(function (Builder $query): void {
                        $query->whereNotIn('status', [CommunicationCampaign::STATUS_CANCELLED, CommunicationCampaign::STATUS_FAILED])
                            ->orWhere('sent_count', '>', 0)
                            ->orWhere('delivered_count', '>', 0);
                    });

                // Preserve the existing ticket buyer protection. For attendees,
                // allow a deliberate follow-up after the earlier campaign finishes
                // and the short duplicate-submission window expires.
                if (! $ticketBuyerCampaign) {
                    $cooldown = max(1, (int) config('messaging.campaign_duplicate_cooldown_seconds', 60));
                    $duplicates->where(function (Builder $query) use ($cooldown): void {
                        $query->whereIn('status', [
                            CommunicationCampaign::STATUS_SCHEDULED,
                            CommunicationCampaign::STATUS_QUEUED,
                            CommunicationCampaign::STATUS_PROCESSING,
                        ])->orWhere('created_at', '>=', now()->subSeconds($cooldown));
                    });
                }

                foreach ($duplicates->latest('id')->lazy() as $duplicate) {
                    if ($duplicate->categoryId() !== $categoryId) {
                        continue;
                    }

                    if ($channel === CommunicationTemplate::CHANNEL_EMAIL
                        && (data_get($duplicate->recipient_filter, 'email_poster_path') !== $emailPosterPath
                            || data_get($duplicate->recipient_filter, 'email_action_url') !== $emailActionUrl
                            || data_get($duplicate->recipient_filter, 'email_action_label') !== $emailActionLabel)) {
                        continue;
                    }

                    return $duplicate;
                }

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
                            'email_poster_path' => $channel === CommunicationTemplate::CHANNEL_EMAIL ? $emailPosterPath : null,
                            'email_action_url' => $channel === CommunicationTemplate::CHANNEL_EMAIL ? $emailActionUrl : null,
                            'email_action_label' => $channel === CommunicationTemplate::CHANNEL_EMAIL ? $emailActionLabel : null,
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

                $query = $ticketBuyerCampaign
                    ? $this->ticketBuyerQuery($event)
                    : $this->audienceQuery($event, $audience, $categoryId)->with([
                        'event.organization',
                        'category:id,name',
                        'badgeType:id,name',
                    ]);

                $query
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
                            foreach ($attendees as $member) {
                                $totalRecipients++;
                                $order = $member instanceof TicketOrder ? $member : null;
                                $attendee = $order ? null : $member;

                                /*
                                |--------------------------------------------------------------------------
                                | Render Message / Subject
                                |--------------------------------------------------------------------------
                                */

                                $renderedMessage =
                                    $order ? $message : $this->renderMessage($message, $attendee);

                                $renderedSubject =
                                    $channel
                                    === CommunicationTemplate::CHANNEL_EMAIL
                                        ? ($order ? $subject : $this->renderNullableMessage($subject, $attendee))
                                        : null;

                                /*
                                |--------------------------------------------------------------------------
                                | Resolve Recipient
                                |--------------------------------------------------------------------------
                                */

                                $recipient =
                                    $order
                                        ? $this->ticketBuyerRecipient($order, $channel)
                                        : $this->recipientForChannel($attendee, $channel);

                                if (blank($recipient)) {
                                    CommunicationCampaignRecipient::create([
                                        'communication_campaign_id' =>
                                            $campaign->id,

                                        'attendee_id' => $attendee?->id,
                                        'ticket_order_id' => $order?->id,

                                        'status' =>
                                            CommunicationCampaignRecipient::STATUS_SKIPPED,

                                        'recipient' =>
                                            $order ? null : $this->rawRecipientForChannel($attendee, $channel),

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

                                        'attendee_id' => $attendee?->id,
                                        'ticket_order_id' => $order?->id,
                                        'purpose' => $order ? CommunicationLog::PURPOSE_TICKET_BUYER_CAMPAIGN : null,

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

                                    'attendee_id' => $attendee?->id,
                                    'ticket_order_id' => $order?->id,

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
                                )
                                    ->onQueue(
                                        $queue
                                    )
                                    ->afterCommit();

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

                return $campaign->refresh();
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Audience Query
    |--------------------------------------------------------------------------
    */

    private function authorizeCampaignEvent(Event $event, ?int $createdBy = null): void
    {
        $authenticated = auth()->user();
        if ($authenticated !== null && (! $authenticated instanceof User
            || ($createdBy !== null && (int) $authenticated->getKey() !== $createdBy)
            || ! $authenticated->canManageEventCommunication($event))) {
            throw new RuntimeException('You are not allowed to manage communications for this event.');
        }

        if ($createdBy !== null) {
            $actor = User::query()->find($createdBy);
            if (! $actor || ! $actor->canManageEventCommunication($event)) {
                throw new RuntimeException('You are not allowed to manage communications for this event.');
            }
        }
    }

    private function validateAudience(Event $event, string $audience, ?int $categoryId): void
    {
        if (! in_array($audience, ['all', 'registered', 'confirmed', 'approved', 'pending_approval', 'waitlisted', 'checked_in', 'not_checked_in', self::PAID_TICKET_BUYERS], true)) {
            throw new RuntimeException('Unsupported campaign audience.');
        }

        if ($categoryId !== null && ($audience === self::PAID_TICKET_BUYERS
            || ! $event->attendeeCategories()->whereKey($categoryId)->exists())) {
            throw new RuntimeException('The selected category does not belong to this campaign audience.');
        }
    }

    private function ticketBuyerQuery(Event $event): Builder
    {
        return TicketOrder::query()
            ->where('event_id', $event->id)
            ->where('status', TicketOrder::STATUS_PAID)
            ->whereNotNull('public_token')
            ->where('public_token', '!=', '');
    }

    public function ticketBuyerRecipient(TicketOrder $order, string $channel): ?string
    {
        if (! $order->isPaid() || blank($order->public_token)) {
            return null;
        }

        if ($channel === CommunicationTemplate::CHANNEL_EMAIL) {
            $email = strtolower(trim((string) $order->buyer_email));

            return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
        }

        $phones = app(PhoneNumberService::class);
        if ($channel === CommunicationTemplate::CHANNEL_SMS && $phones->isValid($order->buyer_phone)) {
            return $phones->normalize($order->buyer_phone);
        }

        return null;
    }

    public function audienceQuery(
        Event $event,
        string $audience = 'all',
        ?int $categoryId = null
    ): Builder {
        $this->authorizeCampaignEvent($event);
        $this->validateAudience($event, $audience, $categoryId);

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
