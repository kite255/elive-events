<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventPaymentSetting extends Model
{
    protected $fillable = [
        'event_id',
        'payments_enabled',
        'currency',
        'registration_fee',
        'payment_required_before_confirmation',
        'payment_required_before_badge',
        'block_check_in_if_unpaid',
        'allow_manual_payment',
    ];

    protected function casts(): array
    {
        return [
            'payments_enabled' => 'boolean',
            'registration_fee' => 'decimal:2',
            'payment_required_before_confirmation' => 'boolean',
            'payment_required_before_badge' => 'boolean',
            'block_check_in_if_unpaid' => 'boolean',
            'allow_manual_payment' => 'boolean',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
