<?php

namespace Tests\Feature\Donations;

use App\Models\Donation;
use App\Models\DonationCampaign;
use App\Models\Organization;
use App\Services\Donations\DonationReferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DonationCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tracked_campaign_creates_donation_with_secure_reference_and_public_token(): void
    {
        $campaign = $this->makeCampaign([
            'minimum_amount' => 10000,
            'suggested_amounts' => [10000, 25000, 50000],
            'allow_custom_amount' => true,
        ]);

        $response = $this->post(
            route('public.donations.store', ['campaign' => $campaign->slug]),
            [
                'donor_name' => 'Jane Donor',
                'donor_phone' => '255700000001',
                'donor_email' => 'jane@example.com',
                'amount' => 25000,
                'is_anonymous' => true,
                'public_display_consent' => false,
            ]
        );

        $donation = Donation::query()->first();

        $this->assertNotNull($donation);
        $this->assertMatchesRegularExpression('/^ELV-DON-[A-Z0-9]{6}$/', $donation->reference);
        $this->assertSame(48, strlen($donation->public_token));
        $this->assertSame(Donation::STATUS_PENDING, $donation->status);
        $this->assertSame('client_direct', $donation->payment_type);
        $this->assertSame('Jane Donor', $donation->donor_name);
        $this->assertTrue($donation->is_anonymous);
        $this->assertFalse($donation->public_display_consent);

        $response->assertRedirect(
            route('public.donations.show', ['campaign' => $campaign->slug])
        );
    }

    public function test_amount_below_campaign_minimum_is_rejected(): void
    {
        $campaign = $this->makeCampaign([
            'minimum_amount' => 10000,
            'allow_custom_amount' => true,
        ]);

        $response = $this->from(
            route('public.donations.show', ['campaign' => $campaign->slug])
        )->post(
            route('public.donations.store', ['campaign' => $campaign->slug]),
            [
                'donor_name' => 'Low Donor',
                'donor_phone' => '255700000002',
                'amount' => 5000,
            ]
        );

        $response->assertRedirect(
            route('public.donations.show', ['campaign' => $campaign->slug])
        );
        $response->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('donations', 0);
    }

    public function test_custom_amount_is_rejected_when_disabled_unless_it_matches_suggested_amount(): void
    {
        $campaign = $this->makeCampaign([
            'minimum_amount' => 5000,
            'suggested_amounts' => [10000, 25000],
            'allow_custom_amount' => false,
        ]);

        $invalid = $this->post(
            route('public.donations.store', ['campaign' => $campaign->slug]),
            [
                'donor_name' => 'Custom Donor',
                'donor_phone' => '255700000003',
                'amount' => 12000,
            ]
        );

        $invalid->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('donations', 0);

        $valid = $this->post(
            route('public.donations.store', ['campaign' => $campaign->slug]),
            [
                'donor_name' => 'Suggested Donor',
                'donor_phone' => '255700000004',
                'amount' => 10000,
            ]
        );

        $valid->assertRedirect();
        $this->assertDatabaseHas('donations', [
            'donation_campaign_id' => $campaign->id,
            'donor_name' => 'Suggested Donor',
            'amount' => '10000.00',
        ]);
    }

    public function test_display_only_campaign_cannot_create_donation_records(): void
    {
        $campaign = $this->makeCampaign([
            'direct_payment_behavior' => DonationCampaign::DIRECT_BEHAVIOR_DISPLAY_ONLY,
        ]);

        $this->post(
            route('public.donations.store', ['campaign' => $campaign->slug]),
            [
                'donor_name' => 'Should Not Exist',
                'donor_phone' => '255700000005',
                'amount' => 10000,
            ]
        )->assertNotFound();

        $this->assertDatabaseCount('donations', 0);
    }

    public function test_reference_service_generates_unique_human_references(): void
    {
        $service = app(DonationReferenceService::class);

        $first = $service->generate();
        $second = $service->generate();

        $this->assertMatchesRegularExpression('/^ELV-DON-[A-Z0-9]{6}$/', $first);
        $this->assertMatchesRegularExpression('/^ELV-DON-[A-Z0-9]{6}$/', $second);
        $this->assertNotSame($first, $second);
    }

    private function makeCampaign(array $overrides = []): DonationCampaign
    {
        $organization = Organization::query()->create([
            'name' => 'Tracked Donation Organization',
            'slug' => 'tracked-donation-organization-' . uniqid(),
        ]);

        return DonationCampaign::query()->create(array_merge([
            'organization_id' => $organization->id,
            'title' => 'Tracked Donation Campaign',
            'status' => DonationCampaign::STATUS_ACTIVE,
            'payment_mode' => DonationCampaign::PAYMENT_MODE_CLIENT_DIRECT,
            'direct_payment_behavior' => DonationCampaign::DIRECT_BEHAVIOR_TRACKED,
            'currency' => 'TZS',
            'minimum_amount' => 5000,
            'suggested_amounts' => [10000, 25000, 50000],
            'allow_custom_amount' => true,
            'is_public' => true,
        ], $overrides));
    }
}
