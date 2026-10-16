<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchandiseProduct extends Model
{
    protected $fillable = ['event_id', 'event_merchandise_id', 'name', 'slug', 'category', 'description', 'image_path', 'price_tzs', 'colors', 'sizes', 'is_active', 'sort_order', 'stock_quantity'];

    protected function casts(): array
    {
        return ['colors' => 'array', 'sizes' => 'array', 'is_active' => 'boolean', 'price_tzs' => 'integer', 'stock_quantity' => 'integer'];
    }

    public function sourceMerchandise(): BelongsTo
    {
        return $this->belongsTo(EventMerchandise::class, 'event_merchandise_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
