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
            /*
            |--------------------------------------------------------------------------
            | Canvas
            |--------------------------------------------------------------------------
            |
            | This matches the current Camp Meeting badge background.
            |
            */

            'canvas' => [
                'width' => 1638,
                'height' => 2048,
                'background_image_path' => null,
            ],

            /*
            |--------------------------------------------------------------------------
            | Enabled Elements
            |--------------------------------------------------------------------------
            |
            | For the coming event we only need:
            | - Attendee category
            | - Attendee full name
            | - Secure QR code
            |
            */

            'enabled_elements' => [
                'category',
                'name',
                'qr_code',
            ],

            /*
            |--------------------------------------------------------------------------
            | Category
            |--------------------------------------------------------------------------
            |
            | Example:
            | MEDIA CREW
            |
            */

            'category' => [
                'x' => 819,
                'y' => 820,
                'width' => 1350,
                'font_size' => 110,
                'min_font_size' => 65,
                'font_weight' => '800',
                'color' => '#FFFFFF',
                'align' => 'center',
                'uppercase' => true,
                'visible' => true,
            ],

            /*
            |--------------------------------------------------------------------------
            | Attendee Name
            |--------------------------------------------------------------------------
            |
            | Example:
            | JOEL MWASIPOSA
            |
            */

            'name' => [
                'x' => 819,
                'y' => 1080,
                'width' => 1250,
                'font_size' => 70,
                'min_font_size' => 42,
                'font_weight' => '500',
                'color' => '#FFFFFF',
                'align' => 'center',
                'uppercase' => true,
                'visible' => true,
            ],

            /*
            |--------------------------------------------------------------------------
            | QR Code
            |--------------------------------------------------------------------------
            |
            | X represents the center position.
            | The renderer can calculate the top-left position using:
            |
            | $left = $x - ($size / 2);
            |
            */

            'qr_code' => [
                'x' => 819,
                'y' => 1430,
                'size' => 470,
                'padding' => 20,
                'align' => 'center',
                'visible' => true,
            ],

            /*
            |--------------------------------------------------------------------------
            | Elements
            |--------------------------------------------------------------------------
            |
            | Keep this array because the existing badge designer / preview
            | may already depend on design_config.elements.
            |
            */

            'elements' => [
                [
                    'id' => 'category_001',
                    'type' => 'category',
                    'key' => 'category',
                    'label' => 'Category',

                    'x' => 819,
                    'y' => 820,
                    'width' => 1350,

                    'font_size' => 110,
                    'min_font_size' => 65,
                    'font_weight' => '800',

                    'color' => '#FFFFFF',
                    'align' => 'center',
                    'uppercase' => true,

                    'visible' => true,
                    'z_index' => 10,
                ],

                [
                    'id' => 'name_001',
                    'type' => 'attendee_name',
                    'key' => 'name',
                    'label' => 'Attendee Name',

                    'x' => 819,
                    'y' => 1080,
                    'width' => 1250,

                    'font_size' => 70,
                    'min_font_size' => 42,
                    'font_weight' => '500',

                    'color' => '#FFFFFF',
                    'align' => 'center',
                    'uppercase' => true,

                    'visible' => true,
                    'z_index' => 20,
                ],

                [
                    'id' => 'qr_code_001',
                    'type' => 'qr_code',
                    'key' => 'qr_code',
                    'label' => 'QR Code',

                    'x' => 819,
                    'y' => 1430,
                    'size' => 470,
                    'padding' => 20,
                    'align' => 'center',

                    'visible' => true,
                    'z_index' => 30,
                ],
            ],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Convenience Helpers
    |--------------------------------------------------------------------------
    */

    public function canvasWidth(): int
    {
        $config = $this->getDesignConfigWithDefaults();

        return (int) (
            $config['canvas']['width']
            ?? $this->width
            ?? 1638
        );
    }

    public function canvasHeight(): int
    {
        $config = $this->getDesignConfigWithDefaults();

        return (int) (
            $config['canvas']['height']
            ?? $this->height
            ?? 2048
        );
    }

    public function enabledElements(): array
    {
        $config = $this->getDesignConfigWithDefaults();

        return $config['enabled_elements'] ?? [
            'category',
            'name',
            'qr_code',
        ];
    }

    public function hasElement(string $key): bool
    {
        return in_array(
            $key,
            $this->enabledElements(),
            true
        );
    }

    public function elementConfig(string $key): array
    {
        $config = $this->getDesignConfigWithDefaults();

        return $config[$key] ?? [];
    }

    public function backgroundImagePath(): ?string
    {
        /*
        |--------------------------------------------------------------------------
        | Priority
        |--------------------------------------------------------------------------
        |
        | 1. Main badge_templates.background_image_path
        | 2. design_config.canvas.background_image_path
        |
        */

        if (filled($this->background_image_path)) {
            return $this->background_image_path;
        }

        $config = $this->getDesignConfigWithDefaults();

        $path = $config['canvas']['background_image_path'] ?? null;

        return filled($path)
            ? $path
            : null;
    }

    public function backgroundImageUrl(): ?string
    {
        $path = $this->backgroundImagePath();

        if (blank($path)) {
            return null;
        }

        return asset('storage/' . ltrim($path, '/'));
    }

    public function logoUrl(): ?string
    {
        if (blank($this->logo_path)) {
            return null;
        }

        return asset('storage/' . ltrim($this->logo_path, '/'));
    }
}