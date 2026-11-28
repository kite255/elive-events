<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventCertificateTemplate extends Model
{
    protected $fillable = ['name', 'width', 'height', 'definition'];

    protected function casts(): array
    {
        return ['width' => 'integer', 'height' => 'integer', 'definition' => 'array', 'is_active' => 'boolean'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(EventCertificateAsset::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(EventCertificate::class);
    }
}
