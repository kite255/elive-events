<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MerchandiseProduct extends Model
{
    protected $fillable = ['event_id', 'name', 'slug', 'description', 'image_path', 'price_tzs', 'colors', 'sizes', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['colors' => 'array', 'sizes' => 'array', 'is_active' => 'boolean', 'price_tzs' => 'integer'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
