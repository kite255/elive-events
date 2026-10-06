<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Donation extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_AWAITING_PAYMENT = 'awaiting_payment';
    public const STATUS_AWAITING_VERIFICATION = 'awaiting_verification';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'donation_campaign_id',
        'public_token',
        'reference',
        'donor_name',
        'donor_phone',
        'donor_email',
        'amount',
        'currency',
        'is_anonymous',
        'public_display_consent',
        'payment_type',
        'status',
        'completed_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (Donation $donation): void {
            if (blank($donation->public_token)) {
                do {
                    $token = Str::random(48);
                } while (
                    self::query()
                        ->where('public_token', $token)
                        ->exists()
                );

                $donation->public_token = $token;
            }

            if (blank($donation->status)) {
                $donation->status = self::STATUS_PENDING;
            }

            if (blank($donation->currency)) {
                $donation->currency = 'TZS';
            }

            $donation->currency = strtoupper((string) $donation->currency);
        });
    }

    protected function casts(): array
    {
        return [
            'donation_campaign_id' => 'integer',
            'amount' => 'decimal:2',
            'is_anonymous' => 'boolean',
            'public_display_consent' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(DonationCampaign::class, 'donation_campaign_id');
    }

    public function manualSubmission(): HasOne
    {
        return $this->hasOne(DonationManualSubmission::class);
    }

    public function scopeAccessibleBy(Builder $query, ?User $user): Builder
    {
        return $query->whereHas(
            'campaign',
            fn (Builder $campaignQuery): Builder => $campaignQuery->accessibleBy($user)
        );
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }
}
