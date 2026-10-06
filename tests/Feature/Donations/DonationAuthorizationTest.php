<?php

namespace Tests\Feature\Donations;

use App\Models\Donation;
use App\Models\DonationCampaign;
use App\Models\Event;
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
        [$organization, $event, $campaign, $donation] = $this->makeCampaignWithDonation();

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
        [$organization, $event, $campaign, $donation] = $this->makeCampaignWithDonation();

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

    public function test_assigned_event_manager_can_manage_only_campaigns_linked_to_assigned_events(): void
    {
        [$organization, $event, $campaign, $donation] = $this->makeCampaignWithDonation();

        $otherEvent = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Other Event',
            'slug' => 'other-event-' . uniqid(),
            'status' => Event::STATUS_ACTIVE,
        ]);

        $otherCampaign = DonationCampaign::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $otherEvent->id,
            'title' => 'Other Event Campaign',
            'status' => DonationCampaign::STATUS_ACTIVE,
            'payment_mode' => DonationCampaign::PAYMENT_MODE_CLIENT_DIRECT,
            'direct_payment_behavior' => DonationCampaign::DIRECT_BEHAVIOR_TRACKED,
            'currency' => 'TZS',
        ]);

        $manager = User::factory()->create();

        $organization->attachUser($manager, User::ORGANIZATION_ROLE_MEMBER);
        $event->assignUser($manager, User::ORGANIZATION_ROLE_EVENT_MANAGER);

        $service = app(DonationAuthorizationService::class);

        $this->assertTrue($service->canManageCampaign($manager, $campaign));
        $this->assertTrue($service->canVerifyDonation($manager, $donation));
        $this->assertFalse($service->canManageCampaign($manager, $otherCampaign));

        $accessibleIds = $service->accessibleCampaignIds($manager)->all();

        $this->assertContains($campaign->id, $accessibleIds);
        $this->assertNotContains($otherCampaign->id, $accessibleIds);
    }

    public function test_unrelated_user_cannot_access_campaign_or_donation_even_by_numeric_id(): void
    {
        [$organization, $event, $campaign, $donation] = $this->makeCampaignWithDonation();

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

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Donation Authorization Event',
            'slug' => 'donation-auth-event-' . uniqid(),
            'status' => Event::STATUS_ACTIVE,
        ]);

        $campaign = DonationCampaign::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
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

        return [$organization, $event, $campaign, $donation];
    }
}
