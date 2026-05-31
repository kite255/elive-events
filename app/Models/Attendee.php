<?php

namespace App\Models;

use App\Services\QrTokenService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attendee extends Model
{
    protected $fillable = [
        'event_id',
        'category_id',
        'badge_type_id',
        'full_name',
        'phone',
        'email',
        'organization_name',
        'position',
        'status',
        'registration_source',
        'registered_at',
        'badge_number',
        'badge_path',
        'checked_in_at',
    ];

    protected function casts(): array
    {
        return [
            'registered_at' => 'datetime',
            'checked_in_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Attendee $attendee) {
            if (blank($attendee->registered_at)) {
                $attendee->registered_at = now();
            }

            if (blank($attendee->badge_number)) {
                $attendee->badge_number = 'ELV-' . now()->format('Ymd') . '-' . strtoupper(fake()->bothify('####??'));
            }
        });

        static::created(function (Attendee $attendee) {
            app(QrTokenService::class)->generateForAttendee($attendee);
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AttendeeCategory::class, 'category_id');
    }

    public function badgeType(): BelongsTo
    {
        return $this->belongsTo(BadgeType::class);
    }

    public function qrToken(): HasOne
    {
        return $this->hasOne(AttendeeQrToken::class);
    }

    public function rsvp(): HasOne
    {
        return $this->hasOne(Rsvp::class);
    }

    public function checkIns(): HasMany
    {
        return $this->hasMany(CheckIn::class);
    }

    public function communicationLogs(): HasMany
    {
        return $this->hasMany(CommunicationLog::class);
    }
}