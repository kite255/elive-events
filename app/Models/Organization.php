<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Organization extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_SUSPENDED = 'suspended';

    protected $fillable = [
        'name',
        'slug',
        'email',
        'phone',
        'logo_path',
        'website',
        'status',

        // Branding
        'primary_color',
        'secondary_color',
        'background_color',
        'button_color',

        // Support
        'support_email',
        'support_phone',

        // Organization details
        'description',
        'address',
        'city',
        'country',
        'timezone',
        'currency',

        // Registration and operational settings
        'registration_prefix',
        'default_language',
        'date_format',
        'time_format',

        // Subscription and limits
        'subscription_plan',
        'subscription_status',
        'subscription_starts_at',
        'subscription_ends_at',
        'maximum_users',
        'maximum_events',
        'maximum_attendees',

        // Communication settings
        'sms_enabled',
        'email_enabled',
        'whatsapp_enabled',

        // SMTP settings
        'smtp_host',
        'smtp_port',
        'smtp_username',
        'smtp_password',
        'smtp_encryption',
        'mail_from_address',
        'mail_from_name',

        // SMS settings
        'sms_provider',
        'sms_sender_id',
        'sms_api_key',
        'sms_api_secret',

        // WhatsApp settings
        'whatsapp_phone_number_id',
        'whatsapp_business_account_id',
        'whatsapp_access_token',

        // Metadata
        'settings',
    ];

    protected $hidden = [
        'smtp_password',
        'sms_api_key',
        'sms_api_secret',
        'whatsapp_access_token',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',

            'sms_enabled' => 'boolean',
            'email_enabled' => 'boolean',
            'whatsapp_enabled' => 'boolean',

            'smtp_port' => 'integer',

            'maximum_users' => 'integer',
            'maximum_events' => 'integer',
            'maximum_attendees' => 'integer',

            'subscription_starts_at' => 'datetime',
            'subscription_ends_at' => 'datetime',

            'smtp_password' => 'encrypted',
            'sms_api_key' => 'encrypted',
            'sms_api_secret' => 'encrypted',
            'whatsapp_access_token' => 'encrypted',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Organization $organization): void {
            if (blank($organization->slug)) {
                $organization->slug = static::generateUniqueSlug(
                    $organization->name
                );
            }

            if (blank($organization->status)) {
                $organization->status = self::STATUS_ACTIVE;
            }

            if (blank($organization->timezone)) {
                $organization->timezone = 'Africa/Dar_es_Salaam';
            }

            if (blank($organization->currency)) {
                $organization->currency = 'TZS';
            }

            if (blank($organization->country)) {
                $organization->country = 'Tanzania';
            }

            if (blank($organization->default_language)) {
                $organization->default_language = 'en';
            }

            if (blank($organization->date_format)) {
                $organization->date_format = 'd/m/Y';
            }

            if (blank($organization->time_format)) {
                $organization->time_format = 'H:i';
            }

            if (blank($organization->primary_color)) {
                $organization->primary_color = '#2563EB';
            }

            if (blank($organization->secondary_color)) {
                $organization->secondary_color = '#0F172A';
            }

            if (blank($organization->background_color)) {
                $organization->background_color = '#F8FAFC';
            }

            if (blank($organization->button_color)) {
                $organization->button_color = '#2563EB';
            }
        });

        static::updating(function (Organization $organization): void {
            if (
                $organization->isDirty('name')
                && blank($organization->slug)
            ) {
                $organization->slug = static::generateUniqueSlug(
                    $organization->name,
                    $organization->getKey()
                );
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot([
                'role',
                'status',
                'is_owner',
                'joined_at',
                'last_accessed_at',
            ])
            ->withTimestamps();
    }

    public function activeUsers(): BelongsToMany
    {
        return $this->users()
            ->wherePivot('status', 'active');
    }

    public function owners(): BelongsToMany
    {
        return $this->users()
            ->wherePivot('is_owner', true)
            ->wherePivot('status', 'active');
    }

    public function administrators(): BelongsToMany
    {
        return $this->users()
            ->wherePivotIn('role', [
                'owner',
                'organization_admin',
            ])
            ->wherePivot('status', 'active');
    }

    public function eventManagers(): BelongsToMany
    {
        return $this->users()
            ->wherePivot('role', 'event_manager')
            ->wherePivot('status', 'active');
    }

    public function registrationOfficers(): BelongsToMany
    {
        return $this->users()
            ->wherePivot('role', 'registration_officer')
            ->wherePivot('status', 'active');
    }

    public function checkInOfficers(): BelongsToMany
    {
        return $this->users()
            ->wherePivot('role', 'check_in_officer')
            ->wherePivot('status', 'active');
    }

    public function badgeOfficers(): BelongsToMany
    {
        return $this->users()
            ->wherePivot('role', 'badge_officer')
            ->wherePivot('status', 'active');
    }

    public function communicationOfficers(): BelongsToMany
    {
        return $this->users()
            ->wherePivot('role', 'communication_officer')
            ->wherePivot('status', 'active');
    }

    public function reportViewers(): BelongsToMany
    {
        return $this->users()
            ->wherePivot('role', 'report_viewer')
            ->wherePivot('status', 'active');
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function communicationTemplates(): HasMany
    {
        return $this->hasMany(CommunicationTemplate::class);
    }

    public function attendees(): HasManyThrough
    {
        return $this->hasManyThrough(
            Attendee::class,
            Event::class,
            'organization_id',
            'event_id',
            'id',
            'id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Query scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_INACTIVE);
    }

    public function scopeSuspended(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SUSPENDED);
    }

    public function scopeSearch(
        Builder $query,
        ?string $search
    ): Builder {
        return $query->when(
            filled($search),
            function (Builder $query) use ($search): Builder {
                return $query->where(
                    function (Builder $query) use ($search): void {
                        $query
                            ->where('name', 'ilike', "%{$search}%")
                            ->orWhere('slug', 'ilike', "%{$search}%")
                            ->orWhere('email', 'ilike', "%{$search}%")
                            ->orWhere('phone', 'ilike', "%{$search}%");
                    }
                );
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Status helpers
    |--------------------------------------------------------------------------
    */

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isInactive(): bool
    {
        return $this->status === self::STATUS_INACTIVE;
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    /*
    |--------------------------------------------------------------------------
    | User membership helpers
    |--------------------------------------------------------------------------
    */

    public function hasUser(User|int $user): bool
    {
        $userId = $user instanceof User
            ? $user->getKey()
            : $user;

        return $this->users()
            ->whereKey($userId)
            ->exists();
    }

    public function hasActiveUser(User|int $user): bool
    {
        $userId = $user instanceof User
            ? $user->getKey()
            : $user;

        return $this->users()
            ->whereKey($userId)
            ->wherePivot('status', 'active')
            ->exists();
    }

    public function userRole(User|int $user): ?string
    {
        $userId = $user instanceof User
            ? $user->getKey()
            : $user;

        $member = $this->users()
            ->whereKey($userId)
            ->first();

        return $member?->pivot?->role;
    }

    public function userHasRole(
        User|int $user,
        string|array $roles
    ): bool {
        $role = $this->userRole($user);

        if ($role === null) {
            return false;
        }

        return in_array($role, (array) $roles, true);
    }

    public function userIsOwner(User|int $user): bool
    {
        $userId = $user instanceof User
            ? $user->getKey()
            : $user;

        return $this->users()
            ->whereKey($userId)
            ->wherePivot('is_owner', true)
            ->wherePivot('status', 'active')
            ->exists();
    }

    public function userCanManage(User|int $user): bool
    {
        return $this->hasActiveUser($user)
            && (
                $this->userIsOwner($user)
                || $this->userHasRole($user, [
                    'owner',
                    'organization_admin',
                ])
            );
    }

    public function attachUser(
        User|int $user,
        string $role = 'member',
        bool $isOwner = false
    ): void {
        $userId = $user instanceof User
            ? $user->getKey()
            : $user;

        $this->users()->syncWithoutDetaching([
            $userId => [
                'role' => $role,
                'status' => 'active',
                'is_owner' => $isOwner,
                'joined_at' => now(),
            ],
        ]);
    }

    public function updateUserMembership(
        User|int $user,
        array $attributes
    ): int {
        $userId = $user instanceof User
            ? $user->getKey()
            : $user;

        return $this->users()
            ->updateExistingPivot($userId, $attributes);
    }

    public function suspendUser(User|int $user): int
    {
        return $this->updateUserMembership($user, [
            'status' => 'suspended',
        ]);
    }

    public function activateUser(User|int $user): int
    {
        return $this->updateUserMembership($user, [
            'status' => 'active',
        ]);
    }

    public function removeUser(User|int $user): int
    {
        $userId = $user instanceof User
            ? $user->getKey()
            : $user;

        return $this->users()->detach($userId);
    }

    /*
    |--------------------------------------------------------------------------
    | Subscription helpers
    |--------------------------------------------------------------------------
    */

    public function hasActiveSubscription(): bool
    {
        if ($this->subscription_status !== 'active') {
            return false;
        }

        if (
            $this->subscription_ends_at
            && $this->subscription_ends_at->isPast()
        ) {
            return false;
        }

        return true;
    }

    public function hasReachedEventLimit(): bool
    {
        if (blank($this->maximum_events)) {
            return false;
        }

        return $this->events()->count() >= $this->maximum_events;
    }

    public function hasReachedUserLimit(): bool
    {
        if (blank($this->maximum_users)) {
            return false;
        }

        return $this->activeUsers()->count() >= $this->maximum_users;
    }

    public function hasReachedAttendeeLimit(): bool
    {
        if (blank($this->maximum_attendees)) {
            return false;
        }

        return $this->attendees()->count() >= $this->maximum_attendees;
    }

    /*
    |--------------------------------------------------------------------------
    | Branding and contact helpers
    |--------------------------------------------------------------------------
    */

    public function getLogoUrlAttribute(): ?string
    {
        if (blank($this->logo_path)) {
            return null;
        }

        return asset(
            'storage/' . ltrim($this->logo_path, '/')
        );
    }

    public function getContactEmailAttribute(): ?string
    {
        return $this->support_email ?: $this->email;
    }

    public function getContactPhoneAttribute(): ?string
    {
        return $this->support_phone ?: $this->phone;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /*
    |--------------------------------------------------------------------------
    | Internal helpers
    |--------------------------------------------------------------------------
    */

    private static function generateUniqueSlug(
        string $name,
        int|string|null $ignoreId = null
    ): string {
        $baseSlug = Str::slug($name);

        if (blank($baseSlug)) {
            $baseSlug = 'organization';
        }

        $slug = $baseSlug;
        $counter = 1;

        while (
            static::query()
                ->when(
                    filled($ignoreId),
                    fn (Builder $query): Builder => $query->whereKeyNot(
                        $ignoreId
                    )
                )
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}