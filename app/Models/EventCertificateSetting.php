<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventCertificateSetting extends Model
{
    protected $attributes = ['enabled' => false, 'eligibility_rule' => 'checked_in'];
    protected $fillable = ['enabled', 'eligibility_rule'];
    public const RULES = ['registered', 'confirmed', 'checked_in', 'manual'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
