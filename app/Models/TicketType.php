<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TicketType extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'name',
        'code',
        'description',
        'price',
        'currency',
        'capacity',
        'min_per_order',
        'max_per_order',
        'sales_start_at',
        'sales_end_at',
        'is_active',
        'is_public',
        'requires_holder_details',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'capacity' => 'integer',
            'min_per_order' => 'integer',
            'max_per_order' => 'integer',
            'sales_start_at' => 'datetime',
            'sales_end_at' => 'datetime',
            'is_active' => 'boolean',
            'is_public' => 'boolean',
            'requires_holder_details' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(TicketOrderItem::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    public function scopeOnSale(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where('is_public', true)
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('sales_start_at')
                    ->orWhere('sales_start_at', '<=', now());
            })
            ->where(function (Builder $query): void {
                $query
                    ->whereNull('sales_end_at')
                    ->orWhere('sales_end_at', '>=', now());
            });
    }

    public function soldCount(): int
    {
        return $this->tickets()
            ->whereNotIn('status', [
                Ticket::STATUS_CANCELLED,
                Ticket::STATUS_REFUNDED,
            ])
            ->count();
    }

    public function remainingCapacity(): ?int
    {
        if ($this->capacity === null || $this->capacity <= 0) {
            return null;
        }

        return max(
            0,
            $this->capacity - $this->soldCount()
        );
    }

    public function isSoldOut(): bool
    {
        $remaining = $this->remainingCapacity();

        return $remaining !== null
            && $remaining <= 0;
    }
}