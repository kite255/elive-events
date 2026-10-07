<?php

namespace App\Services\Donations;

use App\Models\Donation;
use App\Models\DonationCampaign;

class DonationMetricsService
{
    public function forCampaign(
        DonationCampaign $campaign
    ): array {
        if (
            $campaign->payment_mode === DonationCampaign::PAYMENT_MODE_CLIENT_DIRECT
            && $campaign->direct_payment_behavior === DonationCampaign::DIRECT_BEHAVIOR_DISPLAY_ONLY
        ) {
            return [
                'amount_raised' => '0.00',
                'donor_count' => 0,
                'goal_amount' => $campaign->goal_amount !== null
                    ? number_format((float) $campaign->goal_amount, 2, '.', '')
                    : null,
                'percentage' => null,
            ];
        }

        $completed = Donation::query()
            ->where('donation_campaign_id', $campaign->id)
            ->where('status', Donation::STATUS_COMPLETED);

        $amountRaised = (float) (clone $completed)->sum('amount');
        $donorCount = (clone $completed)->count();
        $goalAmount = $campaign->goal_amount !== null
            ? (float) $campaign->goal_amount
            : null;

        $percentage = null;

        if ($goalAmount !== null && $goalAmount > 0) {
            $percentage = round(($amountRaised / $goalAmount) * 100, 2);
        }

        return [
            'amount_raised' => number_format($amountRaised, 2, '.', ''),
            'donor_count' => $donorCount,
            'goal_amount' => $goalAmount !== null
                ? number_format($goalAmount, 2, '.', '')
                : null,
            'percentage' => $percentage,
        ];
    }
}
