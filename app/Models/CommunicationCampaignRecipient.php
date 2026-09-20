<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunicationCampaignRecipient extends Model
{
    /*
    |--------------------------------------------------------------------------
    | Statuses
    |--------------------------------------------------------------------------
    */

    public const STATUS_PENDING = 'pending';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_SENT = 'sent';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    /*
    |--------------------------------------------------------------------------
    | Mass assignment
    |--------------------------------------------------------------------------
    */

    protected $fillable = [
        'communication_campaign_id',
        'attendee_id',
        'communication_log_id',

        'status',
        'recipient',

        'rendered_subject',
        'rendered_message',

        'attempts',

        'queued_at',
        'sent_at',
        'delivered_at',
        'failed_at',

        'error_message',
        'metadata',
    ];

    /*
    |--------------------------------------------------------------------------
    | Casts
    |--------------------------------------------------------------------------
    */

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',

            'queued_at' => 'datetime',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',

            'metadata' => 'array',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(
            CommunicationCampaign::class,
            'communication_campaign_id'
        );
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(
            Attendee::class
        );
    }

    public function communicationLog(): BelongsTo
    {
        return $this->belongsTo(
            CommunicationLog::class,
            'communication_log_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeForCampaign(
        Builder $query,
        int $campaignId
    ): Builder {
        return $query->where(
            'communication_campaign_id',
            $campaignId
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

    public function scopeProcessing(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            self::STATUS_PROCESSING
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

    public function scopeSkipped(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            self::STATUS_SKIPPED
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
                self::STATUS_PROCESSING,
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
                'rendered_message'
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

    public function isProcessing(): bool
    {
        return $this->status ===
            self::STATUS_PROCESSING;
    }

    public function isSent(): bool
    {
        return $this->status ===
            self::STATUS_SENT;
    }

    public function isDelivered(): bool
    {
        return $this->status ===
            self::STATUS_DELIVERED;
    }

    public function isSuccessful(): bool
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

    public function isFailed(): bool
    {
        return $this->status ===
            self::STATUS_FAILED;
    }

    public function isSkipped(): bool
    {
        return $this->status ===
            self::STATUS_SKIPPED;
    }

    public function isIncomplete(): bool
    {
        return in_array(
            $this->status,
            [
                self::STATUS_PENDING,
                self::STATUS_QUEUED,
                self::STATUS_PROCESSING,
            ],
            true
        );
    }

    public function canRetry(): bool
    {
        return $this->isFailed()
            && filled($this->recipient)
            && filled($this->rendered_message);
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

    public function attendeeName(): string
    {
        return (string) (
            $this->attendee?->full_name
            ?: 'Unknown attendee'
        );
    }

    public function recipientLabel(): string
    {
        return (string) (
            $this->recipient
            ?: '—'
        );
    }

    public function errorLabel(): string
    {
        return (string) (
            $this->error_message
            ?: '—'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Attempts
    |--------------------------------------------------------------------------
    */

    public function attemptsCount(): int
    {
        return (int) $this->attempts;
    }

    public function incrementAttempts(): void
    {
        $this->increment(
            'attempts'
        );

        $this->refresh();
    }

    /*
    |--------------------------------------------------------------------------
    | Lifecycle
    |--------------------------------------------------------------------------
    */

    public function markPending(): void
    {
        $this->forceFill([
            'status' =>
                self::STATUS_PENDING,

            'queued_at' =>
                null,

            'sent_at' =>
                null,

            'delivered_at' =>
                null,

            'failed_at' =>
                null,

            'error_message' =>
                null,
        ])->save();
    }

    public function markQueued(): void
    {
        $this->forceFill([
            'status' =>
                self::STATUS_QUEUED,

            'queued_at' =>
                now(),

            'sent_at' =>
                null,

            'delivered_at' =>
                null,

            'failed_at' =>
                null,

            'error_message' =>
                null,
        ])->save();
    }

    public function markProcessing(): void
    {
        $this->forceFill([
            'status' =>
                self::STATUS_PROCESSING,

            'failed_at' =>
                null,

            'error_message' =>
                null,
        ])->save();
    }

    public function markSent(): void
    {
        $this->forceFill([
            'status' =>
                self::STATUS_SENT,

            'sent_at' =>
                now(),

            'failed_at' =>
                null,

            'error_message' =>
                null,
        ])->save();
    }

    public function markDelivered(): void
    {
        $this->forceFill([
            'status' =>
                self::STATUS_DELIVERED,

            'delivered_at' =>
                now(),

            'failed_at' =>
                null,

            'error_message' =>
                null,
        ])->save();
    }

    public function markFailed(
        string $error
    ): void {
        $this->forceFill([
            'status' =>
                self::STATUS_FAILED,

            'failed_at' =>
                now(),

            'error_message' =>
                $error,
        ])->save();
    }

    public function markSkipped(
        ?string $reason = null
    ): void {
        $this->forceFill([
            'status' =>
                self::STATUS_SKIPPED,

            'error_message' =>
                $reason,

            'failed_at' =>
                null,
        ])->save();
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

        if ($this->communicationLog) {
            $this->communicationLog
                ->markQueued();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Communication log synchronization
    |--------------------------------------------------------------------------
    */

    public function syncFromCommunicationLog(): void
    {
        $log =
            $this->communicationLog;

        if (! $log) {
            return;
        }

        $status = match (
            $log->status
        ) {
            CommunicationLog::STATUS_PENDING =>
                self::STATUS_PENDING,

            CommunicationLog::STATUS_QUEUED =>
                self::STATUS_QUEUED,

            CommunicationLog::STATUS_SENDING =>
                self::STATUS_PROCESSING,

            CommunicationLog::STATUS_SENT =>
                self::STATUS_SENT,

            CommunicationLog::STATUS_DELIVERED =>
                self::STATUS_DELIVERED,

            CommunicationLog::STATUS_FAILED =>
                self::STATUS_FAILED,

            default =>
                $this->status,
        };

        $this->forceFill([
            'status' =>
                $status,

            'queued_at' =>
                $log->queued_at,

            'sent_at' =>
                $log->sent_at,

            'delivered_at' =>
                $log->delivered_at,

            'failed_at' =>
                $log->failed_at,

            'error_message' =>
                $log->error,
        ])->save();
    }

    /*
    |--------------------------------------------------------------------------
    | Campaign counter refresh
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

    /*
    |--------------------------------------------------------------------------
    | Metadata
    |--------------------------------------------------------------------------
    */

    public function metadataValue(
        string $key,
        mixed $default = null
    ): mixed {
        return data_get(
            $this->metadata ?? [],
            $key,
            $default
        );
    }

    public function setMetadataValue(
        string $key,
        mixed $value
    ): void {
        $metadata =
            $this->metadata ?? [];

        data_set(
            $metadata,
            $key,
            $value
        );

        $this->forceFill([
            'metadata' =>
                $metadata,
        ])->save();
    }

    public function mergeMetadata(
        array $values
    ): void {
        $metadata =
            array_merge(
                $this->metadata ?? [],
                $values
            );

        $this->forceFill([
            'metadata' =>
                $metadata,
        ])->save();
    }
}