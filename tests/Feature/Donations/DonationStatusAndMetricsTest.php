<?php

namespace Tests\Feature\Donations;

use App\Models\Donation;
use App\Models\DonationCampaign;
use App\Models\Organization;
use App\Services\Donations\DonationMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DonationStatusAndMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_page_is_accessible_only_by_opaque_public_token(): void
    {
        [$campaign, $donation] = $this->makeCampaignAndDonation([
            'status' => Donation::STATUS_AWAITING_PAYMENT,
        ]);

        $response = $this->get(
            route('public.donations.status', ['token' => $donation->public_token])
        );

        $response->assertOk();
        $response->assertSee($donation->reference);
        $response->assertSee('Awaiting Payment');

        $this->get('/donations/status/' . $donation->id)
            ->assertNotFound();
    }

    public function test_status_page_does_not_expose_private_contact_or_manual_proof_details(): void
    {
        [$campaign, $donation] = $this->makeCampaignAndDonation([
            'donor_name' => 'Private Donor',
            'donor_phone' => '255700123456',
            'donor_email' => 'private@example.com',
            'status' => Donation::STATUS_AWAITING_VERIFICATION,
        ]);

        $response = $this->get(
            route('public.donations.status', ['token' => $donation->public_token])
        );

        $response->assertOk();
        $response->assertDontSee('255700123456');
        $response->assertDontSee('private@example.com');
        $response->assertDontSee('proof_path');
        $response->assertDontSee('transaction_reference');
    }

    public function test_metrics_count_only_completed_donations(): void
    {
        [$campaign] = $this->makeCampaignAndDonation([
            'status' => Donation::STATUS_COMPLETED,
            'amount' => 10000,
            'completed_at' => now(),
        ], [
            'goal_amount' => 100000,
            'show_goal' => true,
            'show_amount_raised' => true,
            'show_percentage' => true,
            'show_donor_count' => true,
        ]);

        foreach ([
            Donation::STATUS_PENDING,
            Donation::STATUS_AWAITING_PAYMENT,
            Donation::STATUS_AWAITING_VERIFICATION,
            Donation::STATUS_FAILED,
            Donation::STATUS_REJECTED,
            Donation::STATUS_CANCELLED,
        ] as $status) {
            Donation::query()->create([
                'donation_campaign_id' => $campaign->id,
                'reference' => 'ELV-DON-' . strtoupper(substr(md5($status . uniqid()), 0, 6)),
                'amount' => 50000,
                'currency' => 'TZS',
                'payment_type' => 'client_direct',
                'status' => $status,
            ]);
        }

        Donation::query()->create([
            'donation_campaign_id' => $campaign->id,
            'reference' => 'ELV-DON-' . strtoupper(substr(md5('completed-2' . uniqid()), 0, 6)),
            'amount' => 15000,
            'currency' => 'TZS',
            'payment_type' => 'client_direct',
            'status' => Donation::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        $metrics = app(DonationMetricsService::class)->forCampaign($campaign);

        $this->assertSame('25000.00', $metrics['amount_raised']);
        $this->assertSame(2, $metrics['donor_count']);
        $this->assertSame('100000.00', $metrics['goal_amount']);
        $this->assertSame(25.0, $metrics['percentage']);
    }

    public function test_public_campaign_shows_only_enabled_progress_metrics(): void
    {
        [$campaign] = $this->makeCampaignAndDonation([
            'status' => Donation::STATUS_COMPLETED,
            'amount' => 25000,
            'completed_at' => now(),
        ], [
            'goal_amount' => 100000,
            'show_goal' => false,
            'show_amount_raised' => true,
            'show_percentage' => false,
            'show_donor_count' => true,
        ]);

        $response = $this->get(
            route('public.donations.show', ['campaign' => $campaign->slug])
        );

        $response->assertOk();
        $response->assertSee('Amount Raised');
        $response->assertSee('TZS 25,000');
        $response->assertSee('Donors');
        $response->assertSee('1');
        $response->assertDontSee('Goal');
        $response->assertDontSee('25%');
    }

    public function test_display_only_campaign_never_shows_system_derived_progress(): void
    {
        [$campaign] = $this->makeCampaignAndDonation(
            [
                'status' => Donation::STATUS_COMPLETED,
                'amount' => 40000,
                'completed_at' => now(),
            ],
            [
                'payment_mode' => DonationCampaign::PAYMENT_MODE_CLIENT_DIRECT,
                'direct_payment_behavior' => DonationCampaign::DIRECT_BEHAVIOR_DISPLAY_ONLY,
                'goal_amount' => 100000,
                'show_goal' => true,
                'show_amount_raised' => true,
                'show_percentage' => true,
                'show_donor_count' => true,
            ]
        );

        $response = $this->get(
            route('public.donations.show', ['campaign' => $campaign->slug])
        );

        $response->assertOk();
        $response->assertDontSee('Amount Raised');
        $response->assertDontSee('Donors');
        $response->assertDontSee('40,000');
        $response->assertDontSee('40%');
    }

    private function makeCampaignAndDonation(
        array $donationOverrides = [],
        array $campaignOverrides = []
    ): array {
        $organization = Organization::query()->create([
            'name' => 'Donation Status Organization',
            'slug' => 'donation-status-organization-' . uniqid(),
        ]);

        $campaign = DonationCampaign::query()->create(array_merge([
            'organization_id' => $organization->id,
            'title' => 'Donation Status Campaign',
            'status' => DonationCampaign::STATUS_ACTIVE,
            'payment_mode' => DonationCampaign::PAYMENT_MODE_CLIENT_DIRECT,
            'direct_payment_behavior' => DonationCampaign::DIRECT_BEHAVIOR_TRACKED,
            'currency' => 'TZS',
            'is_public' => true,
        ], $campaignOverrides));

        $donation = Donation::query()->create(array_merge([
            'donation_campaign_id' => $campaign->id,
            'reference' => 'ELV-DON-' . strtoupper(substr(md5(uniqid()), 0, 6)),
            'donor_name' => 'Status Donor',
            'amount' => 10000,
            'currency' => 'TZS',
            'payment_type' => 'client_direct',
            'status' => Donation::STATUS_PENDING,
        ], $donationOverrides));

        return [$campaign, $donation];
    }
}
