<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    public const TYPE_ORDER_CREATED = 'order_created';
    public const TYPE_PAYMENT_STATUS = 'payment_status';
    public const TYPE_IPN = 'ipn';
    public const TYPE_REFUND = 'refund';
    public const TYPE_MANUAL_ADJUSTMENT = 'manual_adjustment';

    protected $fillable = [
        'payment_id',
        'type',
        'provider_reference',
        'request_payload',
        'response_payload',
        'status',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'request_payload' => 'array',
            'response_payload' => 'array',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
