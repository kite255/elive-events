<?php

namespace App\Http\Controllers;

use App\Http\Requests\Donations\StoreDonationRequest;
use App\Models\DonationCampaign;
use App\Services\Donations\DonationService;
use Illuminate\Http\RedirectResponse;

class PublicDonationController extends Controller
{
    public function __construct(
        protected DonationService $donationService
    ) {
    }

    public function store(
        StoreDonationRequest $request,
        string $campaign
    ): RedirectResponse {
        $campaign = DonationCampaign::query()
            ->publicActive()
            ->where('slug', $campaign)
            ->firstOrFail();

        $this->donationService->createTracked(
            $campaign,
            $request->validated()
        );

        return redirect()
            ->route('public.donations.show', [
                'campaign' => $campaign->slug,
            ])
            ->with('status', 'Donation details received.');
    }
}
