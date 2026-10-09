<?php

namespace App\Http\Controllers;

use App\Models\Donation;
use Illuminate\Contracts\View\View;

class PublicDonationStatusController extends Controller
{
    public function show(string $token): View
    {
        $donation = Donation::query()
            ->where('public_token', $token)
            ->with('campaign.organization')
            ->firstOrFail();

        return view('public.donations.status', [
            'donation' => $donation,
        ]);
    }
}
