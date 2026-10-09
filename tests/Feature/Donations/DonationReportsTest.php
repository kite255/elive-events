<?php

namespace Tests\Feature\Donations;

use App\Models\Donation;
use App\Models\DonationCampaign;
use App\Models\Event;
use App\Models\Organization;
use App\Services\Donations\DonationReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DonationReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_summary_counts_only_completed_donations_in_totals(): void
    {
        [$organization, $event, $campaign] = $this->makeCampaign();

        $this->donation($campaign, Donation::STATUS_COMPLETED, 10000, 'platform');
        $this->donation($campaign, Donation::STATUS_COMPLETED, 15000, 'client_direct');
        $this->donation($campaign, Donation::STATUS_AWAITING_VERIFICATION, 50000, 'client_direct');
        $this->donation($campaign, Donation::STATUS_PENDING, 70000, 'client_direct');
        $this->donation($campaign, Donation::STATUS_FAILED, 90000, 'platform');

        $summary = app(DonationReportService::class)
            ->summaryForCampaign($campaign);

        $this->assertSame('25000.00', $summary['completed_amount']);
        $this->assertSame(2, $summary['completed_count']);
        $this->assertSame(1, $summary['awaiting_verification_count']);
        $this->assertSame('10000.00', $summary['by_payment_type']['platform']['amount']);
        $this->assertSame(1, $summary['by_payment_type']['platform']['count']);
        $this->assertSame('15000.00', $summary['by_payment_type']['client_direct']['amount']);
        $this->assertSame(1, $summary['by_payment_type']['client_direct']['count']);
    }

    public function test_display_only_campaign_is_excluded_from_tracked_totals(): void
    {
        [$organization, $event, $campaign] = $this->makeCampaign([
            'payment_mode' => DonationCampaign::PAYMENT_MODE_CLIENT_DIRECT,
            'direct_payment_behavior' => DonationCampaign::DIRECT_BEHAVIOR_DISPLAY_ONLY,
        ]);

        // Historical/accidental rows must not make display-only campaigns look tracked.
        $this->donation($campaign, Donation::STATUS_COMPLETED, 80000, 'client_direct');

        $summary = app(DonationReportService::class)
            ->summaryForCampaign($campaign);

        $this->assertSame('0.00', $summary['completed_amount']);
        $this->assertSame(0, $summary['completed_count']);
        $this->assertSame(0, $summary['awaiting_verification_count']);
    }

    public function test_organization_summary_is_scoped_to_that_organization_and_optional_event(): void
    {
        [$organization, $eventA, $campaignA] = $this->makeCampaign();

        $eventB = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Second Donation Event',
            'slug' => 'second-donation-event-' . uniqid(),
            'status' => Event::STATUS_ACTIVE,
        ]);

        $campaignB = DonationCampaign::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $eventB->id,
            'title' => 'Second Donation Campaign',
            'status' => DonationCampaign::STATUS_ACTIVE,
            'payment_mode' => DonationCampaign::PAYMENT_MODE_PLATFORM,
            'currency' => 'TZS',
            'is_public' => true,
        ]);

        $otherOrganization = Organization::query()->create([
            'name' => 'Other Report Organization',
            'slug' => 'other-report-organization-' . uniqid(),
        ]);

        $otherCampaign = DonationCampaign::query()->create([
            'organization_id' => $otherOrganization->id,
            'title' => 'Other Organization Campaign',
            'status' => DonationCampaign::STATUS_ACTIVE,
            'payment_mode' => DonationCampaign::PAYMENT_MODE_PLATFORM,
            'currency' => 'TZS',
            'is_public' => true,
        ]);

        $this->donation($campaignA, Donation::STATUS_COMPLETED, 10000, 'platform');
        $this->donation($campaignB, Donation::STATUS_COMPLETED, 20000, 'platform');
        $this->donation($otherCampaign, Donation::STATUS_COMPLETED, 999999, 'platform');

        $service = app(DonationReportService::class);

        $organizationSummary = $service->summaryForOrganization($organization);

        $this->assertSame('30000.00', $organizationSummary['completed_amount']);
        $this->assertSame(2, $organizationSummary['completed_count']);

        $eventSummary = $service->summaryForOrganization(
            $organization,
            ['event_id' => $eventA->id]
        );

        $this->assertSame('10000.00', $eventSummary['completed_amount']);
        $this->assertSame(1, $eventSummary['completed_count']);
    }

    private function makeCampaign(array $overrides = []): array
    {
        $organization = Organization::query()->create([
            'name' => 'Donation Report Organization',
            'slug' => 'donation-report-organization-' . uniqid(),
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Donation Report Event',
            'slug' => 'donation-report-event-' . uniqid(),
            'status' => Event::STATUS_ACTIVE,
        ]);

        $campaign = DonationCampaign::query()->create(array_merge([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'title' => 'Donation Report Campaign',
            'status' => DonationCampaign::STATUS_ACTIVE,
            'payment_mode' => DonationCampaign::PAYMENT_MODE_HYBRID,
            'direct_payment_behavior' => DonationCampaign::DIRECT_BEHAVIOR_TRACKED,
            'currency' => 'TZS',
            'is_public' => true,
        ], $overrides));

        return [$organization, $event, $campaign];
    }

    private function donation(
        DonationCampaign $campaign,
        string $status,
        float $amount,
        string $paymentType
    ): Donation {
        return Donation::query()->create([
            'donation_campaign_id' => $campaign->id,
            'reference' => 'ELV-DON-' . strtoupper(substr(md5(uniqid()), 0, 6)),
            'amount' => $amount,
            'currency' => 'TZS',
            'payment_type' => $paymentType,
            'status' => $status,
            'completed_at' => $status === Donation::STATUS_COMPLETED ? now() : null,
        ]);
    }
}
