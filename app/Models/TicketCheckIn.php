<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketCheckIn extends Model
{
    protected $table = 'ticket_check_ins';

    protected $fillable = [
        'event_id',
        'ticket_id',
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
            'ticket_id' => 'integer',
            'check_in_point_id' => 'integer',
            'checked_in_by' => 'integer',
            'checked_in_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function checkInPoint(): BelongsTo
    {
        return $this->belongsTo(CheckInPoint::class);
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
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

    public function methodLabel(): string
    {
        return match ($this->method) {
            'qr' => 'QR Code',
            'manual' => 'Manual Lookup',
            'ticket_number' => 'Ticket Number',
            default => filled($this->method)
                ? str($this->method)->replace('_', ' ')->headline()->toString()
                : 'Unknown',
        };
    }
}
