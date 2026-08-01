<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventMerchandise extends Model
{
    protected $table = 'event_merchandises';

    protected $fillable = [
        'event_id',
        'name',
        'description',
        'image_path',
        'show_image',
        'selection_type',
        'selection_opens_at',
        'selection_closes_at',
        'maximum_per_attendee',
        'is_active',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'show_image' => 'boolean',
            'selection_opens_at' => 'datetime',
            'selection_closes_at' => 'datetime',
            'maximum_per_attendee' => 'integer',
            'is_active' => 'boolean',
            'display_order' => 'integer',
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

    public function variants(): HasMany
    {
        return $this->hasMany(
            MerchandiseVariant::class,
            'event_merchandise_id'
        )
            ->orderBy('display_order')
            ->orderBy('id');
    }

    public function activeVariants(): HasMany
    {
        return $this->variants()
            ->where('is_active', true);
    }

    public function selections(): HasMany
    {
        return $this->hasMany(
            AttendeeMerchandise::class,
            'event_merchandise_id'
        );
    }

    public function activeSelections(): HasMany
    {
        return $this->selections()
            ->whereIn('status', [
                'selected',
                'reserved',
                'distributed',
            ]);
    }

    public function reservedSelections(): HasMany
    {
        return $this->selections()
            ->whereIn('status', [
                'selected',
                'reserved',
            ]);
    }

    public function distributedSelections(): HasMany
    {
        return $this->selections()
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

    public function scopeVisibleToAttendees(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->whereIn('selection_type', [
                'optional',
                'required',
            ]);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy('display_order')
            ->orderBy('id');
    }

    /*
    |--------------------------------------------------------------------------
    | Selection Helpers
    |--------------------------------------------------------------------------
    */

    public function isOptional(): bool
    {
        return $this->selection_type === 'optional';
    }

    public function isRequired(): bool
    {
        return $this->selection_type === 'required';
    }

    public function isAutomatic(): bool
    {
        return $this->selection_type === 'automatic';
    }

    public function isAdminOnly(): bool
    {
        return $this->selection_type === 'admin_only';
    }

    public function isSelectionOpen(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if (
            $this->selection_opens_at
            && now()->lt($this->selection_opens_at)
        ) {
            return false;
        }

        if (
            $this->selection_closes_at
            && now()->gt($this->selection_closes_at)
        ) {
            return false;
        }

        return true;
    }

    public function shouldShowImage(): bool
    {
        return (bool) $this->show_image
            && (bool) $this->event?->show_merchandise_images
            && filled($this->image_path);
    }

    /*
    |--------------------------------------------------------------------------
    | Stock Helpers
    |--------------------------------------------------------------------------
    */

    public function totalStock(): int
    {
        return (int) $this->variants()
            ->sum('stock_quantity');
    }

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

    public function remainingStock(): int
    {
        return max(
            0,
            $this->totalStock()
                - $this->expectedQuantity()
                - $this->distributedQuantity()
        );
    }

    public function shortageQuantity(): int
    {
        $required = $this->expectedQuantity()
            + $this->distributedQuantity();

        return max(
            0,
            $required - $this->totalStock()
        );
    }

    public function hasVariants(): bool
    {
        return $this->variants()->exists();
    }

    public function hasAvailableStock(): bool
    {
        return $this->remainingStock() > 0;
    }
}