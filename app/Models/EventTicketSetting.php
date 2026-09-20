<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventTicketSetting extends Model
{
    public const DEFAULT_RESERVATION_MINUTES = 15;
    public const DEFAULT_MAX_TICKETS_PER_ORDER = 10;

    protected $fillable = [
        'event_id',
        'ticket_sales_enabled',
        'reservation_minutes',
        'max_tickets_per_order',
        'allow_guest_checkout',
        'sales_start_at',
        'sales_end_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'ticket_sales_enabled' => 'boolean',
            'reservation_minutes' => 'integer',
            'max_tickets_per_order' => 'integer',
            'allow_guest_checkout' => 'boolean',
            'sales_start_at' => 'datetime',
            'sales_end_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function reservationMinutes(): int
    {
        return max(
            1,
            $this->reservation_minutes
                ?? self::DEFAULT_RESERVATION_MINUTES
        );
    }

    public function maxTicketsPerOrder(): int
    {
        return max(
            1,
            $this->max_tickets_per_order
                ?? self::DEFAULT_MAX_TICKETS_PER_ORDER
        );
    }

    public function salesAreOpen(): bool
    {
        if (! $this->ticket_sales_enabled) {
            return false;
        }

        if (
            $this->sales_start_at !== null
            && $this->sales_start_at->isFuture()
        ) {
            return false;
        }

        if (
            $this->sales_end_at !== null
            && $this->sales_end_at->isPast()
        ) {
            return false;
        }

        return true;
    }
}