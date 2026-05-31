<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rsvp extends Model
{
    protected $fillable = [
        'attendee_id',
        'response',
        'guest_count',
        'responded_at',
        'ip_address',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'guest_count' => 'integer',
            'responded_at' => 'datetime',
        ];
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(Attendee::class);
    }
}