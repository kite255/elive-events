<?php

namespace App\Services\Donations;

use App\Models\Donation;
use App\Models\DonationCampaign;
use App\Models\Organization;

class DonationReportService
{
    public function summaryForCampaign(
        DonationCampaign $campaign
    ): array {
        if (
            $campaign->payment_mode === DonationCampaign::PAYMENT_MODE_CLIENT_DIRECT
            && $campaign->direct_payment_behavior === DonationCampaign::DIRECT_BEHAVIOR_DISPLAY_ONLY
        ) {
            return $this->emptySummary();
        }

        $base = Donation::query()
            ->where('donation_campaign_id', $campaign->id);

        return $this->summarize($base);
    }

    public function summaryForOrganization(
        Organization $organization,
        array $filters = []
    ): array {
        $campaignIds = DonationCampaign::query()
            ->where('organization_id', $organization->id)
            ->where(function ($query): void {
                $query
                    ->where('payment_mode', '!=', DonationCampaign::PAYMENT_MODE_CLIENT_DIRECT)
                    ->orWhere('direct_payment_behavior', '!=', DonationCampaign::DIRECT_BEHAVIOR_DISPLAY_ONLY)
                    ->orWhereNull('direct_payment_behavior');
            });

        if (! empty($filters['event_id'])) {
            $campaignIds->where('event_id', (int) $filters['event_id']);
        }

        $ids = $campaignIds->pluck('id');

        if ($ids->isEmpty()) {
            return $this->emptySummary();
        }

        $base = Donation::query()
            ->whereIn('donation_campaign_id', $ids);

        return $this->summarize($base);
    }

    private function summarize($query): array
    {
        $completed = (clone $query)
            ->where('status', Donation::STATUS_COMPLETED);

        $completedAmount = (float) (clone $completed)->sum('amount');
        $completedCount = (clone $completed)->count();

        $awaitingVerificationCount = (clone $query)
            ->where('status', Donation::STATUS_AWAITING_VERIFICATION)
            ->count();

        $byPaymentType = [];

        foreach (['platform', 'client_direct'] as $type) {
            $typed = (clone $completed)
                ->where('payment_type', $type);

            $byPaymentType[$type] = [
                'amount' => number_format(
                    (float) (clone $typed)->sum('amount'),
                    2,
                    '.',
                    ''
                ),
                'count' => (clone $typed)->count(),
            ];
        }

        return [
            'completed_amount' => number_format(
                $completedAmount,
                2,
                '.',
                ''
            ),
            'completed_count' => $completedCount,
            'awaiting_verification_count' => $awaitingVerificationCount,
            'by_payment_type' => $byPaymentType,
        ];
    }

    private function emptySummary(): array
    {
        return [
            'completed_amount' => '0.00',
            'completed_count' => 0,
            'awaiting_verification_count' => 0,
            'by_payment_type' => [
                'platform' => [
                    'amount' => '0.00',
                    'count' => 0,
                ],
                'client_direct' => [
                    'amount' => '0.00',
                    'count' => 0,
                ],
            ],
        ];
    }
}
