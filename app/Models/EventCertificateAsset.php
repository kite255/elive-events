<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventCertificateAsset extends Model
{
    protected $guarded = ['*'];
    protected $hidden = ['file_path'];

    public function template(): BelongsTo
    {
        return $this->belongsTo(EventCertificateTemplate::class, 'event_certificate_template_id');
    }
}
