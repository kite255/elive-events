<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BadgePrintLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'attendee_id',
        'printed_by',
        'copies',
        'printer_name',
        'print_type',
        'reprint_reason',
        'printed_at',
    ];

    protected function casts(): array
    {
        return [
            'copies' => 'integer',
            'printed_at' => 'datetime',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(Attendee::class);
    }

    public function printedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'printed_by');
    }
}
