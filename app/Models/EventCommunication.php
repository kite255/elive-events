<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class EventCommunication extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_SENT = 'sent';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'event_id',
        'created_by',
        'type',
        'title',
        'slug',
        'summary',
        'body',
        'status',
        'is_public',
        'public_token',
        'published_at',
        'scheduled_at',

        'hero_enabled',
        'hero_image_path',
        'hero_title',
        'hero_subtitle',
        'hero_overlay_enabled',
        'hero_text_alignment',
        'hero_height',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'published_at' => 'datetime',
            'scheduled_at' => 'datetime',

            'hero_enabled' => 'boolean',
            'hero_overlay_enabled' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (EventCommunication $communication): void {
            if (blank($communication->slug)) {
                $communication->slug = static::generateUniqueSlug(
                    $communication->event_id,
                    $communication->title
                );
            }

            if (blank($communication->public_token)) {
                $communication->public_token = Str::random(48);
            }

            if (blank($communication->status)) {
                $communication->status = self::STATUS_DRAFT;
            }
        });

        static::updating(function (EventCommunication $communication): void {
            if (
                $communication->isDirty('title')
                && blank($communication->slug)
            ) {
                $communication->slug = static::generateUniqueSlug(
                    $communication->event_id,
                    $communication->title,
                    $communication->getKey()
                );
            }
        });
    }

    public static function generateUniqueSlug(
        int $eventId,
        string $title,
        ?int $ignoreId = null
    ): string {
        $base = Str::slug($title) ?: 'communication';
        $slug = $base;
        $counter = 2;

        while (
            static::query()
                ->where('event_id', $eventId)
                ->where('slug', $slug)
                ->when(
                    $ignoreId,
                    fn ($query) => $query->whereKeyNot($ignoreId)
                )
                ->exists()
        ) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    /*
    |--------------------------------------------------------------------------
    | Parent Relationships
    |--------------------------------------------------------------------------
    */

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'created_by'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Communication Content Relationships
    |--------------------------------------------------------------------------
    */

    public function sections(): HasMany
    {
        return $this->hasMany(
            EventCommunicationSection::class
        )
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(
            EventCommunicationImage::class
        )
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(
            EventCommunicationAttachment::class
        )
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function links(): HasMany
    {
        return $this->hasMany(
            EventCommunicationLink::class
        )
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /*
    |--------------------------------------------------------------------------
    | Publishing Helpers
    |--------------------------------------------------------------------------
    */

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED
            && $this->published_at !== null;
    }

    public function publicUrl(): string
    {
        return route(
            'public.event-communications.show',
            [
                'event' => $this->event->slug,
                'communication' => $this->slug,
            ]
        );
    }
}
