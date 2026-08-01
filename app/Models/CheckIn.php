<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckIn extends Model
{
    protected $fillable = [
        'event_id',
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
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeForEvent($query, int $eventId)
    {
        return $query->where('event_id', $eventId);
    }

    public function scopeManual($query)
    {
        return $query->where('method', 'manual');
    }

    public function scopeQr($query)
    {
        return $query->where('method', 'qr');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('checked_in_at', today());
    }

    public function scopeLatestCheckIns($query)
    {
        return $query->latest('checked_in_at');
    }
}