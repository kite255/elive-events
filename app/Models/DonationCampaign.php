<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DonationCampaign extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_ARCHIVED = 'archived';

    public const PAYMENT_MODE_PLATFORM = 'platform';
    public const PAYMENT_MODE_CLIENT_DIRECT = 'client_direct';
    public const PAYMENT_MODE_HYBRID = 'hybrid';

    public const DIRECT_BEHAVIOR_DISPLAY_ONLY = 'display_only';
    public const DIRECT_BEHAVIOR_TRACKED = 'tracked';

    protected $fillable = [
        'organization_id',
        'title',
        'slug',
        'description',
        'banner_image_path',
        'gallery_image_paths',
        'status',
        'payment_mode',
        'direct_payment_behavior',
        'currency',
        'minimum_amount',
        'suggested_amounts',
        'allow_custom_amount',
        'goal_amount',
        'show_goal',
        'show_amount_raised',
        'show_percentage',
        'show_donor_count',
        'donor_wall_enabled',
        'notification_settings',
        'is_public',
    ];

    protected static function booted(): void
    {
        static::creating(function (DonationCampaign $campaign): void {
            if (blank($campaign->slug)) {
                $campaign->slug = self::generateUniqueSlug(
                    (string) $campaign->title,
                    (int) $campaign->organization_id
                );
            }

            if (blank($campaign->status)) {
                $campaign->status = self::STATUS_DRAFT;
            }

            if (blank($campaign->currency)) {
                $campaign->currency = 'TZS';
            }

            foreach ([
                'show_goal',
                'show_amount_raised',
                'show_percentage',
                'show_donor_count',
                'donor_wall_enabled',
                'is_public',
            ] as $booleanDefault) {
                if ($campaign->{$booleanDefault} === null) {
                    $campaign->{$booleanDefault} = false;
                }
            }

            if ($campaign->allow_custom_amount === null) {
                $campaign->allow_custom_amount = true;
            }

            $campaign->currency = strtoupper((string) $campaign->currency);
        });
    }

    protected function casts(): array
    {
        return [
            'organization_id' => 'integer',
            'minimum_amount' => 'decimal:2',
            'suggested_amounts' => 'array',
            'gallery_image_paths' => 'array',
            'allow_custom_amount' => 'boolean',
            'goal_amount' => 'decimal:2',
            'show_goal' => 'boolean',
            'show_amount_raised' => 'boolean',
            'show_percentage' => 'boolean',
            'show_donor_count' => 'boolean',
            'donor_wall_enabled' => 'boolean',
            'notification_settings' => 'array',
            'is_public' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
    }

    public function paymentMethods(): HasMany
    {
        return $this->hasMany(DonationPaymentMethod::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function scopePublicActive(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_ACTIVE)
            ->where('is_public', true);
    }

    public function scopeAccessibleBy(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isSuperAdmin()) {
            return $query;
        }

        $managedOrganizationIds = $user->managedOrganizations()
            ->pluck('organizations.id');

        if ($managedOrganizationIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('organization_id', $managedOrganizationIds);
    }

    public function canBeManagedBy(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if (
            $user->managedOrganizations()
                ->where('organizations.id', $this->organization_id)
                ->exists()
        ) {
            return true;
        }
        return false;
    }

    private static function generateUniqueSlug(string $title, int $organizationId): string
    {
        $base = Str::slug($title) ?: 'campaign';
        $slug = $base;
        $suffix = 2;

        while (
            self::query()
                ->where('organization_id', $organizationId)
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }
}