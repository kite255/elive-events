<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BadgeTemplate extends Model
{
    protected $fillable = [
        'event_id',
        'category_id',
        'badge_type_id',

        'name',

        'width',
        'height',

        'background_color',
        'background_image_path',
        'header_color',
        'accent_color',
        'text_color',
        'footer_color',
        'logo_path',

        'design_config',

        'show_category',
        'show_badge_type',
        'show_badge_number',
        'show_organization',
        'show_position',

        'is_default',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',

            'design_config' => 'array',

            'show_category' => 'boolean',
            'show_badge_type' => 'boolean',
            'show_badge_number' => 'boolean',
            'show_organization' => 'boolean',
            'show_position' => 'boolean',

            'is_default' => 'boolean',
            'is_active' => 'boolean',
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

    public function category(): BelongsTo
    {
        return $this->belongsTo(AttendeeCategory::class, 'category_id');
    }

    public function badgeType(): BelongsTo
    {
        return $this->belongsTo(BadgeType::class);
    }

    public function elements(): HasMany
    {
        return $this->hasMany(BadgeTemplateElement::class)
            ->orderBy('sort_order');
    }

    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    public function scopeForEvent($query, int $eventId)
    {
        return $query->where('event_id', $eventId);
    }

    public function scopeForCategory($query, int $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeForBadgeType($query, int $badgeTypeId)
    {
        return $query->where('badge_type_id', $badgeTypeId);
    }

    public function scopeGlobal($query)
    {
        return $query->whereNull('event_id');
    }

    /*
    |--------------------------------------------------------------------------
    | Designer Helpers
    |--------------------------------------------------------------------------
    */

    public function getDesignConfigWithDefaults(): array
    {
        return array_replace_recursive(
            self::defaultDesignConfig(),
            $this->design_config ?? []
        );
    }

    public static function defaultDesignConfig(): array
    {
        return [
            'canvas' => [
                'width' => 420,
                'height' => 620,
                'background_image_path' => null,
            ],

            'enabled_elements' => [
                'name',
                'category',
                'organization',
                'position',
                'badge_number',
                'qr_code',
            ],

            'name' => [
                'x' => 210,
                'y' => 250,
                'font_size' => 30,
                'font_weight' => 'bold',
                'color' => '#FFFFFF',
                'align' => 'center',
                'visible' => true,
            ],

            'category' => [
                'x' => 210,
                'y' => 315,
                'font_size' => 18,
                'font_weight' => 'bold',
                'color' => '#FFFFFF',
                'background' => '#F99A12',
                'align' => 'center',
                'visible' => true,
            ],

            'organization' => [
                'x' => 210,
                'y' => 360,
                'font_size' => 14,
                'font_weight' => '600',
                'color' => '#DBEAFE',
                'align' => 'center',
                'visible' => true,
            ],

            'position' => [
                'x' => 210,
                'y' => 385,
                'font_size' => 13,
                'font_weight' => '400',
                'color' => '#E0F2FE',
                'align' => 'center',
                'visible' => true,
            ],

            'badge_number' => [
                'x' => 210,
                'y' => 420,
                'font_size' => 13,
                'font_weight' => 'bold',
                'color' => '#FFFFFF',
                'align' => 'center',
                'visible' => true,
            ],

            'qr_code' => [
                'x' => 150,
                'y' => 465,
                'size' => 120,
                'visible' => true,
            ],

            'elements' => [
                [
                    'id' => 'name_001',
                    'type' => 'attendee_name',
                    'key' => 'name',
                    'label' => 'Attendee Name',
                    'x' => 210,
                    'y' => 250,
                    'width' => 360,
                    'font_size' => 30,
                    'font_weight' => '800',
                    'color' => '#FFFFFF',
                    'align' => 'center',
                    'visible' => true,
                    'z_index' => 10,
                ],
                [
                    'id' => 'category_001',
                    'type' => 'category',
                    'key' => 'category',
                    'label' => 'Category',
                    'x' => 210,
                    'y' => 315,
                    'width' => 230,
                    'height' => 38,
                    'font_size' => 18,
                    'font_weight' => '800',
                    'color' => '#FFFFFF',
                    'background' => '#F99A12',
                    'align' => 'center',
                    'visible' => true,
                    'z_index' => 20,
                ],
                [
                    'id' => 'organization_001',
                    'type' => 'organization',
                    'key' => 'organization',
                    'label' => 'Organization',
                    'x' => 210,
                    'y' => 360,
                    'width' => 360,
                    'font_size' => 14,
                    'font_weight' => '600',
                    'color' => '#DBEAFE',
                    'align' => 'center',
                    'visible' => true,
                    'z_index' => 30,
                ],
                [
                    'id' => 'position_001',
                    'type' => 'position',
                    'key' => 'position',
                    'label' => 'Position / Title',
                    'x' => 210,
                    'y' => 385,
                    'width' => 360,
                    'font_size' => 13,
                    'font_weight' => '500',
                    'color' => '#E0F2FE',
                    'align' => 'center',
                    'visible' => true,
                    'z_index' => 40,
                ],
                [
                    'id' => 'badge_number_001',
                    'type' => 'badge_number',
                    'key' => 'badge_number',
                    'label' => 'Badge Number',
                    'x' => 210,
                    'y' => 420,
                    'width' => 360,
                    'font_size' => 13,
                    'font_weight' => '800',
                    'color' => '#FFFFFF',
                    'align' => 'center',
                    'visible' => true,
                    'z_index' => 50,
                ],
                [
                    'id' => 'qr_code_001',
                    'type' => 'qr_code',
                    'key' => 'qr_code',
                    'label' => 'QR Code',
                    'x' => 150,
                    'y' => 465,
                    'size' => 120,
                    'visible' => true,
                    'z_index' => 60,
                ],
            ],
        ];
    }

    public function backgroundImageUrl(): ?string
    {
        if (blank($this->background_image_path)) {
            return null;
        }

        return asset('storage/' . $this->background_image_path);
    }

    public function logoUrl(): ?string
    {
        if (blank($this->logo_path)) {
            return null;
        }

        return asset('storage/' . $this->logo_path);
    }
}