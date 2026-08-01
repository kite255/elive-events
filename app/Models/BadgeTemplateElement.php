<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BadgeTemplateElement extends Model
{
    protected $fillable = [
        'badge_template_id',
        'type',
        'field_key',
        'label',
        'x',
        'y',
        'width',
        'height',
        'font_size',
        'font_weight',
        'color',
        'background_color',
        'text_align',
        'is_visible',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'x' => 'integer',
            'y' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'font_size' => 'integer',
            'is_visible' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function badgeTemplate(): BelongsTo
    {
        return $this->belongsTo(BadgeTemplate::class);
    }

    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}