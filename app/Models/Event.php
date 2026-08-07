<?php

namespace App\Models;

use App\Services\BadgeNumberService;
use App\Services\EventPresetService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Str;

class Event extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STAFF_STATUS_ACTIVE = 'active';

    public const STAFF_STATUS_INACTIVE = 'inactive';

    public const STAFF_STATUS_SUSPENDED = 'suspended';

    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'event_code',
        'event_type',
        'custom_event_type',
        'venue',
        'description',
        'starts_at',
        'ends_at',
        'capacity',
        'status',

        'schedule_mode',
        'registration_allow_day_selection',
        'registration_allow_all_days',
        'sessions_enabled',
        'session_registration_enabled',
        'session_check_in_enabled',

        'registration_is_open',
        'registration_requires_approval',
        'registration_banner_image_path',
        'registration_logo_path',
        'registration_primary_color',
        'registration_background_color',
        'registration_button_color',
        'registration_welcome_title',
        'registration_welcome_message',
        'registration_success_message',
        'registration_auto_generate_badge',

        'registration_waitlist_enabled',
        'registration_waitlist_message',

        'registration_show_phone',
        'registration_require_phone',

        'registration_show_email',
        'registration_require_email',

        'registration_show_organization',
        'registration_require_organization',

        'registration_show_position',
        'registration_require_position',

        'registration_show_category',
        'registration_require_category',

        'registration_show_badge_type',
        'registration_require_badge_type',

        'show_merchandise_images',
    ];

    protected function casts(): array
    {
        return [
            'organization_id' => 'integer',

            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'capacity' => 'integer',

            'registration_allow_day_selection' => 'boolean',
            'registration_allow_all_days' => 'boolean',
            'sessions_enabled' => 'boolean',
            'session_registration_enabled' => 'boolean',
            'session_check_in_enabled' => 'boolean',

            'registration_is_open' => 'boolean',
            'registration_requires_approval' => 'boolean',
            'registration_auto_generate_badge' => 'boolean',
            'registration_waitlist_enabled' => 'boolean',

            'registration_show_phone' => 'boolean',
            'registration_require_phone' => 'boolean',

            'registration_show_email' => 'boolean',
            'registration_require_email' => 'boolean',

            'registration_show_organization' => 'boolean',
            'registration_require_organization' => 'boolean',

            'registration_show_position' => 'boolean',
            'registration_require_position' => 'boolean',

            'registration_show_category' => 'boolean',
            'registration_require_category' => 'boolean',

            'registration_show_badge_type' => 'boolean',
            'registration_require_badge_type' => 'boolean',

            'show_merchandise_images' => 'boolean',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Boot
    |--------------------------------------------------------------------------
    */

    protected static function booted(): void
    {
        static::creating(function (Event $event): void {
            if (
                blank($event->organization_id)
                && auth()->check()
            ) {
                $organizationId = auth()
                    ->user()
                    ?->activeOrganizations()
                    ->value('organizations.id');

                if ($organizationId) {
                    $event->organization_id = $organizationId;
                }
            }

            if (blank($event->slug)) {
                $event->slug = static::generateUniqueSlug(
                    $event->name,
                    organizationId: $event->organization_id
                );
            }

            if (blank($event->status)) {
                $event->status = self::STATUS_DRAFT;
            }

            if (filled($event->event_code)) {
                $event->event_code = strtoupper(
                    trim($event->event_code)
                );
            }
        });

        static::created(function (Event $event): void {
            app(BadgeNumberService::class)
                ->assignEventCode($event);
        });

        static::updating(function (Event $event): void {
            if (
                $event->isDirty('name')
                && blank($event->slug)
            ) {
                $event->slug = static::generateUniqueSlug(
                    $event->name,
                    ignoreId: $event->getKey(),
                    organizationId: $event->organization_id
                );
            }

            if (
                $event->isDirty('event_code')
                && filled($event->event_code)
            ) {
                $event->event_code = strtoupper(
                    trim($event->event_code)
                );
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Slug generation
    |--------------------------------------------------------------------------
    */

    public static function generateUniqueSlug(
        ?string $name,
        ?int $ignoreId = null,
        ?int $organizationId = null
    ): string {
        $baseSlug = Str::slug($name ?: 'event');

        if (blank($baseSlug)) {
            $baseSlug = 'event';
        }

        $slug = $baseSlug;
        $counter = 2;

        while (
            static::query()
                ->when(
                    $organizationId,
                    fn (Builder $query): Builder =>
                        $query->where(
                            'organization_id',
                            $organizationId
                        )
                )
                ->where('slug', $slug)
                ->when(
                    $ignoreId,
                    fn (Builder $query): Builder =>
                        $query->whereKeyNot($ignoreId)
                )
                ->exists()
        ) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    /*
    |--------------------------------------------------------------------------
    | Organization
    |--------------------------------------------------------------------------
    */

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Event staff
    |--------------------------------------------------------------------------
    */

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot([
                'role',
                'status',
                'assigned_at',
                'last_accessed_at',
            ])
            ->withTimestamps();
    }

    public function activeUsers(): BelongsToMany
    {
        return $this->users()
            ->wherePivot(
                'status',
                self::STAFF_STATUS_ACTIVE
            );
    }

    public function eventManagers(): BelongsToMany
    {
        return $this->activeUsers()
            ->wherePivot(
                'role',
                User::ORGANIZATION_ROLE_EVENT_MANAGER
            );
    }

    public function registrationOfficers(): BelongsToMany
    {
        return $this->activeUsers()
            ->wherePivot(
                'role',
                User::ORGANIZATION_ROLE_REGISTRATION_OFFICER
            );
    }

    public function checkInOfficers(): BelongsToMany
    {
        return $this->activeUsers()
            ->wherePivot(
                'role',
                User::ORGANIZATION_ROLE_CHECK_IN_OFFICER
            );
    }

    public function badgeOfficers(): BelongsToMany
    {
        return $this->activeUsers()
            ->wherePivot(
                'role',
                User::ORGANIZATION_ROLE_BADGE_OFFICER
            );
    }

    public function communicationOfficers(): BelongsToMany
    {
        return $this->activeUsers()
            ->wherePivot(
                'role',
                User::ORGANIZATION_ROLE_COMMUNICATION_OFFICER
            );
    }

    public function reportViewers(): BelongsToMany
    {
        return $this->activeUsers()
            ->wherePivot(
                'role',
                User::ORGANIZATION_ROLE_REPORT_VIEWER
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Event setup
    |--------------------------------------------------------------------------
    */

    public function attendeeCategories(): HasMany
    {
        return $this->hasMany(AttendeeCategory::class);
    }

    public function badgeTypes(): HasMany
    {
        return $this->hasMany(BadgeType::class);
    }

    public function badgeTemplates(): HasMany
    {
        return $this->hasMany(BadgeTemplate::class);
    }

    public function registrationFields(): HasMany
    {
        return $this->hasMany(EventRegistrationField::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /*
    |--------------------------------------------------------------------------
    | Event days
    |--------------------------------------------------------------------------
    */

    public function days(): HasMany
    {
        return $this->hasMany(EventDay::class)
            ->orderBy('event_date')
            ->orderBy('display_order')
            ->orderBy('id');
    }

    public function activeDays(): HasMany
    {
        return $this->days()
            ->where('status', 'active');
    }

    public function openRegistrationDays(): HasMany
    {
        return $this->days()
            ->where('status', 'active')
            ->where('is_registration_open', true);
    }

    /*
    |--------------------------------------------------------------------------
    | Sessions / Activities
    |--------------------------------------------------------------------------
    */

    public function sessions(): HasMany
    {
        return $this->hasMany(EventSession::class)
            ->orderBy('event_day_id')
            ->orderBy('display_order')
            ->orderBy('starts_at')
            ->orderBy('id');
    }

    public function activeSessions(): HasMany
    {
        return $this->sessions()
            ->where('status', EventSession::STATUS_ACTIVE);
    }

    public function registrationOpenSessions(): HasMany
    {
        return $this->sessions()
            ->where('status', EventSession::STATUS_ACTIVE)
            ->where('requires_registration', true)
            ->where('registration_is_open', true);
    }

    /*
    |--------------------------------------------------------------------------
    | Merchandise
    |--------------------------------------------------------------------------
    */

    public function merchandise(): HasMany
    {
        return $this->hasMany(EventMerchandise::class)
            ->orderBy('display_order')
            ->orderBy('id');
    }

    public function activeMerchandise(): HasMany
    {
        return $this->merchandise()
            ->where('is_active', true);
    }

    public function attendeeMerchandiseOrders(): HasManyThrough
    {
        return $this->hasManyThrough(
            AttendeeMerchandise::class,
            Attendee::class,
            'event_id',
            'attendee_id',
            'id',
            'id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Attendees
    |--------------------------------------------------------------------------
    */

    public function attendees(): HasMany
    {
        return $this->hasMany(Attendee::class);
    }

    public function approvedAttendees(): HasMany
    {
        return $this->attendees()
            ->whereIn('status', [
                'registered',
                'confirmed',
                'checked_in',
            ]);
    }

    public function pendingApprovalAttendees(): HasMany
    {
        return $this->attendees()
            ->where('status', 'pending_approval');
    }

    public function waitlistedAttendees(): HasMany
    {
        return $this->attendees()
            ->where('status', 'waitlisted');
    }

    public function checkedInAttendees(): HasMany
    {
        return $this->attendees()
            ->where(function (Builder $query): void {
                $query
                    ->where('status', 'checked_in')
                    ->orWhereNotNull('checked_in_at');
            });
    }

    /*
    |--------------------------------------------------------------------------
    | Check-in and attendance
    |--------------------------------------------------------------------------
    */

    public function checkInPoints(): HasMany
    {
        return $this->hasMany(CheckInPoint::class);
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(CheckIn::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Communication
    |--------------------------------------------------------------------------
    */

    public function communicationCampaigns(): HasMany
    {
        return $this->hasMany(CommunicationCampaign::class);
    }

    public function communicationLogs(): HasMany
    {
        return $this->hasMany(CommunicationLog::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Query scopes
    |--------------------------------------------------------------------------
    */

    public function scopeForOrganization(
        Builder $query,
        Organization|int $organization
    ): Builder {
        $organizationId = $organization instanceof Organization
            ? $organization->getKey()
            : $organization;

        return $query->where(
            'organization_id',
            $organizationId
        );
    }

    public function scopeAssignedTo(
        Builder $query,
        User|int $user,
        ?string $role = null
    ): Builder {
        $userId = $user instanceof User
            ? $user->getKey()
            : $user;

        return $query->whereHas(
            'users',
            function (Builder $staffQuery) use (
                $userId,
                $role
            ): void {
                $staffQuery
                    ->where('users.id', $userId)
                    ->where(
                        'event_user.status',
                        self::STAFF_STATUS_ACTIVE
                    )
                    ->when(
                        filled($role),
                        fn (Builder $query): Builder =>
                            $query->where(
                                'event_user.role',
                                $role
                            )
                    );
            }
        );
    }

    public function scopeAccessibleBy(
        Builder $query,
        ?User $user
    ): Builder {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isSuperAdmin()) {
            return $query;
        }

        $managedOrganizationIds = $user
            ->activeOrganizations()
            ->wherePivotIn('role', [
                User::ORGANIZATION_ROLE_OWNER,
                User::ORGANIZATION_ROLE_ADMIN,
            ])
            ->pluck('organizations.id');

        return $query->where(
            function (Builder $query) use (
                $user,
                $managedOrganizationIds
            ): void {
                if ($managedOrganizationIds->isNotEmpty()) {
                    $query->whereIn(
                        'organization_id',
                        $managedOrganizationIds
                    );
                } else {
                    $query->whereRaw('1 = 0');
                }

                $query->orWhereHas(
                    'users',
                    function (Builder $staffQuery) use (
                        $user
                    ): void {
                        $staffQuery
                            ->where(
                                'users.id',
                                $user->getKey()
                            )
                            ->where(
                                'event_user.status',
                                self::STAFF_STATUS_ACTIVE
                            );
                    }
                );
            }
        );
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where(
            'status',
            self::STATUS_ACTIVE
        );
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where(
            'status',
            self::STATUS_DRAFT
        );
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where(
            'status',
            self::STATUS_COMPLETED
        );
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where(
            'status',
            self::STATUS_CANCELLED
        );
    }

    public function scopeRegistrationOpen(
        Builder $query
    ): Builder {
        return $query
            ->where('registration_is_open', true)
            ->where('status', self::STATUS_ACTIVE);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where(
            'starts_at',
            '>=',
            now()
        );
    }

    public function scopeOngoing(Builder $query): Builder
    {
        return $query
            ->where('starts_at', '<=', now())
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            });
    }

    public function scopePast(Builder $query): Builder
    {
        return $query->where(
            function (Builder $query): void {
                $query
                    ->where('ends_at', '<', now())
                    ->orWhere(
                        function (Builder $query): void {
                            $query
                                ->whereNull('ends_at')
                                ->where(
                                    'starts_at',
                                    '<',
                                    now()
                                );
                        }
                    );
            }
        );
    }

    public function scopeSearch(
        Builder $query,
        ?string $search
    ): Builder {
        return $query->when(
            filled($search),
            function (Builder $query) use (
                $search
            ): Builder {
                $search = trim($search);

                return $query->where(
                    function (Builder $query) use (
                        $search
                    ): void {
                        $query
                            ->where(
                                'name',
                                'ilike',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'slug',
                                'ilike',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'event_code',
                                'ilike',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'event_type',
                                'ilike',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'venue',
                                'ilike',
                                "%{$search}%"
                            );
                    }
                );
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Event staff helpers
    |--------------------------------------------------------------------------
    */

    public function isUserAssigned(
        User|int $user
    ): bool {
        $userId = $user instanceof User
            ? $user->getKey()
            : $user;

        return $this->users()
            ->whereKey($userId)
            ->wherePivot(
                'status',
                self::STAFF_STATUS_ACTIVE
            )
            ->exists();
    }

    public function assignedUserRole(
        User|int $user
    ): ?string {
        $userId = $user instanceof User
            ? $user->getKey()
            : $user;

        $assignedUser = $this->users()
            ->whereKey($userId)
            ->wherePivot(
                'status',
                self::STAFF_STATUS_ACTIVE
            )
            ->first();

        return $assignedUser?->pivot?->role;
    }

    public function assignedUserHasRole(
        User|int $user,
        string|array $roles
    ): bool {
        $assignedRole = $this->assignedUserRole($user);

        if ($assignedRole === null) {
            return false;
        }

        return in_array(
            $assignedRole,
            (array) $roles,
            true
        );
    }

    public function assignUser(
        User|int $user,
        string $role
    ): void {
        $userId = $user instanceof User
            ? $user->getKey()
            : $user;

        $this->users()->syncWithoutDetaching([
            $userId => [
                'role' => $role,
                'status' => self::STAFF_STATUS_ACTIVE,
                'assigned_at' => now(),
            ],
        ]);
    }

    public function updateUserAssignment(
        User|int $user,
        array $attributes
    ): int {
        $userId = $user instanceof User
            ? $user->getKey()
            : $user;

        return $this->users()
            ->updateExistingPivot(
                $userId,
                $attributes
            );
    }

    public function activateAssignedUser(
        User|int $user
    ): int {
        return $this->updateUserAssignment(
            $user,
            [
                'status' => self::STAFF_STATUS_ACTIVE,
            ]
        );
    }

    public function suspendAssignedUser(
        User|int $user
    ): int {
        return $this->updateUserAssignment(
            $user,
            [
                'status' => self::STAFF_STATUS_SUSPENDED,
            ]
        );
    }

    public function removeAssignedUser(
        User|int $user
    ): int {
        $userId = $user instanceof User
            ? $user->getKey()
            : $user;

        return $this->users()->detach($userId);
    }

    /*
    |--------------------------------------------------------------------------
    | Organization access helpers
    |--------------------------------------------------------------------------
    */

    public function belongsToOrganization(
        Organization|int $organization
    ): bool {
        $organizationId = $organization instanceof Organization
            ? $organization->getKey()
            : $organization;

        return (int) $this->organization_id
            === (int) $organizationId;
    }

    public function isAccessibleBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if (! $this->organization_id) {
            return false;
        }

        if (! $user->hasActiveOrganizationAccess(
            $this->organization_id
        )) {
            return false;
        }

        if ($user->canManageOrganization(
            $this->organization_id
        )) {
            return true;
        }

        return $this->isUserAssigned($user);
    }

    public function canBeManagedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if (! $this->organization_id) {
            return false;
        }

        if ($user->canManageOrganization(
            $this->organization_id
        )) {
            return true;
        }

        return $this->assignedUserHasRole(
            $user,
            User::ORGANIZATION_ROLE_EVENT_MANAGER
        );
    }

    public function canBeCheckedInBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if (! $this->organization_id) {
            return false;
        }

        if (! $user->hasActiveOrganizationAccess(
            $this->organization_id
        )) {
            return false;
        }

        if ($user->canManageOrganization(
            $this->organization_id
        )) {
            return true;
        }

        return $this->assignedUserHasRole(
            $user,
            [
                User::ORGANIZATION_ROLE_EVENT_MANAGER,
                User::ORGANIZATION_ROLE_CHECK_IN_OFFICER,
            ]
        );
    }

    public function canManageRegistrationBy(
        ?User $user
    ): bool {
        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if (! $this->organization_id) {
            return false;
        }

        if (! $user->hasActiveOrganizationAccess(
            $this->organization_id
        )) {
            return false;
        }

        if ($user->canManageOrganization(
            $this->organization_id
        )) {
            return true;
        }

        return $this->assignedUserHasRole(
            $user,
            [
                User::ORGANIZATION_ROLE_EVENT_MANAGER,
                User::ORGANIZATION_ROLE_REGISTRATION_OFFICER,
            ]
        );
    }

    public function canManageBadgesBy(
        ?User $user
    ): bool {
        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if (! $this->organization_id) {
            return false;
        }

        if (! $user->hasActiveOrganizationAccess(
            $this->organization_id
        )) {
            return false;
        }

        if ($user->canManageOrganization(
            $this->organization_id
        )) {
            return true;
        }

        return $this->assignedUserHasRole(
            $user,
            [
                User::ORGANIZATION_ROLE_EVENT_MANAGER,
                User::ORGANIZATION_ROLE_BADGE_OFFICER,
            ]
        );
    }

    public function canManageCommunicationBy(
        ?User $user
    ): bool {
        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if (! $this->organization_id) {
            return false;
        }

        if (! $user->hasActiveOrganizationAccess(
            $this->organization_id
        )) {
            return false;
        }

        if ($user->canManageOrganization(
            $this->organization_id
        )) {
            return true;
        }

        return $this->assignedUserHasRole(
            $user,
            [
                User::ORGANIZATION_ROLE_EVENT_MANAGER,
                User::ORGANIZATION_ROLE_COMMUNICATION_OFFICER,
            ]
        );
    }

    public function canViewReportsBy(
        ?User $user
    ): bool {
        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        if (! $this->organization_id) {
            return false;
        }

        if (! $user->hasActiveOrganizationAccess(
            $this->organization_id
        )) {
            return false;
        }

        if ($user->canManageOrganization(
            $this->organization_id
        )) {
            return true;
        }

        return $this->assignedUserHasRole(
            $user,
            [
                User::ORGANIZATION_ROLE_EVENT_MANAGER,
                User::ORGANIZATION_ROLE_REPORT_VIEWER,
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
        return $this->status === self::STATUS_DRAFT;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function hasStarted(): bool
    {
        return filled($this->starts_at)
            && $this->starts_at->isPast();
    }

    public function hasEnded(): bool
    {
        return $this->ends_at
            ? $this->ends_at->isPast()
            : false;
    }

    public function isUpcoming(): bool
    {
        return filled($this->starts_at)
            && $this->starts_at->isFuture();
    }

    public function isOngoing(): bool
    {
        if (! $this->starts_at) {
            return false;
        }

        if ($this->starts_at->isFuture()) {
            return false;
        }

        return ! $this->ends_at
            || $this->ends_at->isFuture();
    }

    /*
    |--------------------------------------------------------------------------
    | Registration helpers
    |--------------------------------------------------------------------------
    */

    public function acceptedAttendeesCount(): int
    {
        return $this->attendees()
            ->whereIn('status', [
                'pending_approval',
                'registered',
                'confirmed',
                'checked_in',
            ])
            ->count();
    }

    public function confirmedAttendeesCount(): int
    {
        return $this->attendees()
            ->whereIn('status', [
                'registered',
                'confirmed',
                'checked_in',
            ])
            ->count();
    }

    public function pendingApprovalAttendeesCount(): int
    {
        return $this
            ->pendingApprovalAttendees()
            ->count();
    }

    public function waitlistedAttendeesCount(): int
    {
        return $this
            ->waitlistedAttendees()
            ->count();
    }

    public function checkedInAttendeesCount(): int
    {
        return $this
            ->checkedInAttendees()
            ->count();
    }

    public function remainingCapacity(): ?int
    {
        if (
            blank($this->capacity)
            || (int) $this->capacity <= 0
        ) {
            return null;
        }

        return max(
            0,
            (int) $this->capacity
                - $this->acceptedAttendeesCount()
        );
    }

    public function isRegistrationFull(): bool
    {
        if (
            blank($this->capacity)
            || (int) $this->capacity <= 0
        ) {
            return false;
        }

        return $this->acceptedAttendeesCount()
            >= (int) $this->capacity;
    }

    public function registrationCanAcceptAttendees(): bool
    {
        if (! $this->registration_is_open) {
            return false;
        }

        if (! $this->isActive()) {
            return false;
        }

        if (! $this->isRegistrationFull()) {
            return true;
        }

        return (bool) $this
            ->registration_waitlist_enabled;
    }

    public function nextRegistrationStatus(): string
    {
        if ($this->isRegistrationFull()) {
            return $this->registration_waitlist_enabled
                ? 'waitlisted'
                : 'closed';
        }

        if ($this->registration_requires_approval) {
            return 'pending_approval';
        }

        return 'registered';
    }

    public function eventTypeLabel(): string
    {
        return EventPresetService::eventTypeLabel(
            $this->event_type,
            $this->custom_event_type
        );
    }

    public function registrationSectionLabels(): array
    {
        return EventPresetService::registrationLabels(
            $this->event_type
        );
    }

    public function isMultiDay(): bool
    {
        return $this->schedule_mode === 'multi_day';
    }

    public function isSingleDay(): bool
    {
        return ! $this->isMultiDay();
    }

    public function allowsDaySelection(): bool
    {
        return $this->isMultiDay()
            && (bool) $this->registration_allow_day_selection;
    }

    public function allowsAllDaysSelection(): bool
    {
        return $this->allowsDaySelection()
            && (bool) $this->registration_allow_all_days;
    }

    public function sessionsAreEnabled(): bool
    {
        return (bool) $this->sessions_enabled;
    }

    public function allowsSessionRegistration(): bool
    {
        return $this->sessionsAreEnabled()
            && (bool) $this->session_registration_enabled;
    }

    public function allowsSessionCheckIn(): bool
    {
        return $this->sessionsAreEnabled()
            && (bool) $this->session_check_in_enabled;
    }

    public function hasMultipleDays(): bool
    {
        return $this->days()->count() > 1;
    }

    public function hasEventDays(): bool
    {
        return $this->days()->exists();
    }

    public function hasSessions(): bool
    {
        return $this->sessions()->exists();
    }

    public function activeSessionsCount(): int
    {
        return $this->activeSessions()->count();
    }

    public function hasMerchandise(): bool
    {
        return $this->activeMerchandise()->exists();
    }

    public function shouldShowMerchandiseImages(): bool
    {
        return (bool) $this->show_merchandise_images;
    }
}