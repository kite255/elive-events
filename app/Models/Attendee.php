<?php

namespace App\Models;

use App\Services\BadgeNumberService;
use App\Services\PhoneNumberService;
use App\Services\QrTokenService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Attendee extends Model
{
    protected $fillable = [
        'event_id',
        'event_sequence',
        'category_id',
        'badge_type_id',

        'full_name',
        'phone',
        'email',
        'organization_name',
        'position',

        'status',
        'registration_source',
        'registered_at',

        'badge_number',
        'public_token',
        'badge_path',

        'checked_in_at',
    ];

    protected function casts(): array
    {
        return [
            'event_sequence' =>
                'integer',

            'registered_at' =>
                'datetime',

            'checked_in_at' =>
                'datetime',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Boot
    |--------------------------------------------------------------------------
    */

    protected static function booted(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Normalize Phone Number
        |--------------------------------------------------------------------------
        */

        static::saving(
            function (
                Attendee $attendee
            ): void {
                if (
                    filled(
                        $attendee->phone
                    )
                ) {
                    $attendee->phone =
                        app(
                            PhoneNumberService::class
                        )->normalize(
                            $attendee->phone
                        );
                }
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Defaults
        |--------------------------------------------------------------------------
        */

        static::creating(
            function (
                Attendee $attendee
            ): void {
                if (
                    blank(
                        $attendee->registered_at
                    )
                ) {
                    $attendee->registered_at =
                        now();
                }

                if (
                    blank(
                        $attendee->status
                    )
                ) {
                    $attendee->status =
                        'registered';
                }

                if (
                    blank(
                        $attendee
                            ->registration_source
                    )
                ) {
                    $attendee
                        ->registration_source =
                        'manual';
                }

                if (
                    blank(
                        $attendee->public_token
                    )
                ) {
                    $attendee->public_token =
                        static::generatePublicToken();
                }
            }
        );

        /*
        |--------------------------------------------------------------------------
        | Post Creation
        |--------------------------------------------------------------------------
        */

        static::created(
            function (
                Attendee $attendee
            ): void {
                app(
                    BadgeNumberService::class
                )->assignBadgeNumber(
                    $attendee
                );

                app(
                    QrTokenService::class
                )->generateForAttendee(
                    $attendee
                );
            }
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Public Token
    |--------------------------------------------------------------------------
    */

    public static function generatePublicToken(): string
    {
        do {
            $token =
                Str::random(
                    32
                );
        } while (
            static::query()
                ->where(
                    'public_token',
                    $token
                )
                ->exists()
        );

        return $token;
    }

    /*
    |--------------------------------------------------------------------------
    | Core Relationships
    |--------------------------------------------------------------------------
    */

    public function event(): BelongsTo
    {
        return $this->belongsTo(
            Event::class
        );
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(
            AttendeeCategory::class,
            'category_id'
        );
    }

    public function badgeType(): BelongsTo
    {
        return $this->belongsTo(
            BadgeType::class
        );
    }

    public function qrToken(): HasOne
    {
        return $this->hasOne(
            AttendeeQrToken::class
        );
    }

    public function rsvp(): HasOne
    {
        return $this->hasOne(
            Rsvp::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Event Days
    |--------------------------------------------------------------------------
    */

    public function eventDays(): BelongsToMany
    {
        return $this
            ->belongsToMany(
                EventDay::class,
                'attendee_event_day'
            )
            ->withPivot([
                'selection_source',
                'selected_at',
            ])
            ->withTimestamps()
            ->orderBy(
                'event_days.event_date'
            )
            ->orderBy(
                'event_days.display_order'
            )
            ->orderBy(
                'event_days.id'
            );
    }

    public function activeEventDays(): BelongsToMany
    {
        return $this
            ->eventDays()
            ->where(
                'event_days.status',
                'active'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Sessions / Activities
    |--------------------------------------------------------------------------
    */

    public function eventSessions(): BelongsToMany
    {
        return $this
            ->belongsToMany(
                EventSession::class,
                'attendee_event_session'
            )
            ->withPivot([
                'status',
                'selection_source',
                'selected_at',
            ])
            ->withTimestamps()
            ->orderBy(
                'event_sessions.event_day_id'
            )
            ->orderBy(
                'event_sessions.display_order'
            )
            ->orderBy(
                'event_sessions.starts_at'
            )
            ->orderBy(
                'event_sessions.id'
            );
    }

    public function activeEventSessions(): BelongsToMany
    {
        return $this
            ->eventSessions()
            ->where(
                'event_sessions.status',
                EventSession::STATUS_ACTIVE
            );
    }

    public function registeredEventSessions(): BelongsToMany
    {
        return $this
            ->eventSessions()
            ->wherePivot(
                'status',
                'registered'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Merchandise
    |--------------------------------------------------------------------------
    */

    public function merchandiseSelections(): HasMany
    {
        return $this->hasMany(
            AttendeeMerchandise::class,
            'attendee_id'
        );
    }

    public function activeMerchandiseSelections(): HasMany
    {
        return $this
            ->merchandiseSelections()
            ->whereIn(
                'status',
                [
                    'selected',
                    'reserved',
                    'distributed',
                ]
            );
    }

    public function reservedMerchandiseSelections(): HasMany
    {
        return $this
            ->merchandiseSelections()
            ->where(
                'status',
                'reserved'
            );
    }

    public function distributedMerchandiseSelections(): HasMany
    {
        return $this
            ->merchandiseSelections()
            ->where(
                'status',
                'distributed'
            );
    }

    public function cancelledMerchandiseSelections(): HasMany
    {
        return $this
            ->merchandiseSelections()
            ->where(
                'status',
                'cancelled'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Attendance and Communication
    |--------------------------------------------------------------------------
    */

    public function checkIns(): HasMany
    {
        return $this->hasMany(
            CheckIn::class
        );
    }

    public function communicationLogs(): HasMany
    {
        return $this->hasMany(
            CommunicationLog::class
        );
    }

    public function communicationCampaignRecipients(): HasMany
    {
        return $this->hasMany(
            CommunicationCampaignRecipient::class,
            'attendee_id'
        );
    }

    public function registrationAnswers(): HasMany
    {
        return $this->hasMany(
            AttendeeRegistrationAnswer::class
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
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

    public function scopeCheckedIn(
        Builder $query
    ): Builder {
        return $query
            ->whereNotNull(
                'checked_in_at'
            );
    }

    public function scopeNotCheckedIn(
        Builder $query
    ): Builder {
        return $query
            ->whereNull(
                'checked_in_at'
            );
    }

    public function scopePublicRegistrations(
        Builder $query
    ): Builder {
        return $query->whereIn(
            'registration_source',
            [
                'public',
                'public_form',
                'public_registration',
            ]
        );
    }

    public function scopePendingApproval(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            'pending_approval'
        );
    }

    public function scopeWaitlisted(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            'waitlisted'
        );
    }

    public function scopeApproved(
        Builder $query
    ): Builder {
        return $query->whereIn(
            'status',
            [
                'registered',
                'confirmed',
                'approved',
                'checked_in',
            ]
        );
    }

    public function scopeExpectedForDay(
        Builder $query,
        int $eventDayId
    ): Builder {
        return $query
            ->whereHas(
                'eventDays',
                fn (
                    Builder $dayQuery
                ) =>
                    $dayQuery->where(
                        'event_days.id',
                        $eventDayId
                    )
            )
            ->whereIn(
                'status',
                [
                    'registered',
                    'confirmed',
                    'approved',
                    'checked_in',
                ]
            );
    }

    public function scopeExpectedForSession(
        Builder $query,
        int $eventSessionId
    ): Builder {
        return $query
            ->whereHas(
                'eventSessions',
                fn (
                    Builder $sessionQuery
                ) =>
                    $sessionQuery
                        ->where(
                            'event_sessions.id',
                            $eventSessionId
                        )
                        ->where(
                            'attendee_event_session.status',
                            'registered'
                        )
            )
            ->whereIn(
                'status',
                [
                    'registered',
                    'confirmed',
                    'approved',
                    'checked_in',
                ]
            );
    }

    public function scopeWithReservedMerchandise(
        Builder $query
    ): Builder {
        return $query->whereHas(
            'merchandiseSelections',
            fn (
                Builder $selectionQuery
            ) =>
                $selectionQuery->where(
                    'status',
                    'reserved'
                )
        );
    }

    public function scopeWithoutMerchandiseSelections(
        Builder $query
    ): Builder {
        return $query->whereDoesntHave(
            'merchandiseSelections',
            fn (
                Builder $selectionQuery
            ) =>
                $selectionQuery
                    ->whereIn(
                        'status',
                        [
                            'selected',
                            'reserved',
                            'distributed',
                        ]
                    )
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Status Helpers
    |--------------------------------------------------------------------------
    */

    public function isCheckedIn(): bool
    {
        return filled(
            $this->checked_in_at
        );
    }

    public function isPendingApproval(): bool
    {
        return $this->status
            === 'pending_approval';
    }

    public function isWaitlisted(): bool
    {
        return $this->status
            === 'waitlisted';
    }

    public function isRejected(): bool
    {
        return in_array(
            $this->status,
            [
                'rejected',
                'cancelled',
            ],
            true
        );
    }

    public function isApproved(): bool
    {
        return in_array(
            $this->status,
            [
                'registered',
                'confirmed',
                'approved',
                'checked_in',
            ],
            true
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Event Day Helpers
    |--------------------------------------------------------------------------
    */

    public function hasSelectedEventDays(): bool
    {
        return $this
            ->eventDays()
            ->exists();
    }

    public function hasSelectedEventDay(
        int|EventDay $eventDay
    ): bool {
        $eventDayId =
            $eventDay
                instanceof EventDay
                ? $eventDay->id
                : $eventDay;

        return $this
            ->eventDays()
            ->where(
                'event_days.id',
                $eventDayId
            )
            ->exists();
    }

    public function selectedEventDaysCount(): int
    {
        return $this
            ->eventDays()
            ->count();
    }

    public function selectedEventDaysLabel(): string
    {
        $days =
            $this
                ->eventDays()
                ->get()
                ->map(
                    fn (
                        EventDay $day
                    ) =>
                        $day->name
                )
                ->filter()
                ->values();

        return $days
                ->isEmpty()
            ? 'No days selected'
            : $days->implode(
                ', '
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Session / Activity Helpers
    |--------------------------------------------------------------------------
    */

    public function hasSelectedEventSessions(): bool
    {
        return $this
            ->registeredEventSessions()
            ->exists();
    }

    public function hasSelectedEventSession(
        int|EventSession $eventSession
    ): bool {
        $eventSessionId =
            $eventSession
                instanceof EventSession
                ? $eventSession->id
                : $eventSession;

        return $this
            ->registeredEventSessions()
            ->where(
                'event_sessions.id',
                $eventSessionId
            )
            ->exists();
    }

    public function selectedEventSessionsCount(): int
    {
        return $this
            ->registeredEventSessions()
            ->count();
    }

    public function selectedEventSessionsLabel(): string
    {
        $sessions =
            $this
                ->registeredEventSessions()
                ->get()
                ->map(
                    fn (
                        EventSession $session
                    ) =>
                        $session->name
                )
                ->filter()
                ->values();

        return $sessions
                ->isEmpty()
            ? 'No sessions selected'
            : $sessions->implode(
                ', '
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Merchandise Helpers
    |--------------------------------------------------------------------------
    */

    public function hasMerchandiseSelections(): bool
    {
        return $this
            ->activeMerchandiseSelections()
            ->exists();
    }

    public function hasReservedMerchandise(): bool
    {
        return $this
            ->reservedMerchandiseSelections()
            ->exists();
    }

    public function hasDistributedMerchandise(): bool
    {
        return $this
            ->distributedMerchandiseSelections()
            ->exists();
    }

    public function hasSelectedMerchandise(
        int|EventMerchandise $merchandise
    ): bool {
        $merchandiseId =
            $merchandise
                instanceof EventMerchandise
                ? $merchandise->id
                : $merchandise;

        return $this
            ->activeMerchandiseSelections()
            ->where(
                'event_merchandise_id',
                $merchandiseId
            )
            ->exists();
    }

    public function merchandiseSelectionFor(
        int|EventMerchandise $merchandise
    ): ?AttendeeMerchandise {
        $merchandiseId =
            $merchandise
                instanceof EventMerchandise
                ? $merchandise->id
                : $merchandise;

        return $this
            ->merchandiseSelections()
            ->where(
                'event_merchandise_id',
                $merchandiseId
            )
            ->latest(
                'id'
            )
            ->first();
    }

    public function activeMerchandiseQuantity(): int
    {
        return (int) $this
            ->activeMerchandiseSelections()
            ->sum(
                'quantity'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Badge Helpers
    |--------------------------------------------------------------------------
    |
    | badge_path stores the SVG master path.
    |
    | Example:
    |
    | events/4/badges/svg/attendee-23-name.svg
    |
    | From this path we derive:
    |
    | PNG:
    | events/4/badges/png/attendee-23-name.png
    |
    | PDF:
    | events/4/badges/pdf/attendee-23-name.pdf
    |
    |--------------------------------------------------------------------------
    */

    public function hasBadge(): bool
    {
        return filled(
            $this->badge_path
        );
    }

    /*
    |--------------------------------------------------------------------------
    | SVG Master Badge
    |--------------------------------------------------------------------------
    */

    public function badgeSvgPath(): ?string
    {
        if (
            blank(
                $this->badge_path
            )
        ) {
            return null;
        }

        return ltrim(
            (string) $this->badge_path,
            '/'
        );
    }

    public function badgeSvgExists(): bool
    {
        $path =
            $this->badgeSvgPath();

        return filled($path)
            && Storage::disk(
                'public'
            )->exists(
                $path
            );
    }

    public function badgeSvgUrl(): ?string
    {
        $path =
            $this->badgeSvgPath();

        if (
            blank($path)
            || ! Storage::disk(
                'public'
            )->exists(
                $path
            )
        ) {
            return null;
        }

        return asset(
            'storage/'
            . $path
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PNG Delivery Badge
    |--------------------------------------------------------------------------
    */

    public function badgePngPath(): ?string
    {
        $masterPath =
            $this->badgeSvgPath();

        if (
            blank(
                $masterPath
            )
        ) {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | Compatibility:
        | If badge_path somehow already points to PNG
        |--------------------------------------------------------------------------
        */

        if (
            strtolower(
                pathinfo(
                    $masterPath,
                    PATHINFO_EXTENSION
                )
            )
            === 'png'
        ) {
            return $masterPath;
        }

        $filename =
            pathinfo(
                $masterPath,
                PATHINFO_FILENAME
            );

        return sprintf(
            'events/%s/badges/png/%s.png',
            $this->event_id,
            $filename
        );
    }

    public function badgePngExists(): bool
    {
        $path =
            $this->badgePngPath();

        return filled($path)
            && Storage::disk(
                'public'
            )->exists(
                $path
            );
    }

    public function badgePngUrl(): ?string
    {
        $path =
            $this->badgePngPath();

        if (
            blank($path)
            || ! Storage::disk(
                'public'
            )->exists(
                $path
            )
        ) {
            return null;
        }

        return asset(
            'storage/'
            . $path
        );
    }

    /*
    |--------------------------------------------------------------------------
    | PDF Print Badge
    |--------------------------------------------------------------------------
    */

    public function badgePdfPath(): ?string
    {
        $masterPath =
            $this->badgeSvgPath();

        if (
            blank(
                $masterPath
            )
        ) {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | Compatibility:
        | If badge_path somehow already points to PDF
        |--------------------------------------------------------------------------
        */

        if (
            strtolower(
                pathinfo(
                    $masterPath,
                    PATHINFO_EXTENSION
                )
            )
            === 'pdf'
        ) {
            return $masterPath;
        }

        $filename =
            pathinfo(
                $masterPath,
                PATHINFO_FILENAME
            );

        return sprintf(
            'events/%s/badges/pdf/%s.pdf',
            $this->event_id,
            $filename
        );
    }

    public function badgePdfExists(): bool
    {
        $path =
            $this->badgePdfPath();

        return filled($path)
            && Storage::disk(
                'public'
            )->exists(
                $path
            );
    }

    public function badgePdfUrl(): ?string
    {
        $path =
            $this->badgePdfPath();

        if (
            blank($path)
            || ! Storage::disk(
                'public'
            )->exists(
                $path
            )
        ) {
            return null;
        }

        return asset(
            'storage/'
            . $path
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Backward-Compatible Badge URL
    |--------------------------------------------------------------------------
    |
    | Existing pages may still call:
    |
    | $attendee->badgeUrl()
    |
    | Instead of exposing the SVG master, return the PNG delivery badge when
    | available.
    |
    | Older badges that have not yet been regenerated can still fall back to
    | their existing badge_path.
    |
    |--------------------------------------------------------------------------
    */

    public function badgeUrl(): ?string
    {
        /*
        |--------------------------------------------------------------------------
        | Preferred: PNG
        |--------------------------------------------------------------------------
        */

        $pngUrl =
            $this->badgePngUrl();

        if (
            filled(
                $pngUrl
            )
        ) {
            return $pngUrl;
        }

        /*
        |--------------------------------------------------------------------------
        | Legacy / Master Fallback
        |--------------------------------------------------------------------------
        */

        if (
            blank(
                $this->badge_path
            )
        ) {
            return null;
        }

        $path =
            ltrim(
                (string) $this->badge_path,
                '/'
            );

        /*
        |--------------------------------------------------------------------------
        | Absolute Legacy URL
        |--------------------------------------------------------------------------
        */

        if (
            str_starts_with(
                $path,
                'http://'
            )
            || str_starts_with(
                $path,
                'https://'
            )
        ) {
            return $path;
        }

        /*
        |--------------------------------------------------------------------------
        | Already storage-prefixed
        |--------------------------------------------------------------------------
        */

        if (
            str_starts_with(
                $path,
                'storage/'
            )
        ) {
            return asset(
                $path
            );
        }

        return asset(
            'storage/'
            . $path
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Preferred Delivery Badge
    |--------------------------------------------------------------------------
    */

    public function deliveryBadgePath(): ?string
    {
        return $this
            ->badgePngPath();
    }

    public function deliveryBadgeUrl(): ?string
    {
        return $this
            ->badgePngUrl();
    }

    /*
    |--------------------------------------------------------------------------
    | Preferred Print Badge
    |--------------------------------------------------------------------------
    */

    public function printBadgePath(): ?string
    {
        return $this
            ->badgePdfPath();
    }

    public function printBadgeUrl(): ?string
    {
        return $this
            ->badgePdfUrl();
    }

    /*
    |--------------------------------------------------------------------------
    | Public Page
    |--------------------------------------------------------------------------
    */

    public function publicUrl(): string
    {
        return route(
            'public.attendees.show',
            [
                'token' =>
                    $this->public_token,
            ]
        );
    }
}