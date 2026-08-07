<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;
    use Notifiable;

    /*
    |--------------------------------------------------------------------------
    | Organization roles
    |--------------------------------------------------------------------------
    */

    public const ORGANIZATION_ROLE_OWNER = 'owner';

    public const ORGANIZATION_ROLE_ADMIN = 'organization_admin';

    public const ORGANIZATION_ROLE_EVENT_MANAGER = 'event_manager';

    public const ORGANIZATION_ROLE_REGISTRATION_OFFICER =
        'registration_officer';

    public const ORGANIZATION_ROLE_CHECK_IN_OFFICER =
        'check_in_officer';

    public const ORGANIZATION_ROLE_BADGE_OFFICER =
        'badge_officer';

    public const ORGANIZATION_ROLE_COMMUNICATION_OFFICER =
        'communication_officer';

    public const ORGANIZATION_ROLE_REPORT_VIEWER =
        'report_viewer';

    public const ORGANIZATION_ROLE_MEMBER = 'member';

    /*
    |--------------------------------------------------------------------------
    | Organization membership statuses
    |--------------------------------------------------------------------------
    */

    public const ORGANIZATION_STATUS_ACTIVE = 'active';

    public const ORGANIZATION_STATUS_INACTIVE = 'inactive';

    public const ORGANIZATION_STATUS_SUSPENDED = 'suspended';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Organization relationships
    |--------------------------------------------------------------------------
    */

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)
            ->withPivot([
                'role',
                'status',
                'is_owner',
                'joined_at',
                'last_accessed_at',
            ])
            ->withTimestamps();
    }

    public function activeOrganizations(): BelongsToMany
    {
        return $this->organizations()
            ->wherePivot(
                'status',
                self::ORGANIZATION_STATUS_ACTIVE
            );
    }

    public function ownedOrganizations(): BelongsToMany
    {
        return $this->organizations()
            ->wherePivot('is_owner', true)
            ->wherePivot(
                'status',
                self::ORGANIZATION_STATUS_ACTIVE
            );
    }

    public function managedOrganizations(): BelongsToMany
    {
        return $this->organizations()
            ->wherePivot(
                'status',
                self::ORGANIZATION_STATUS_ACTIVE
            )
            ->wherePivotIn('role', [
                self::ORGANIZATION_ROLE_OWNER,
                self::ORGANIZATION_ROLE_ADMIN,
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Event assignment relationships
    |--------------------------------------------------------------------------
    */

    public function assignedEvents(): BelongsToMany
    {
        return $this->belongsToMany(Event::class)
            ->withPivot([
                'role',
                'status',
                'assigned_at',
                'last_accessed_at',
            ])
            ->withTimestamps();
    }

    public function activeAssignedEvents(): BelongsToMany
    {
        return $this->assignedEvents()
            ->wherePivot(
                'status',
                Event::STAFF_STATUS_ACTIVE
            );
    }

    public function eventManagerEvents(): BelongsToMany
    {
        return $this->activeAssignedEvents()
            ->wherePivot(
                'role',
                self::ORGANIZATION_ROLE_EVENT_MANAGER
            );
    }

    public function registrationEvents(): BelongsToMany
    {
        return $this->activeAssignedEvents()
            ->wherePivot(
                'role',
                self::ORGANIZATION_ROLE_REGISTRATION_OFFICER
            );
    }

    public function checkInEvents(): BelongsToMany
    {
        return $this->activeAssignedEvents()
            ->wherePivot(
                'role',
                self::ORGANIZATION_ROLE_CHECK_IN_OFFICER
            );
    }

    public function badgeEvents(): BelongsToMany
    {
        return $this->activeAssignedEvents()
            ->wherePivot(
                'role',
                self::ORGANIZATION_ROLE_BADGE_OFFICER
            );
    }

    public function communicationEvents(): BelongsToMany
    {
        return $this->activeAssignedEvents()
            ->wherePivot(
                'role',
                self::ORGANIZATION_ROLE_COMMUNICATION_OFFICER
            );
    }

    public function reportEvents(): BelongsToMany
    {
        return $this->activeAssignedEvents()
            ->wherePivot(
                'role',
                self::ORGANIZATION_ROLE_REPORT_VIEWER
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Organization access helpers
    |--------------------------------------------------------------------------
    */

    public function belongsToOrganization(
        Organization|int $organization
    ): bool {
        $organizationId = $this->resolveOrganizationId(
            $organization
        );

        return $this->organizations()
            ->whereKey($organizationId)
            ->exists();
    }

    public function hasActiveOrganizationAccess(
        Organization|int $organization
    ): bool {
        $organizationId = $this->resolveOrganizationId(
            $organization
        );

        return $this->organizations()
            ->whereKey($organizationId)
            ->wherePivot(
                'status',
                self::ORGANIZATION_STATUS_ACTIVE
            )
            ->exists();
    }

    public function organizationRole(
        Organization|int $organization
    ): ?string {
        $organizationId = $this->resolveOrganizationId(
            $organization
        );

        $organizationRecord = $this->organizations()
            ->whereKey($organizationId)
            ->first();

        return $organizationRecord?->pivot?->role;
    }

    public function hasOrganizationRole(
        Organization|int $organization,
        string|array $roles
    ): bool {
        if (! $this->hasActiveOrganizationAccess($organization)) {
            return false;
        }

        $role = $this->organizationRole($organization);

        if ($role === null) {
            return false;
        }

        return in_array(
            $role,
            (array) $roles,
            true
        );
    }

    public function isOrganizationOwner(
        Organization|int $organization
    ): bool {
        $organizationId = $this->resolveOrganizationId(
            $organization
        );

        return $this->organizations()
            ->whereKey($organizationId)
            ->wherePivot('is_owner', true)
            ->wherePivot(
                'status',
                self::ORGANIZATION_STATUS_ACTIVE
            )
            ->exists();
    }

    public function canManageOrganization(
        Organization|int $organization
    ): bool {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if (! $this->hasActiveOrganizationAccess($organization)) {
            return false;
        }

        return $this->isOrganizationOwner($organization)
            || $this->hasOrganizationRole(
                $organization,
                [
                    self::ORGANIZATION_ROLE_OWNER,
                    self::ORGANIZATION_ROLE_ADMIN,
                ]
            );
    }

    public function canManageEvents(
        Organization|int $organization
    ): bool {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->hasOrganizationRole(
            $organization,
            [
                self::ORGANIZATION_ROLE_OWNER,
                self::ORGANIZATION_ROLE_ADMIN,
                self::ORGANIZATION_ROLE_EVENT_MANAGER,
            ]
        );
    }

    public function canManageRegistration(
        Organization|int $organization
    ): bool {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->hasOrganizationRole(
            $organization,
            [
                self::ORGANIZATION_ROLE_OWNER,
                self::ORGANIZATION_ROLE_ADMIN,
                self::ORGANIZATION_ROLE_EVENT_MANAGER,
                self::ORGANIZATION_ROLE_REGISTRATION_OFFICER,
            ]
        );
    }

    public function canPerformCheckIn(
        Organization|int $organization
    ): bool {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->hasOrganizationRole(
            $organization,
            [
                self::ORGANIZATION_ROLE_OWNER,
                self::ORGANIZATION_ROLE_ADMIN,
                self::ORGANIZATION_ROLE_EVENT_MANAGER,
                self::ORGANIZATION_ROLE_CHECK_IN_OFFICER,
            ]
        );
    }

    public function canManageBadges(
        Organization|int $organization
    ): bool {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->hasOrganizationRole(
            $organization,
            [
                self::ORGANIZATION_ROLE_OWNER,
                self::ORGANIZATION_ROLE_ADMIN,
                self::ORGANIZATION_ROLE_EVENT_MANAGER,
                self::ORGANIZATION_ROLE_BADGE_OFFICER,
            ]
        );
    }

    public function canManageCommunication(
        Organization|int $organization
    ): bool {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->hasOrganizationRole(
            $organization,
            [
                self::ORGANIZATION_ROLE_OWNER,
                self::ORGANIZATION_ROLE_ADMIN,
                self::ORGANIZATION_ROLE_EVENT_MANAGER,
                self::ORGANIZATION_ROLE_COMMUNICATION_OFFICER,
            ]
        );
    }

    public function canViewReports(
        Organization|int $organization
    ): bool {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->hasOrganizationRole(
            $organization,
            [
                self::ORGANIZATION_ROLE_OWNER,
                self::ORGANIZATION_ROLE_ADMIN,
                self::ORGANIZATION_ROLE_EVENT_MANAGER,
                self::ORGANIZATION_ROLE_REPORT_VIEWER,
            ]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Event assignment helpers
    |--------------------------------------------------------------------------
    */

    public function isAssignedToEvent(
        Event|int $event
    ): bool {
        $eventId = $this->resolveEventId($event);

        return $this->assignedEvents()
            ->whereKey($eventId)
            ->wherePivot(
                'status',
                Event::STAFF_STATUS_ACTIVE
            )
            ->exists();
    }

    public function eventAssignmentRole(
        Event|int $event
    ): ?string {
        $eventId = $this->resolveEventId($event);

        $assignedEvent = $this->assignedEvents()
            ->whereKey($eventId)
            ->wherePivot(
                'status',
                Event::STAFF_STATUS_ACTIVE
            )
            ->first();

        return $assignedEvent?->pivot?->role;
    }

    public function hasEventAssignmentRole(
        Event|int $event,
        string|array $roles
    ): bool {
        $role = $this->eventAssignmentRole($event);

        if ($role === null) {
            return false;
        }

        return in_array(
            $role,
            (array) $roles,
            true
        );
    }

    public function canAccessEvent(Event $event): bool
    {
        return $event->isAccessibleBy($this);
    }

    public function canManageEvent(Event $event): bool
    {
        return $event->canBeManagedBy($this);
    }

    public function canCheckInEvent(Event $event): bool
    {
        return $event->canBeCheckedInBy($this);
    }

    public function canManageEventRegistration(
        Event $event
    ): bool {
        return $event->canManageRegistrationBy($this);
    }

    public function canManageEventBadges(
        Event $event
    ): bool {
        return $event->canManageBadgesBy($this);
    }

    public function canManageEventCommunication(
        Event $event
    ): bool {
        return $event->canManageCommunicationBy($this);
    }

    public function canViewEventReports(
        Event $event
    ): bool {
        return $event->canViewReportsBy($this);
    }

    /*
    |--------------------------------------------------------------------------
    | Event assignment management helpers
    |--------------------------------------------------------------------------
    */

    public function assignToEvent(
        Event|int $event,
        string $role
    ): void {
        $eventId = $this->resolveEventId($event);

        $this->assignedEvents()->syncWithoutDetaching([
            $eventId => [
                'role' => $role,
                'status' => Event::STAFF_STATUS_ACTIVE,
                'assigned_at' => now(),
            ],
        ]);
    }

    public function updateEventAssignment(
        Event|int $event,
        array $attributes
    ): int {
        $eventId = $this->resolveEventId($event);

        return $this->assignedEvents()
            ->updateExistingPivot(
                $eventId,
                $attributes
            );
    }

    public function activateEventAssignment(
        Event|int $event
    ): int {
        return $this->updateEventAssignment(
            $event,
            [
                'status' => Event::STAFF_STATUS_ACTIVE,
            ]
        );
    }

    public function suspendEventAssignment(
        Event|int $event
    ): int {
        return $this->updateEventAssignment(
            $event,
            [
                'status' => Event::STAFF_STATUS_SUSPENDED,
            ]
        );
    }

    public function removeEventAssignment(
        Event|int $event
    ): int {
        $eventId = $this->resolveEventId($event);

        return $this->assignedEvents()
            ->detach($eventId);
    }

    /*
    |--------------------------------------------------------------------------
    | Super admin helper
    |--------------------------------------------------------------------------
    */

    public function isSuperAdmin(): bool
    {
        if (method_exists($this, 'hasRole')) {
            return $this->hasRole('super_admin');
        }

        return isset($this->role)
            && $this->role === 'super_admin';
    }

    /*
    |--------------------------------------------------------------------------
    | Filament access
    |--------------------------------------------------------------------------
    */

    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->activeOrganizations()->exists();
    }

    /*
    |--------------------------------------------------------------------------
    | Internal helpers
    |--------------------------------------------------------------------------
    */

    private function resolveOrganizationId(
        Organization|int $organization
    ): int {
        return (int) (
            $organization instanceof Organization
                ? $organization->getKey()
                : $organization
        );
    }

    private function resolveEventId(
        Event|int $event
    ): int {
        return (int) (
            $event instanceof Event
                ? $event->getKey()
                : $event
        );
    }
}