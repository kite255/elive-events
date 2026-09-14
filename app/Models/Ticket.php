<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Ticket extends Model
{
    use HasFactory;

    public const STATUS_ISSUED = 'issued';
    public const STATUS_USED = 'used';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'event_id',
        'ticket_order_id',
        'ticket_order_item_id',
        'ticket_type_id',
        'attendee_id',
        'ticket_number',
        'public_token',
        'qr_token_encrypted',
        'qr_token_hash',
        'holder_name',
        'holder_phone',
        'holder_email',
        'price',
        'currency',
        'status',
        'issued_at',
        'used_at',
        'cancelled_at',
        'refunded_at',
        'metadata',
    ];

    protected $hidden = [
        'qr_token_encrypted',
        'qr_token_hash',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',

            /*
             * Laravel transparently encrypts this before
             * storing it and decrypts it when accessed.
             */
            'qr_token_encrypted' =>
                'encrypted',

            'issued_at' => 'datetime',
            'used_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'refunded_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(
            Event::class
        );
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(
            TicketOrder::class,
            'ticket_order_id'
        );
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(
            TicketOrderItem::class,
            'ticket_order_item_id'
        );
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(
            TicketType::class
        );
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(
            Attendee::class
        );
    }

    public function isUsable(): bool
    {
        return $this->status
            === self::STATUS_ISSUED;
    }

    public function isUsed(): bool
    {
        return $this->status
            === self::STATUS_USED;
    }

    public function isCancelled(): bool
    {
        return $this->status
            === self::STATUS_CANCELLED;
    }

    public function isRefunded(): bool
    {
        return $this->status
            === self::STATUS_REFUNDED;
    }
}