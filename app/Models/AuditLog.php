<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'actor_id',
        'event_id',
        'action',
        'subject_type',
        'subject_id',
        'reference',
        'before_data',
        'after_data',
        'metadata',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'before_data' => 'array',
            'after_data' => 'array',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeAccessibleBy(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isSuperAdmin()) {
            return $query;
        }

        if ($user->isTicketOrganizer()) {
            $ids = $user->assignedTicketingEventIds();

            return $ids->isEmpty()
                ? $query->whereRaw('1 = 0')
                : $query->whereIn('event_id', $ids);
        }

        return $query->whereHas(
            'event',
            fn (Builder $eventQuery): Builder => $eventQuery->accessibleBy($user)
        );
    }
}
