<?php

namespace App\Http\Controllers;

use App\Models\DonationCampaign;
use App\Services\Donations\DonationMetricsService;
use Illuminate\Contracts\View\View;

class PublicDonationCampaignController extends Controller
{
    public function index(): View
    {
        $campaigns = DonationCampaign::query()
            ->publicActive()
            ->with('organization')
            ->latest('id')
            ->paginate(12);

        return view('public.donations.index', [
            'campaigns' => $campaigns,
        ]);
    }

    public function show(string $campaign, DonationMetricsService $metricsService): View
    {
        $campaign = DonationCampaign::query()
            ->publicActive()
            ->where('slug', $campaign)
            ->with([
                'organization',
                'paymentMethods' => fn ($query) => $query
                    ->where('enabled', true)
                    ->orderBy('sort_order')
                    ->orderBy('id'),
            ])
            ->firstOrFail();

        return view('public.donations.show', [
            'campaign' => $campaign,
            'metrics' => $metricsService->forCampaign($campaign),
        ]);
    }
}
