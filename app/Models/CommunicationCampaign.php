<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunicationCampaign extends Model
{
    /*
    |--------------------------------------------------------------------------
    | Statuses
    |--------------------------------------------------------------------------
    */

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

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
        'communication_template_id',
        'created_by',

        'name',
        'channel',
        'type',
        'subject',
        'message',
        'status',

        'recipient_filter',

        'total_recipients',
        'queued_count',
        'sent_count',
        'delivered_count',
        'failed_count',

        'scheduled_at',
        'started_at',
        'sent_at',
        'completed_at',
        'cancelled_at',
    ];

    /*
    |--------------------------------------------------------------------------
    | Casts
    |--------------------------------------------------------------------------
    */

    protected function casts(): array
    {
        return [
            'recipient_filter' =>
                'array',

            'total_recipients' =>
                'integer',

            'queued_count' =>
                'integer',

            'sent_count' =>
                'integer',

            'delivered_count' =>
                'integer',

            'failed_count' =>
                'integer',

            'scheduled_at' =>
                'datetime',

            'started_at' =>
                'datetime',

            'sent_at' =>
                'datetime',

            'completed_at' =>
                'datetime',

            'cancelled_at' =>
                'datetime',
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

    public function template(): BelongsTo
    {
        return $this->belongsTo(
            CommunicationTemplate::class,
            'communication_template_id'
        );
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    public function recipients(): HasMany
    {
        return $this->hasMany(
            CommunicationCampaignRecipient::class,
            'communication_campaign_id'
        );
    }

    public function logs(): HasMany
    {
        return $this->hasMany(
            CommunicationLog::class,
            'communication_campaign_id'
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

    public function scopeDraft(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            self::STATUS_DRAFT
        );
    }

    public function scopeScheduled(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            self::STATUS_SCHEDULED
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

    public function scopeProcessing(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            self::STATUS_PROCESSING
        );
    }

    public function scopeCompleted(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            self::STATUS_COMPLETED
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

    public function scopeCancelled(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            self::STATUS_CANCELLED
        );
    }

    public function scopeActive(
        Builder $query
    ): Builder {
        return $query->whereIn(
            'status',
            [
                self::STATUS_SCHEDULED,
                self::STATUS_QUEUED,
                self::STATUS_PROCESSING,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Status helpers
    |--------------------------------------------------------------------------
    */

    public function isDraft(): bool
    {
        return $this->status ===
            self::STATUS_DRAFT;
    }

    public function isScheduled(): bool
    {
        return $this->status ===
            self::STATUS_SCHEDULED;
    }

    public function isQueued(): bool
    {
        return $this->status ===
            self::STATUS_QUEUED;
    }

    public function isProcessing(): bool
    {
        return in_array(
            $this->status,
            [
                self::STATUS_QUEUED,
                self::STATUS_PROCESSING,
            ],
            true
        );
    }

    public function isCompleted(): bool
    {
        return $this->status ===
            self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status ===
            self::STATUS_FAILED;
    }

    public function isCancelled(): bool
    {
        return $this->status ===
            self::STATUS_CANCELLED;
    }

    public function canSend(): bool
    {
        return in_array(
            $this->status,
            [
                self::STATUS_DRAFT,
                self::STATUS_SCHEDULED,
            ],
            true
        );
    }

    public function canCancel(): bool
    {
        return in_array(
            $this->status,
            [
                self::STATUS_DRAFT,
                self::STATUS_SCHEDULED,
                self::STATUS_QUEUED,
            ],
            true
        );
    }

    public function canRetry(): bool
    {
        return $this->failedCount() > 0
            && ! $this->isCancelled();
    }

    /*
    |--------------------------------------------------------------------------
    | Stored counter helpers
    |--------------------------------------------------------------------------
    */

    public function totalRecipientsCount(): int
    {
        return (int) $this->total_recipients;
    }

    public function queuedCount(): int
    {
        return (int) $this->queued_count;
    }

    public function sentCount(): int
    {
        return (int) $this->sent_count;
    }

    public function deliveredCount(): int
    {
        return (int) $this->delivered_count;
    }

    public function failedCount(): int
    {
        return (int) $this->failed_count;
    }

    /*
    |--------------------------------------------------------------------------
    | Recipient status counters
    |--------------------------------------------------------------------------
    |
    | These statuses do not currently have dedicated columns on
    | communication_campaigns, so they are calculated from recipients.
    |--------------------------------------------------------------------------
    */

    public function pendingCount(): int
    {
        return $this->recipientStatusCount(
            CommunicationCampaignRecipient::STATUS_PENDING
        );
    }

    public function processingCount(): int
    {
        return $this->recipientStatusCount(
            CommunicationCampaignRecipient::STATUS_PROCESSING
        );
    }

    public function skippedCount(): int
    {
        return $this->recipientStatusCount(
            CommunicationCampaignRecipient::STATUS_SKIPPED
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Progress helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Successful means accepted/sent OR delivered.
     *
     * Recipient statuses are mutually exclusive, so sent and delivered
     * must both be counted.
     */
    public function successfulCount(): int
    {
        return
            $this->sentCount()
            + $this->deliveredCount();
    }

    /**
     * Finished processing includes:
     *
     * - sent
     * - delivered
     * - failed
     * - skipped
     */
    public function processedCount(): int
    {
        return
            $this->sentCount()
            + $this->deliveredCount()
            + $this->failedCount()
            + $this->skippedCount();
    }

    /**
     * Still waiting to reach a terminal status.
     */
    public function remainingCount(): int
    {
        return max(
            0,
            $this->totalRecipientsCount()
            - $this->processedCount()
        );
    }

    public function completionPercentage(): float
    {
        $total =
            $this->totalRecipientsCount();

        if ($total <= 0) {
            return 0;
        }

        return min(
            100,
            round(
                (
                    $this->processedCount()
                    / $total
                ) * 100,
                2
            )
        );
    }

    public function successPercentage(): float
    {
        $processed =
            $this->processedCount();

        if ($processed <= 0) {
            return 0;
        }

        return min(
            100,
            round(
                (
                    $this->successfulCount()
                    / $processed
                ) * 100,
                2
            )
        );
    }

    public function failurePercentage(): float
    {
        $processed =
            $this->processedCount();

        if ($processed <= 0) {
            return 0;
        }

        return min(
            100,
            round(
                (
                    $this->failedCount()
                    / $processed
                ) * 100,
                2
            )
        );
    }

    public function skippedPercentage(): float
    {
        $total =
            $this->totalRecipientsCount();

        if ($total <= 0) {
            return 0;
        }

        return min(
            100,
            round(
                (
                    $this->skippedCount()
                    / $total
                ) * 100,
                2
            )
        );
    }

    public function hasRemainingRecipients(): bool
    {
        return $this->remainingCount() > 0;
    }

    public function hasFailures(): bool
    {
        return $this->failedCount() > 0;
    }

    public function hasSuccessfulRecipients(): bool
    {
        return $this->successfulCount() > 0;
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
        return match (
            $this->channel
        ) {
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

    /*
    |--------------------------------------------------------------------------
    | Audience helpers
    |--------------------------------------------------------------------------
    */

    public function audience(): string
    {
        return (string) data_get(
            $this->recipient_filter,
            'audience',
            'all'
        );
    }

    public function categoryId(): ?int
    {
        $categoryId = data_get(
            $this->recipient_filter,
            'category_id'
        );

        return filled($categoryId)
            ? (int) $categoryId
            : null;
    }

    public function audienceLabel(): string
    {
        return str(
            $this->audience()
        )
            ->replace(
                '_',
                ' '
            )
            ->headline()
            ->toString();
    }

    /*
    |--------------------------------------------------------------------------
    | Campaign lifecycle
    |--------------------------------------------------------------------------
    */

    public function markAsQueued(): void
    {
        $this->forceFill([
            'status' =>
                self::STATUS_QUEUED,

            'started_at' =>
                $this->started_at
                ?? now(),

            'completed_at' =>
                null,

            'cancelled_at' =>
                null,
        ])->save();
    }

    public function markAsProcessing(): void
    {
        if ($this->isCancelled()) {
            return;
        }

        $this->forceFill([
            'status' =>
                self::STATUS_PROCESSING,

            'started_at' =>
                $this->started_at
                ?? now(),

            'completed_at' =>
                null,
        ])->save();
    }

    public function markAsCompleted(): void
    {
        if ($this->isCancelled()) {
            return;
        }

        $this->forceFill([
            'status' =>
                self::STATUS_COMPLETED,

            'completed_at' =>
                now(),
        ])->save();
    }

    public function markAsFailed(): void
    {
        if ($this->isCancelled()) {
            return;
        }

        $this->forceFill([
            'status' =>
                self::STATUS_FAILED,

            'completed_at' =>
                now(),
        ])->save();
    }

    public function markAsCancelled(): void
    {
        $this->forceFill([
            'status' =>
                self::STATUS_CANCELLED,

            'cancelled_at' =>
                now(),
        ])->save();
    }

    /*
    |--------------------------------------------------------------------------
    | Campaign status synchronization
    |--------------------------------------------------------------------------
    */

    /**
     * Recalculate both counters and the campaign lifecycle status.
     */
    public function synchronizeStatus(): void
    {
        if ($this->isCancelled()) {
            return;
        }

        $this->refreshCounters();

        $this->refresh();

        /*
         * Campaign still has recipients in a non-terminal state.
         */
        if ($this->remainingCount() > 0) {
            $this->markAsProcessing();

            return;
        }

        /*
         * At least one recipient succeeded.
         *
         * Partial failure is still considered a completed campaign.
         * Failed recipients remain available for retry.
         */
        if ($this->successfulCount() > 0) {
            $this->markAsCompleted();

            return;
        }

        /*
         * Nothing succeeded and failures exist.
         */
        if ($this->failedCount() > 0) {
            $this->markAsFailed();

            return;
        }

        /*
         * All recipients may have been skipped.
         * The campaign work is still finished.
         */
        if (
            $this->totalRecipientsCount() > 0
            && $this->processedCount()
                >= $this->totalRecipientsCount()
        ) {
            $this->markAsCompleted();

            return;
        }

        /*
         * No recipients yet: preserve current lifecycle state.
         */
    }

    /*
    |--------------------------------------------------------------------------
    | Counter synchronization
    |--------------------------------------------------------------------------
    */

    /**
     * Recalculate stored campaign counters from recipient records.
     *
     * PostgreSQL FILTER is intentionally used because eLive Events
     * currently runs PostgreSQL.
     */
    public function refreshCounters(): void
    {
        $counts = $this->recipients()
            ->selectRaw(
                "
                COUNT(*) AS total_count,

                COUNT(*) FILTER (
                    WHERE status = ?
                ) AS queued_count,

                COUNT(*) FILTER (
                    WHERE status = ?
                ) AS sent_count,

                COUNT(*) FILTER (
                    WHERE status = ?
                ) AS delivered_count,

                COUNT(*) FILTER (
                    WHERE status = ?
                ) AS failed_count
                ",
                [
                    CommunicationCampaignRecipient::STATUS_QUEUED,
                    CommunicationCampaignRecipient::STATUS_SENT,
                    CommunicationCampaignRecipient::STATUS_DELIVERED,
                    CommunicationCampaignRecipient::STATUS_FAILED,
                ]
            )
            ->first();

        if (! $counts) {
            return;
        }

        $this->forceFill([
            'total_recipients' =>
                (int) $counts->total_count,

            'queued_count' =>
                (int) $counts->queued_count,

            'sent_count' =>
                (int) $counts->sent_count,

            'delivered_count' =>
                (int) $counts->delivered_count,

            'failed_count' =>
                (int) $counts->failed_count,
        ])->save();
    }

    /*
    |--------------------------------------------------------------------------
    | Internal counter helper
    |--------------------------------------------------------------------------
    */

    private function recipientStatusCount(
        string $status
    ): int {
        /*
         * If the recipients relationship is already loaded,
         * avoid another query.
         */
        if ($this->relationLoaded('recipients')) {
            return $this->recipients
                ->where(
                    'status',
                    $status
                )
                ->count();
        }

        return $this->recipients()
            ->where(
                'status',
                $status
            )
            ->count();
    }
}