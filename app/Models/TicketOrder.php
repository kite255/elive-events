<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class TicketOrder extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_PARTIALLY_REFUNDED =
        'partially_refunded';

    protected $fillable = [
        'event_id',
        'attendee_id',
        'order_number',
        'public_token',
        'buyer_name',
        'buyer_phone',
        'buyer_email',
        'quantity',

        'subtotal',
        'discount_amount',
        'total',

        'gross_amount',
        'platform_commission_rate',
        'platform_commission_amount',
        'gateway_fee_rate',
        'gateway_fee_amount',
        'total_charges',
        'organizer_net_amount',
        'financial_snapshot_at',

        'currency',
        'status',
        'paid_at',
        'expires_at',
        'metadata',
    ];

    protected static function booted(): void
    {
        static::creating(
            function (TicketOrder $order): void {
                if (blank($order->public_token)) {
                    $order->public_token =
                        self::generatePublicToken();
                }
            }
        );
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',

            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'total' => 'decimal:2',

            'gross_amount' => 'decimal:2',
            'platform_commission_rate' => 'decimal:2',
            'platform_commission_amount' => 'decimal:2',
            'gateway_fee_rate' => 'decimal:2',
            'gateway_fee_amount' => 'decimal:2',
            'total_charges' => 'decimal:2',
            'organizer_net_amount' => 'decimal:2',

            'financial_snapshot_at' => 'datetime',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',

            'metadata' => 'array',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(
            Event::class
        );
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(
            Attendee::class
        );
    }

    public function items(): HasMany
    {
        return $this->hasMany(
            TicketOrderItem::class
        );
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(
            Ticket::class
        );
    }

    public function communicationLogs(): HasMany
    {
        return $this->hasMany(
            CommunicationLog::class
        );
    }

    public function payments(): HasMany
    {
        return $this->hasMany(
            Payment::class
        );
    }

    public function latestPayment(): HasOne
    {
        return $this->hasOne(
            Payment::class
        )->latestOfMany();
    }

    public function isPending(): bool
    {
        return in_array(
            $this->status,
            [
                self::STATUS_PENDING,
                self::STATUS_PROCESSING,
            ],
            true
        );
    }

    public function isPaid(): bool
    {
        return $this->status
            === self::STATUS_PAID;
    }

    public function isCancelled(): bool
    {
        return $this->status
            === self::STATUS_CANCELLED;
    }

    public function isExpired(): bool
    {
        return $this->status
            === self::STATUS_EXPIRED;
    }

    public function hasFinancialSnapshot(): bool
    {
        return $this->financial_snapshot_at !== null;
    }

    public function canIssueTickets(): bool
    {
        return $this->isPaid();
    }

    private static function generatePublicToken(): string
    {
        do {
            $token =
                Str::random(48);
        } while (
            self::query()
                ->where(
                    'public_token',
                    $token
                )
                ->exists()
        );

        return $token;
    }
}
