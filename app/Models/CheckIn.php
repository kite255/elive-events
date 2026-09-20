<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckIn extends Model
{
    public const METHOD_QR = 'qr';

    public const METHOD_MANUAL = 'manual';

    public const METHOD_BADGE_NUMBER = 'badge_number';

    public const METHOD_PHONE = 'phone';

    public const METHOD_NAME = 'name';

    public const METHOD_ONSITE = 'onsite';

    protected $fillable = [
        'event_id',
        'event_day_id',
        'event_session_id',
        'attendee_id',
        'check_in_point_id',
        'checked_in_by',
        'method',
        'checked_in_at',
        'device_name',
        'ip_address',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'event_id' => 'integer',
            'event_day_id' => 'integer',
            'event_session_id' => 'integer',
            'attendee_id' => 'integer',
            'check_in_point_id' => 'integer',
            'checked_in_by' => 'integer',
            'checked_in_at' => 'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function eventDay(): BelongsTo
    {
        return $this->belongsTo(EventDay::class);
    }

    public function eventSession(): BelongsTo
    {
        return $this->belongsTo(EventSession::class);
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(Attendee::class);
    }

    public function checkInPoint(): BelongsTo
    {
        return $this->belongsTo(CheckInPoint::class);
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'checked_in_by'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Organization access scopes
    |--------------------------------------------------------------------------
    */

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

        return $query->whereHas(
            'event',
            fn (Builder $eventQuery): Builder =>
                $eventQuery->accessibleBy($user)
        );
    }

    public function scopeForOrganization(
        Builder $query,
        Organization|int $organization
    ): Builder {
        $organizationId = $organization instanceof Organization
            ? $organization->getKey()
            : $organization;

        return $query->whereHas(
            'event',
            fn (Builder $eventQuery): Builder =>
                $eventQuery->where(
                    'organization_id',
                    $organizationId
                )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | General query scopes
    |--------------------------------------------------------------------------
    */

    public function scopeForEvent(
        Builder $query,
        Event|int $event
    ): Builder {
        $eventId = $event instanceof Event
            ? $event->getKey()
            : $event;

        return $query->where('event_id', $eventId);
    }

    public function scopeForEventDay(
        Builder $query,
        EventDay|int $eventDay
    ): Builder {
        $eventDayId = $eventDay instanceof EventDay
            ? $eventDay->getKey()
            : $eventDay;

        return $query->where(
            'event_day_id',
            $eventDayId
        );
    }

    public function scopeForEventSession(
        Builder $query,
        EventSession|int $eventSession
    ): Builder {
        $eventSessionId = $eventSession instanceof EventSession
            ? $eventSession->getKey()
            : $eventSession;

        return $query->where(
            'event_session_id',
            $eventSessionId
        );
    }

    public function scopeForAttendee(
        Builder $query,
        Attendee|int $attendee
    ): Builder {
        $attendeeId = $attendee instanceof Attendee
            ? $attendee->getKey()
            : $attendee;

        return $query->where(
            'attendee_id',
            $attendeeId
        );
    }

    public function scopeForCheckInPoint(
        Builder $query,
        CheckInPoint|int $checkInPoint
    ): Builder {
        $checkInPointId = $checkInPoint instanceof CheckInPoint
            ? $checkInPoint->getKey()
            : $checkInPoint;

        return $query->where(
            'check_in_point_id',
            $checkInPointId
        );
    }

    public function scopeByOfficer(
        Builder $query,
        User|int $user
    ): Builder {
        $userId = $user instanceof User
            ? $user->getKey()
            : $user;

        return $query->where(
            'checked_in_by',
            $userId
        );
    }

    public function scopeManual(Builder $query): Builder
    {
        return $query->where(
            'method',
            self::METHOD_MANUAL
        );
    }

    public function scopeQr(Builder $query): Builder
    {
        return $query->where(
            'method',
            self::METHOD_QR
        );
    }

    public function scopeBadgeNumber(Builder $query): Builder
    {
        return $query->where(
            'method',
            self::METHOD_BADGE_NUMBER
        );
    }

    public function scopePhone(Builder $query): Builder
    {
        return $query->where(
            'method',
            self::METHOD_PHONE
        );
    }

    public function scopeNameSearch(Builder $query): Builder
    {
        return $query->where(
            'method',
            self::METHOD_NAME
        );
    }

    public function scopeOnsite(Builder $query): Builder
    {
        return $query->where(
            'method',
            self::METHOD_ONSITE
        );
    }

    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate(
            'checked_in_at',
            today()
        );
    }

    public function scopeBetween(
        Builder $query,
        mixed $from,
        mixed $to
    ): Builder {
        return $query->whereBetween(
            'checked_in_at',
            [$from, $to]
        );
    }

    public function scopeLatestCheckIns(
        Builder $query
    ): Builder {
        return $query->latest('checked_in_at');
    }

    public function scopeSearch(
        Builder $query,
        ?string $search
    ): Builder {
        return $query->when(
            filled($search),
            function (Builder $query) use ($search): Builder {
                $search = trim($search);

                return $query->whereHas(
                    'attendee',
                    function (Builder $attendeeQuery) use (
                        $search
                    ): void {
                        $attendeeQuery
                            ->where(
                                'full_name',
                                'ilike',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'email',
                                'ilike',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'phone',
                                'ilike',
                                "%{$search}%"
                            )
                            ->orWhere(
                                'badge_number',
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
    | Access helpers
    |--------------------------------------------------------------------------
    */

    public function isAccessibleBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $this->event?->isAccessibleBy($user)
            ?? false;
    }

    public function canBeViewedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $this->event?->canBeCheckedInBy($user)
            || $this->event?->canViewReportsBy($user)
            || false;
    }

    public function canBeManagedBy(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $this->event?->canBeCheckedInBy($user)
            ?? false;
    }

    /*
    |--------------------------------------------------------------------------
    | Event day helpers
    |--------------------------------------------------------------------------
    */

    public function belongsToEventDay(): bool
    {
        return filled($this->event_day_id);
    }

    public function getEventDayNameAttribute(): string
    {
        return $this->eventDay?->name
            ?? 'General Event Check-in';
    }

    /*
    |--------------------------------------------------------------------------
    | Method helpers
    |--------------------------------------------------------------------------
    */

    public function isQr(): bool
    {
        return $this->method === self::METHOD_QR;
    }

    public function isManual(): bool
    {
        return $this->method === self::METHOD_MANUAL;
    }

    public function isBadgeNumber(): bool
    {
        return $this->method === self::METHOD_BADGE_NUMBER;
    }

    public function isPhone(): bool
    {
        return $this->method === self::METHOD_PHONE;
    }

    public function isNameSearch(): bool
    {
        return $this->method === self::METHOD_NAME;
    }

    public function isOnsite(): bool
    {
        return $this->method === self::METHOD_ONSITE;
    }

    public function getMethodLabelAttribute(): string
    {
        return match ($this->method) {
            self::METHOD_QR => 'QR Code',
            self::METHOD_MANUAL => 'Manual',
            self::METHOD_BADGE_NUMBER => 'Badge Number',
            self::METHOD_PHONE => 'Phone',
            self::METHOD_NAME => 'Name Search',
            self::METHOD_ONSITE => 'Onsite Registration',
            default => ucfirst(
                str_replace('_', ' ', $this->method)
            ),
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Display helpers
    |--------------------------------------------------------------------------
    */

    public function getOfficerNameAttribute(): string
    {
        return $this->checkedInBy?->name
            ?? 'System';
    }

    public function getCheckInPointNameAttribute(): string
    {
        return $this->checkInPoint?->name
            ?? 'General Entrance';
    }

    public function getFormattedCheckedInAtAttribute(): ?string
    {
        return $this->checked_in_at?->format(
            'd/m/Y H:i:s'
        );
    }


    public function belongsToEventSession(
        EventSession|int $eventSession
    ): bool {
        $eventSessionId = $eventSession instanceof EventSession
            ? $eventSession->getKey()
            : $eventSession;

        return (int) $this->event_session_id
            === (int) $eventSessionId;
    }

    public function getEventSessionNameAttribute(): ?string
    {
        return $this->eventSession?->name;
    }
}
