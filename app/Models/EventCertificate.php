<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventCertificate extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ISSUED = 'issued';
    public const STATUS_REVOKED = 'revoked';

    protected $guarded = ['*'];
    protected $hidden = ['verification_token', 'pdf_path', 'issuance_snapshot'];

    protected function casts(): array
    {
        return ['issuance_snapshot' => 'array', 'issued_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(Attendee::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EventCertificateTemplate::class, 'event_certificate_template_id');
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(EventCertificateAudit::class);
    }
}
