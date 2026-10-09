<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DonationPaymentMethod extends Model
{
    protected $fillable = [
        'donation_campaign_id',
        'type',
        'provider_name',
        'account_name',
        'account_number_or_phone',
        'account_identifier_type',
        'instructions',
        'enabled',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'donation_campaign_id' => 'integer',
            'enabled' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(DonationCampaign::class, 'donation_campaign_id');
    }

    public function manualSubmissions(): HasMany
    {
        return $this->hasMany(DonationManualSubmission::class);
    }
}
