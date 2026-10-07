<?php

namespace App\Services\Donations;

use App\Models\Donation;
use App\Models\DonationCampaign;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DonationService
{
    public function __construct(
        protected DonationReferenceService $referenceService
    ) {
    }

    public function createTracked(
        DonationCampaign $campaign,
        array $data
    ): Donation {
        abort_unless(
            $campaign->status === DonationCampaign::STATUS_ACTIVE
            && $campaign->is_public,
            404
        );

        abort_if(
            $campaign->payment_mode === DonationCampaign::PAYMENT_MODE_CLIENT_DIRECT
            && $campaign->direct_payment_behavior === DonationCampaign::DIRECT_BEHAVIOR_DISPLAY_ONLY,
            404
        );

        $amount = (float) $data['amount'];

        if (
            $campaign->minimum_amount !== null
            && $amount < (float) $campaign->minimum_amount
        ) {
            throw ValidationException::withMessages([
                'amount' => 'The donation amount is below the minimum allowed for this campaign.',
            ]);
        }

        $suggestedAmounts = collect($campaign->suggested_amounts ?? [])
            ->map(fn ($value): float => (float) $value);

        if (
            ! $campaign->allow_custom_amount
            && ! $suggestedAmounts->contains(
                fn (float $value): bool => abs($value - $amount) < 0.00001
            )
        ) {
            throw ValidationException::withMessages([
                'amount' => 'Please choose one of the suggested donation amounts.',
            ]);
        }

        return DB::transaction(function () use ($campaign, $data, $amount): Donation {
            return Donation::query()->create([
                'donation_campaign_id' => $campaign->id,
                'reference' => $this->referenceService->generate(),
                'donor_name' => $data['donor_name'] ?? null,
                'donor_phone' => $data['donor_phone'] ?? null,
                'donor_email' => $data['donor_email'] ?? null,
                'amount' => $amount,
                'currency' => $campaign->currency,
                'is_anonymous' => (bool) ($data['is_anonymous'] ?? false),
                'public_display_consent' => (bool) ($data['public_display_consent'] ?? false),
                'payment_type' => $campaign->payment_mode === DonationCampaign::PAYMENT_MODE_PLATFORM
                    ? 'platform'
                    : 'client_direct',
                'status' => Donation::STATUS_PENDING,
            ]);
        });
    }
}
