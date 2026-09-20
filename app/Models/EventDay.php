<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventDay extends Model
{
    protected $fillable = [
        'event_id',
        'name',
        'event_date',
        'starts_at',
        'ends_at',
        'venue_name',
        'capacity',
        'is_registration_open',
        'status',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'starts_at' => 'datetime:H:i',
            'ends_at' => 'datetime:H:i',
            'capacity' => 'integer',
            'is_registration_open' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(
            Attendee::class,
            'attendee_event_day'
        )
            ->withPivot([
                'selection_source',
                'selected_at',
            ])
            ->withTimestamps();
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(EventSession::class)
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

    public function hasSessions(): bool
    {
        return $this->sessions()->exists();
    }

    public function sessionsCount(): int
    {
        return $this->sessions()->count();
    }

    public function activeSessionsCount(): int
    {
        return $this->activeSessions()->count();
    }

    public function attendeesCount(): int
    {
        return $this->attendees()->count();
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
            (int) $this->capacity - $this->attendeesCount(),
            0
        );
    }

    public function isFull(): bool
    {
        if (
            blank($this->capacity)
            || (int) $this->capacity <= 0
        ) {
            return false;
        }

        return $this->attendeesCount()
            >= (int) $this->capacity;
    }
}
