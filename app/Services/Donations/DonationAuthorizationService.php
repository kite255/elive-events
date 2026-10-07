<?php

namespace App\Services\Donations;

use App\Models\Donation;
use App\Models\DonationCampaign;
use App\Models\User;
use Illuminate\Support\Collection;

class DonationAuthorizationService
{
    public function canManageCampaign(
        User $user,
        DonationCampaign $campaign
    ): bool {
        return $campaign->canBeManagedBy($user);
    }

    public function canVerifyDonation(
        User $user,
        Donation $donation
    ): bool {
        $donation->loadMissing('campaign');

        return $donation->campaign !== null
            && $this->canManageCampaign(
                $user,
                $donation->campaign
            );
    }

    public function accessibleCampaignIds(
        User $user
    ): Collection {
        return DonationCampaign::query()
            ->accessibleBy($user)
            ->pluck('id');
    }
}
