<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventCertificateAudit extends Model
{
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function certificate(): BelongsTo
    {
        return $this->belongsTo(EventCertificate::class, 'event_certificate_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
