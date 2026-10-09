<?php

namespace Tests\Feature\Donations;

use App\Models\Donation;
use App\Models\DonationCampaign;
use App\Models\Organization;
use App\Models\User;
use App\Services\Donations\DonationAuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DonationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_manage_and_verify_any_donation_campaign(): void
    {
        [$organization, $campaign, $donation] = $this->makeCampaignWithDonation();

        $user = User::factory()->create([
            'is_super_admin' => true,
        ]);

        $service = app(DonationAuthorizationService::class);

        $this->assertTrue($service->canManageCampaign($user, $campaign));
        $this->assertTrue($service->canVerifyDonation($user, $donation));
        $this->assertContains($campaign->id, $service->accessibleCampaignIds($user)->all());
    }

    public function test_organization_owner_and_admin_can_manage_campaigns_in_their_organization(): void
    {
        [$organization, $campaign, $donation] = $this->makeCampaignWithDonation();

        $owner = User::factory()->create();
        $admin = User::factory()->create();

        $organization->attachUser($owner, User::ORGANIZATION_ROLE_OWNER, true);
        $organization->attachUser($admin, User::ORGANIZATION_ROLE_ADMIN);

        $service = app(DonationAuthorizationService::class);

        foreach ([$owner, $admin] as $user) {
            $this->assertTrue($service->canManageCampaign($user, $campaign));
            $this->assertTrue($service->canVerifyDonation($user, $donation));
            $this->assertContains($campaign->id, $service->accessibleCampaignIds($user)->all());
        }
    }

    public function test_organization_member_without_management_role_cannot_manage_campaign(): void
    {
        [$organization, $campaign, $donation] = $this->makeCampaignWithDonation();

        $member = User::factory()->create();
        $organization->attachUser($member, User::ORGANIZATION_ROLE_MEMBER);

        $service = app(DonationAuthorizationService::class);

        $this->assertFalse($service->canManageCampaign($member, $campaign));
        $this->assertFalse($service->canVerifyDonation($member, $donation));
        $this->assertNotContains($campaign->id, $service->accessibleCampaignIds($member)->all());
    }

    public function test_unrelated_user_cannot_access_campaign_or_donation_even_by_numeric_id(): void
    {
        [$organization, $campaign, $donation] = $this->makeCampaignWithDonation();

        $otherOrganization = Organization::query()->create([
            'name' => 'Unrelated Organization',
            'slug' => 'unrelated-organization-' . uniqid(),
        ]);

        $user = User::factory()->create();
        $otherOrganization->attachUser($user, User::ORGANIZATION_ROLE_ADMIN);

        $service = app(DonationAuthorizationService::class);

        $this->assertFalse($service->canManageCampaign($user, $campaign));
        $this->assertFalse($service->canVerifyDonation($user, $donation));
        $this->assertNotContains($campaign->id, $service->accessibleCampaignIds($user)->all());

        $this->assertNull(
            DonationCampaign::query()
                ->accessibleBy($user)
                ->whereKey($campaign->id)
                ->first()
        );

        $this->assertNull(
            Donation::query()
                ->accessibleBy($user)
                ->whereKey($donation->id)
                ->first()
        );
    }

    private function makeCampaignWithDonation(): array
    {
        $organization = Organization::query()->create([
            'name' => 'Donation Authorization Organization',
            'slug' => 'donation-auth-organization-' . uniqid(),
        ]);

        $campaign = DonationCampaign::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Donation Authorization Campaign',
            'status' => DonationCampaign::STATUS_ACTIVE,
            'payment_mode' => DonationCampaign::PAYMENT_MODE_CLIENT_DIRECT,
            'direct_payment_behavior' => DonationCampaign::DIRECT_BEHAVIOR_TRACKED,
            'currency' => 'TZS',
        ]);

        $donation = Donation::query()->create([
            'donation_campaign_id' => $campaign->id,
            'reference' => 'ELV-DON-AUTH-' . uniqid(),
            'donor_name' => 'Authorization Donor',
            'amount' => 10000,
            'currency' => 'TZS',
            'payment_type' => 'client_direct',
            'status' => Donation::STATUS_AWAITING_VERIFICATION,
        ]);

        return [$organization, $campaign, $donation];
    }
}
