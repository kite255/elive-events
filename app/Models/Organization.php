<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'email',
        'phone',
        'logo_path',
        'website',
        'status',

        'primary_color',
        'secondary_color',
        'background_color',
        'button_color',
        'support_email',
        'support_phone',
    ];

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function communicationTemplates(): HasMany
    {
        return $this->hasMany(CommunicationTemplate::class);
    }
}