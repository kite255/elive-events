<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrganizationTicketTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'description',
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

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
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

    public function eventTemplates(): HasMany
    {
        return $this->hasMany(
            EventTicketTemplate::class,
            'source_organization_template_id'
        );
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeDefaults(Builder $query): Builder
    {
        return $query->where('is_default', true);
    }
}
