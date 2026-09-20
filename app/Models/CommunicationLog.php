<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CommunicationLog extends Model
{
    /*
    |--------------------------------------------------------------------------
    | Purposes
    |--------------------------------------------------------------------------
    */

    public const PURPOSE_TICKET_ACCESS =
        'ticket_access';

    public const PURPOSE_TICKET_ACCESS_RECOVERY =
        'ticket_access_recovery';

    public const PURPOSE_BADGE_RESEND =
        'badge_resend';

    /*
    |--------------------------------------------------------------------------
    | Statuses
    |--------------------------------------------------------------------------
    */

    public const STATUS_PENDING = 'pending';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENDING = 'sending';

    public const STATUS_SENT = 'sent';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_FAILED = 'failed';

    /*
    |--------------------------------------------------------------------------
    | Channels
    |--------------------------------------------------------------------------
    */

    public const CHANNEL_SMS = 'sms';

    public const CHANNEL_EMAIL = 'email';

    public const CHANNEL_WHATSAPP = 'whatsapp';

    /*
    |--------------------------------------------------------------------------
    | Mass assignment
    |--------------------------------------------------------------------------
    */

    protected $fillable = [
        'event_id',
        'attendee_id',
        'ticket_order_id',
        'communication_campaign_id',

        'purpose',

        'channel',
        'recipient',

        'subject',
        'message',

        'status',
        'attempt_count',

        'provider_message_id',
        'error',

        'queued_at',
        'sent_at',
        'delivered_at',
        'failed_at',
    ];

    /*
    |--------------------------------------------------------------------------
    | Casts
    |--------------------------------------------------------------------------
    */

    protected function casts(): array
    {
        return [
            'attempt_count' => 'integer',
            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function event(): BelongsTo
    {
        return $this->belongsTo(
            Event::class
        );
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(
            Attendee::class
        );
    }

    public function ticketOrder(): BelongsTo
    {
        return $this->belongsTo(
            TicketOrder::class
        );
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(
            CommunicationCampaign::class,
            'communication_campaign_id'
        );
    }

    public function campaignRecipient(): HasOne
    {
        return $this->hasOne(
            CommunicationCampaignRecipient::class,
            'communication_log_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeForEvent(
        Builder $query,
        int $eventId
    ): Builder {
        return $query->where(
            'event_id',
            $eventId
        );
    }

    public function scopeForAttendee(
        Builder $query,
        int $attendeeId
    ): Builder {
        return $query->where(
            'attendee_id',
            $attendeeId
        );
    }

    public function scopeForCampaign(
        Builder $query,
        int $campaignId
    ): Builder {
        return $query->where(
            'communication_campaign_id',
            $campaignId
        );
    }

    public function scopeForChannel(
        Builder $query,
        string $channel
    ): Builder {
        return $query->where(
            'channel',
            $channel
        );
    }

    public function scopeSms(
        Builder $query
    ): Builder {
        return $query->where(
            'channel',
            self::CHANNEL_SMS
        );
    }

    public function scopeEmail(
        Builder $query
    ): Builder {
        return $query->where(
            'channel',
            self::CHANNEL_EMAIL
        );
    }

    public function scopeWhatsapp(
        Builder $query
    ): Builder {
        return $query->where(
            'channel',
            self::CHANNEL_WHATSAPP
        );
    }

    public function scopePending(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            self::STATUS_PENDING
        );
    }

    public function scopeQueued(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            self::STATUS_QUEUED
        );
    }

    public function scopeSending(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            self::STATUS_SENDING
        );
    }

    public function scopeSent(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            self::STATUS_SENT
        );
    }

    public function scopeDelivered(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            self::STATUS_DELIVERED
        );
    }

    public function scopeFailed(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            self::STATUS_FAILED
        );
    }

    public function scopeSuccessful(
        Builder $query
    ): Builder {
        return $query->whereIn(
            'status',
            [
                self::STATUS_SENT,
                self::STATUS_DELIVERED,
            ]
        );
    }

    public function scopeIncomplete(
        Builder $query
    ): Builder {
        return $query->whereIn(
            'status',
            [
                self::STATUS_PENDING,
                self::STATUS_QUEUED,
                self::STATUS_SENDING,
            ]
        );
    }

    public function scopeRetryable(
        Builder $query
    ): Builder {
        return $query
            ->where(
                'status',
                self::STATUS_FAILED
            )
            ->whereNotNull(
                'recipient'
            )
            ->whereNotNull(
                'message'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Status helpers
    |--------------------------------------------------------------------------
    */

    public function isPending(): bool
    {
        return $this->status ===
            self::STATUS_PENDING;
    }

    public function isQueued(): bool
    {
        return $this->status ===
            self::STATUS_QUEUED;
    }

    public function isSending(): bool
    {
        return $this->status ===
            self::STATUS_SENDING;
    }

    public function isSent(): bool
    {
        return in_array(
            $this->status,
            [
                self::STATUS_SENT,
                self::STATUS_DELIVERED,
            ],
            true
        );
    }

    public function isDelivered(): bool
    {
        return $this->status ===
            self::STATUS_DELIVERED;
    }

    public function isFailed(): bool
    {
        return $this->status ===
            self::STATUS_FAILED;
    }

    public function isIncomplete(): bool
    {
        return in_array(
            $this->status,
            [
                self::STATUS_PENDING,
                self::STATUS_QUEUED,
                self::STATUS_SENDING,
            ],
            true
        );
    }

    public function canRetry(): bool
    {
        return $this->isFailed()
            && filled($this->recipient)
            && filled($this->message);
    }

    /*
    |--------------------------------------------------------------------------
    | Channel helpers
    |--------------------------------------------------------------------------
    */

    public function isSms(): bool
    {
        return $this->channel ===
            self::CHANNEL_SMS;
    }

    public function isWhatsApp(): bool
    {
        return $this->channel ===
            self::CHANNEL_WHATSAPP;
    }

    public function isEmail(): bool
    {
        return $this->channel ===
            self::CHANNEL_EMAIL;
    }

    /*
    |--------------------------------------------------------------------------
    | Display helpers
    |--------------------------------------------------------------------------
    */

    public function statusLabel(): string
    {
        return str(
            $this->status
        )
            ->replace(
                '_',
                ' '
            )
            ->headline()
            ->toString();
    }

    public function channelLabel(): string
    {
        return match ($this->channel) {
            self::CHANNEL_SMS =>
                'SMS',

            self::CHANNEL_WHATSAPP =>
                'WhatsApp',

            self::CHANNEL_EMAIL =>
                'Email',

            default =>
                str(
                    $this->channel
                )
                    ->headline()
                    ->toString(),
        };
    }

    public function recipientLabel(): string
    {
        return (string) (
            $this->recipient
            ?: '—'
        );
    }

    public function attendeeName(): string
    {
        return (string) (
            $this->attendee?->full_name
            ?: 'Unknown attendee'
        );
    }

    public function errorLabel(): string
    {
        return (string) (
            $this->error
            ?: '—'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Lifecycle helpers
    |--------------------------------------------------------------------------
    */

    public function markPending(): void
    {
        $this->forceFill([
            'status' =>
                self::STATUS_PENDING,

            'provider_message_id' =>
                null,

            'error' =>
                null,

            'queued_at' =>
                null,

            'sent_at' =>
                null,

            'delivered_at' =>
                null,

            'failed_at' =>
                null,
        ])->save();

        $this->syncCampaignRecipient();
    }

    public function markQueued(): void
    {
        $this->forceFill([
            'status' =>
                self::STATUS_QUEUED,

            'provider_message_id' =>
                null,

            'error' =>
                null,

            'queued_at' =>
                now(),

            'sent_at' =>
                null,

            'delivered_at' =>
                null,

            'failed_at' =>
                null,
        ])->save();

        $this->syncCampaignRecipient();
    }

    public function markSending(): void
    {
        $this->forceFill([
            'status' =>
                self::STATUS_SENDING,

            'error' =>
                null,

            'failed_at' =>
                null,
        ])->save();

        $this->syncCampaignRecipient();
    }

    public function markSent(
        ?string $providerMessageId = null
    ): void {
        $this->forceFill([
            'status' =>
                self::STATUS_SENT,

            'provider_message_id' =>
                $providerMessageId,

            'sent_at' =>
                now(),

            'error' =>
                null,

            'failed_at' =>
                null,
        ])->save();

        $this->syncCampaignRecipient();
    }

    public function markDelivered(): void
    {
        $this->forceFill([
            'status' =>
                self::STATUS_DELIVERED,

            'delivered_at' =>
                now(),

            'error' =>
                null,

            'failed_at' =>
                null,
        ])->save();

        $this->syncCampaignRecipient();
    }

    public function markFailed(
        string $error
    ): void {
        $this->forceFill([
            'status' =>
                self::STATUS_FAILED,

            'failed_at' =>
                now(),

            'error' =>
                $error,
        ])->save();

        $this->syncCampaignRecipient();
    }

    /*
    |--------------------------------------------------------------------------
    | Retry
    |--------------------------------------------------------------------------
    */

    public function prepareForRetry(): void
    {
        if (! $this->canRetry()) {
            return;
        }

        $this->markQueued();
    }

    /*
    |--------------------------------------------------------------------------
    | Campaign recipient synchronization
    |--------------------------------------------------------------------------
    */

    public function syncCampaignRecipient(): void
    {
        $recipient =
            $this->campaignRecipient;

        if (! $recipient) {
            return;
        }

        $recipientStatus = match (
            $this->status
        ) {
            self::STATUS_PENDING =>
                CommunicationCampaignRecipient::STATUS_PENDING,

            self::STATUS_QUEUED =>
                CommunicationCampaignRecipient::STATUS_QUEUED,

            self::STATUS_SENDING =>
                CommunicationCampaignRecipient::STATUS_PROCESSING,

            self::STATUS_SENT =>
                CommunicationCampaignRecipient::STATUS_SENT,

            self::STATUS_DELIVERED =>
                CommunicationCampaignRecipient::STATUS_DELIVERED,

            self::STATUS_FAILED =>
                CommunicationCampaignRecipient::STATUS_FAILED,

            default =>
                $recipient->status,
        };

        $recipient->forceFill([
            'status' =>
                $recipientStatus,

            'queued_at' =>
                $this->queued_at,

            'sent_at' =>
                $this->sent_at,

            'delivered_at' =>
                $this->delivered_at,

            'failed_at' =>
                $this->failed_at,

            'error_message' =>
                $this->error,
        ])->save();
    }

    /*
    |--------------------------------------------------------------------------
    | Campaign counters
    |--------------------------------------------------------------------------
    */

    public function refreshCampaignCounters(): void
    {
        if (! $this->campaign) {
            return;
        }

        $this->campaign
            ->refreshCounters();
    }
}
