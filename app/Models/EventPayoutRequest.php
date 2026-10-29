<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventPayoutRequest extends Model
{
    public const REQUESTED = 'requested';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';
    public const PAID = 'paid';

    protected $fillable = [
        'event_id', 'requested_by', 'reference', 'amount', 'currency',
        'destination_type', 'destination_details',
    ];

    protected $hidden = ['destination_details'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'destination_details' => 'encrypted', 'reviewed_at' => 'datetime', 'paid_at' => 'datetime', 'received_at' => 'datetime'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
