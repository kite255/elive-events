<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventSession extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'event_id',
        'event_day_id',
        'name',
        'description',
        'session_type',
        'starts_at',
        'ends_at',
        'venue_name',
        'capacity',
        'requires_registration',
        'registration_is_open',
        'requires_check_in',
        'status',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'event_id' => 'integer',
            'event_day_id' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'capacity' => 'integer',
            'requires_registration' => 'boolean',
            'registration_is_open' => 'boolean',
            'requires_check_in' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function eventDay(): BelongsTo
    {
        return $this->belongsTo(EventDay::class);
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(CheckIn::class);
    }

    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(
            Attendee::class,
            'attendee_event_session'
        )
            ->withPivot([
                'status',
                'selection_source',
                'selected_at',
            ])
            ->withTimestamps();
    }

    public function registeredAttendees(): BelongsToMany
    {
        return $this->attendees()
            ->wherePivot('status', 'registered');
    }

    public function scopeActive(
        Builder $query
    ): Builder {
        return $query->where(
            'status',
            self::STATUS_ACTIVE
        );
    }

    public function scopeForEvent(
        Builder $query,
        Event|int $event
    ): Builder {
        $eventId = $event instanceof Event
            ? $event->getKey()
            : $event;

        return $query->where(
            'event_id',
            $eventId
        );
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

    public function hasCapacity(): bool
    {
        if ($this->capacity === null) {
            return true;
        }

        return $this->registeredAttendees()
            ->count() < $this->capacity;
    }

    public function remainingCapacity(): ?int
    {
        if ($this->capacity === null) {
            return null;
        }

        return max(
            $this->capacity
            - $this->registeredAttendees()->count(),
            0
        );
    }
}
