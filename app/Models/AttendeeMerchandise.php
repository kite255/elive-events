<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendeeMerchandise extends Model
{
    protected $table = 'attendee_merchandises';

    protected $fillable = [
        'event_id',
        'attendee_id',
        'event_merchandise_id',
        'merchandise_variant_id',

        'quantity',
        'unit_price',
        'total_price',
        'currency',

        'payment_status',
        'payment_method',
        'payment_reference',
        'paid_at',

        'status',
        'selection_source',

        'selected_at',
        'cancelled_at',
        'distributed_at',
        'distributed_by',

        'note',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',

            'unit_price' => 'decimal:2',
            'total_price' => 'decimal:2',

            'selected_at' => 'datetime',
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'distributed_at' => 'datetime',
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

    public function merchandise(): BelongsTo
    {
        return $this->belongsTo(
            EventMerchandise::class,
            'event_merchandise_id'
        );
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(
            MerchandiseVariant::class,
            'merchandise_variant_id'
        );
    }

    public function distributedBy(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'distributed_by'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeSelected(Builder $query): Builder
    {
        return $query->where('status', 'selected');
    }

    public function scopeReserved(Builder $query): Builder
    {
        return $query->where('status', 'reserved');
    }

    public function scopeWaitlisted(Builder $query): Builder
    {
        return $query->where('status', 'waitlisted');
    }

    public function scopePaidStatus(Builder $query): Builder
    {
        return $query->where('status', 'paid');
    }

    public function scopeDistributed(Builder $query): Builder
    {
        return $query->where('status', 'distributed');
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', [
            'selected',
            'reserved',
            'paid',
            'distributed',
        ]);
    }

    public function scopePendingPayment(Builder $query): Builder
    {
        return $query->where('payment_status', 'pending');
    }

    public function scopePaid(Builder $query): Builder
    {
        return $query->where('payment_status', 'paid');
    }

    public function scopePaymentMethod(
        Builder $query,
        string $method
    ): Builder {
        return $query->where('payment_method', $method);
    }

    /*
    |--------------------------------------------------------------------------
    | Status Helpers
    |--------------------------------------------------------------------------
    */

    public function isSelected(): bool
    {
        return $this->status === 'selected';
    }

    public function isReserved(): bool
    {
        return $this->status === 'reserved';
    }

    public function isWaitlisted(): bool
    {
        return $this->status === 'waitlisted';
    }

    public function isOrderPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function isDistributed(): bool
    {
        return $this->status === 'distributed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isActive(): bool
    {
        return in_array(
            $this->status,
            [
                'selected',
                'reserved',
                'paid',
                'distributed',
            ],
            true
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Payment Helpers
    |--------------------------------------------------------------------------
    */

    public static function paymentMethodOptions(): array
    {
        return [
            'cash' => 'Cash',
            'mobile_money' => 'Mobile Money',
            'bank_transfer' => 'Bank Transfer',
            'card' => 'Card / POS',
            'complimentary' => 'Complimentary / Free',
        ];
    }

    public function requiresPayment(): bool
    {
        return (float) $this->total_price > 0;
    }

    public function isPaid(): bool
    {
        return $this->payment_status === 'paid';
    }

    public function isPaymentPending(): bool
    {
        return $this->payment_status === 'pending';
    }

    public function isPaymentNotRequired(): bool
    {
        return $this->payment_status === 'not_required';
    }

    public function canBeDistributed(): bool
    {
        return in_array(
            $this->payment_status,
            ['paid', 'not_required'],
            true
        ) && ! $this->isCancelled();
    }

    public function markAsPaid(
        string $paymentMethod,
        ?string $paymentReference = null
    ): bool {
        $data = [
            'payment_status' => 'paid',
            'payment_method' => $paymentMethod,
            'payment_reference' => $paymentReference,
            'paid_at' => now(),
        ];

        if ($this->status === 'reserved') {
            $data['status'] = 'paid';
        }

        return $this->update($data);
    }

    public function markPaymentNotRequired(
        ?string $paymentMethod = 'complimentary'
    ): bool {
        return $this->update([
            'payment_status' => 'not_required',
            'payment_method' => $paymentMethod,
            'payment_reference' => null,
            'paid_at' => null,
        ]);
    }

    public function calculatedTotalPrice(): float
    {
        return (float) $this->unit_price
            * max(1, (int) $this->quantity);
    }

    public function formattedUnitPrice(): string
    {
        if ((float) $this->unit_price <= 0) {
            return 'Free';
        }

        return sprintf(
            '%s %s',
            $this->currency ?: 'TZS',
            number_format((float) $this->unit_price, 2)
        );
    }

    public function formattedTotalPrice(): string
    {
        if ((float) $this->total_price <= 0) {
            return 'Free';
        }

        return sprintf(
            '%s %s',
            $this->currency ?: 'TZS',
            number_format((float) $this->total_price, 2)
        );
    }

    public function paymentMethodLabel(): string
    {
        if (blank($this->payment_method)) {
            return 'Not recorded';
        }

        return static::paymentMethodOptions()[$this->payment_method]
            ?? ucfirst(str_replace('_', ' ', $this->payment_method));
    }

    public function paymentReferenceLabel(): string
    {
        return filled($this->payment_reference)
            ? $this->payment_reference
            : 'Not recorded';
    }

    /*
    |--------------------------------------------------------------------------
    | Distribution Helpers
    |--------------------------------------------------------------------------
    */

    public function markAsDistributed(?int $userId = null): bool
    {
        if (! $this->canBeDistributed()) {
            return false;
        }

        return $this->update([
            'status' => 'distributed',
            'distributed_at' => now(),
            'distributed_by' => $userId,
        ]);
    }

    public function cancel(?string $note = null): bool
    {
        if ($this->isPaid()) {
            return false;
        }

        return $this->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'note' => $note ?: $this->note,
        ]);
    }

    public function reserve(): bool
    {
        return $this->update([
            'status' => 'reserved',
            'cancelled_at' => null,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Display Helpers
    |--------------------------------------------------------------------------
    */

    public function displayLabel(): string
    {
        $itemName = $this->merchandise?->name
            ?: 'Merchandise';

        $variantName = $this->variant?->displayName()
            ?: $this->variant?->name;

        if (filled($variantName)) {
            return "{$itemName} — {$variantName}";
        }

        return $itemName;
    }

    public function quantityLabel(): string
    {
        return sprintf(
            '%d item%s',
            (int) $this->quantity,
            (int) $this->quantity === 1 ? '' : 's'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    public function getFormattedUnitPriceAttribute(): string
    {
        return $this->formattedUnitPrice();
    }

    public function getFormattedTotalPriceAttribute(): string
    {
        return $this->formattedTotalPrice();
    }

    public function getDisplayLabelAttribute(): string
    {
        return $this->displayLabel();
    }

    public function getQuantityLabelAttribute(): string
    {
        return $this->quantityLabel();
    }

    public function getPaymentMethodLabelAttribute(): string
    {
        return $this->paymentMethodLabel();
    }

    public function getPaymentReferenceLabelAttribute(): string
    {
        return $this->paymentReferenceLabel();
    }
}
