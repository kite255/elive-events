<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventTicketTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'ticket_type_id',
        'source_organization_template_id',
        'name',
        'width',
        'height',
        'thumbnail_path',
        'is_active',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function ticketType(): BelongsTo
    {
        return $this->belongsTo(TicketType::class);
    }

    public function sourceOrganizationTemplate(): BelongsTo
    {
        return $this->belongsTo(
            OrganizationTicketTemplate::class,
            'source_organization_template_id'
        );
    }

    public function pages(): HasMany
    {
        return $this->hasMany(TicketTemplatePage::class)
            ->orderBy('page_number');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(TicketTemplateAsset::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeDefaults(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }

    public function scopeForTicketType(
        Builder $query,
        ?int $ticketTypeId
    ): Builder {
        return $query->where(
            'ticket_type_id',
            $ticketTypeId
        );
    }
}
