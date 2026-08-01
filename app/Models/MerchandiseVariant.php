<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MerchandiseVariant extends Model
{
    protected $fillable = [
        'event_merchandise_id',
        'name',
        'size',
        'color_name',
        'color_code',
        'sku',
        'stock_quantity',
        'price',
        'currency',
        'is_active',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'stock_quantity' => 'integer',
            'price' => 'decimal:2',
            'is_active' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function merchandise(): BelongsTo
    {
        return $this->belongsTo(
            EventMerchandise::class,
            'event_merchandise_id'
        );
    }

    public function attendeeSelections(): HasMany
    {
        return $this->hasMany(
            AttendeeMerchandise::class,
            'merchandise_variant_id'
        );
    }

    public function activeSelections(): HasMany
    {
        return $this->attendeeSelections()
            ->whereIn('status', [
                'selected',
                'reserved',
                'distributed',
            ]);
    }

    public function reservedSelections(): HasMany
    {
        return $this->attendeeSelections()
            ->whereIn('status', [
                'selected',
                'reserved',
            ]);
    }

    public function distributedSelections(): HasMany
    {
        return $this->attendeeSelections()
            ->where('status', 'distributed');
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('stock_quantity', '>', 0);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy('display_order')
            ->orderBy('id');
    }

    /*
    |--------------------------------------------------------------------------
    | Stock Helpers
    |--------------------------------------------------------------------------
    */

    public function expectedQuantity(): int
    {
        return (int) $this->reservedSelections()
            ->sum('quantity');
    }

    public function distributedQuantity(): int
    {
        return (int) $this->distributedSelections()
            ->sum('quantity');
    }

    public function committedQuantity(): int
    {
        return $this->expectedQuantity()
            + $this->distributedQuantity();
    }

    public function remainingQuantity(): int
    {
        return max(
            0,
            (int) $this->stock_quantity
                - $this->committedQuantity()
        );
    }

    public function shortageQuantity(): int
    {
        return max(
            0,
            $this->committedQuantity()
                - (int) $this->stock_quantity
        );
    }

    public function hasAvailableStock(int $requestedQuantity = 1): bool
    {
        return $this->is_active
            && $requestedQuantity > 0
            && $this->remainingQuantity() >= $requestedQuantity;
    }

    public function isOutOfStock(): bool
    {
        return $this->remainingQuantity() <= 0;
    }

    /*
    |--------------------------------------------------------------------------
    | Price Helpers
    |--------------------------------------------------------------------------
    */

    public function unitPrice(): float
    {
        return (float) $this->price;
    }

    public function totalPrice(int $quantity = 1): float
    {
        return $this->unitPrice() * max(1, $quantity);
    }

    public function isFree(): bool
    {
        return $this->unitPrice() <= 0;
    }

    public function formattedPrice(): string
    {
        if ($this->isFree()) {
            return 'Free';
        }

        return sprintf(
            '%s %s',
            $this->currency ?: 'TZS',
            number_format($this->unitPrice(), 2)
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Display Helpers
    |--------------------------------------------------------------------------
    */

    public function displayName(): string
    {
        $parts = collect([
            $this->size,
            $this->color_name,
        ])
            ->filter()
            ->values();

        if ($parts->isNotEmpty()) {
            return $parts->implode(' / ');
        }

        return $this->name;
    }

    public function fullLabel(): string
    {
        return sprintf(
            '%s — %s — Stock: %d',
            $this->displayName(),
            $this->formattedPrice(),
            $this->remainingQuantity()
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    public function getExpectedQuantityAttribute(): int
    {
        return $this->expectedQuantity();
    }

    public function getDistributedQuantityAttribute(): int
    {
        return $this->distributedQuantity();
    }

    public function getRemainingQuantityAttribute(): int
    {
        return $this->remainingQuantity();
    }

    public function getShortageQuantityAttribute(): int
    {
        return $this->shortageQuantity();
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->displayName();
    }

    public function getFormattedPriceAttribute(): string
    {
        return $this->formattedPrice();
    }
}