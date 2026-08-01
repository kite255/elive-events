<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendeeRegistrationAnswer extends Model
{
    protected $fillable = [
        'event_id',
        'attendee_id',
        'event_registration_field_id',
        'answer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function attendee(): BelongsTo
    {
        return $this->belongsTo(Attendee::class);
    }

    public function registrationField(): BelongsTo
    {
        return $this->belongsTo(
            EventRegistrationField::class,
            'event_registration_field_id'
        );
    }
}