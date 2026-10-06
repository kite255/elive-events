<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DonationManualSubmission extends Model
{
    protected $fillable = [
        'donation_id',
        'donation_payment_method_id',
        'transaction_reference',
        'proof_path',
        'submitted_at',
        'verified_by',
        'verified_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'donation_id' => 'integer',
            'donation_payment_method_id' => 'integer',
            'submitted_at' => 'datetime',
            'verified_by' => 'integer',
            'verified_at' => 'datetime',
        ];
    }

    public function donation(): BelongsTo
    {
        return $this->belongsTo(Donation::class);
    }

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(DonationPaymentMethod::class, 'donation_payment_method_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
