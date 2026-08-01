<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
}