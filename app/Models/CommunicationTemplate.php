<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommunicationTemplate extends Model
{
    public const CHANNEL_SMS = 'sms';
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_WHATSAPP = 'whatsapp';

    protected $fillable = [
        'organization_id',
        'name',
        'channel',
        'subject',
        'body',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function organization(): BelongsTo
    {
        return $this->belongsTo(
            Organization::class
        );
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(
            CommunicationCampaign::class,
            'communication_template_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeForOrganization(
        Builder $query,
        int $organizationId
    ): Builder {
        return $query->where(
            'organization_id',
            $organizationId
        );
    }

    public function scopeActive(
        Builder $query
    ): Builder {
        return $query->where(
            'is_active',
            true
        );
    }

    public function scopeForChannel(
        Builder $query,
        string $channel
    ): Builder {
        return $query->where(
            'channel',
            $channel
        );
    }

    public function scopeNamed(
        Builder $query,
        string $name
    ): Builder {
        return $query->where(
            'name',
            $name
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isSms(): bool
    {
        return $this->channel === self::CHANNEL_SMS;
    }

    public function isEmail(): bool
    {
        return $this->channel === self::CHANNEL_EMAIL;
    }

    public function isWhatsApp(): bool
    {
        return $this->channel === self::CHANNEL_WHATSAPP;
    }

    public function isUsable(): bool
    {
        return (bool) $this->is_active
            && filled($this->body);
    }
}