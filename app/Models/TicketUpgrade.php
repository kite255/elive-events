<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class TicketUpgrade extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    public const UNRESOLVED_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PROCESSING,
    ];

    protected $fillable = [
        'event_id',
        'ticket_order_id',
        'ticket_id',
        'from_ticket_type_id',
        'to_ticket_type_id',
        'public_token',
        'reference',
        'original_price',
        'target_price',
        'upgrade_amount',
        'currency',
        'status',
        'initiated_by',
        'completed_by',
        'initiated_at',
        'completed_at',
        'expires_at',
        'gross_amount',
        'platform_commission_rate',
        'platform_commission_amount',
        'gateway_fee_rate',
        'gateway_fee_amount',
        'total_charges',
        'organizer_net_amount',
        'financial_snapshot_at',
        'metadata',
    ];

    protected static function booted(): void
    {
        static::creating(function (TicketUpgrade $upgrade): void {
            if (blank($upgrade->public_token)) {
                do {
                    $token = Str::random(48);
                } while (
                    self::query()
                        ->where('public_token', $token)
                        ->exists()
                );

                $upgrade->public_token = $token;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'original_price' => 'decimal:2',
            'target_price' => 'decimal:2',
            'upgrade_amount' => 'decimal:2',
            'gross_amount' => 'decimal:2',
            'platform_commission_rate' => 'decimal:4',
            'platform_commission_amount' => 'decimal:2',
            'gateway_fee_rate' => 'decimal:4',
            'gateway_fee_amount' => 'decimal:2',
            'total_charges' => 'decimal:2',
            'organizer_net_amount' => 'decimal:2',
            'initiated_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
            'financial_snapshot_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(TicketOrder::class, 'ticket_order_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function fromTicketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class, 'from_ticket_type_id');
    }

    public function toTicketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class, 'to_ticket_type_id');
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isUnresolved(): bool
    {
        return in_array($this->status, self::UNRESOLVED_STATUSES, true);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED
            || ($this->expires_at !== null && $this->expires_at->isPast());
    }
}
