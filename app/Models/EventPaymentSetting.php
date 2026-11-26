<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventPaymentSetting extends Model
{
    public const GATEWAY_FEE_BEARER_ORGANIZER = 'organizer';
    public const GATEWAY_FEE_BEARER_ELIVE = 'elive';
    public const GATEWAY_FEE_BEARER_CUSTOMER = 'customer';

    protected $fillable = [
        'event_id',
        'payments_enabled',
        'currency',
        'registration_fee',

        'platform_commission_rate',
        'gateway_fee_rate',
        'gateway_fee_bearer',

        'payment_required_before_confirmation',
        'payment_required_before_badge',
        'block_check_in_if_unpaid',
        'allow_manual_payment',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $setting): void {
            $user = auth()->user();

            // Trusted unauthenticated system jobs and console operations retain
            // their existing behavior. Authenticated organizers cannot set
            // platform fees, even through a manipulated form submission.
            if ($user === null || ($user instanceof User && $user->isSuperAdmin())) {
                return;
            }

            if ($setting->isDirty([
                'platform_commission_rate',
                'gateway_fee_rate',
                'gateway_fee_bearer',
            ])) {
                throw new \Illuminate\Auth\Access\AuthorizationException(
                    'Only eLive platform administrators may change platform fees.'
                );
            }
        });
    }

    protected function casts(): array
    {
        return [
            'payments_enabled' => 'boolean',
            'registration_fee' => 'decimal:2',

            'platform_commission_rate' => 'decimal:2',
            'gateway_fee_rate' => 'decimal:2',

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