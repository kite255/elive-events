<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendeeCategory extends Model
{
    protected $fillable = [
        'event_id',
        'name',
        'group_name',
        'color',
        'is_public',
        'is_active',
        'badge_type_id',
        'description',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function badgeType(): BelongsTo
    {
        return $this->belongsTo(
            BadgeType::class,
            'badge_type_id'
        );
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(
            Attendee::class,
            'category_id'
        );
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopePubliclySelectable($query)
    {
        return $query
            ->where('is_active', true)
            ->where('is_public', true);
    }

    public function getDisplayGroupAttribute(): string
    {
        return filled($this->group_name)
            ? $this->group_name
            : 'General Participants';
    }
}