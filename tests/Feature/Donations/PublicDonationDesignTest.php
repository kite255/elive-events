<?php

namespace Tests\Feature\Donations;

use App\Models\DonationCampaign;
use App\Models\Event;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicDonationDesignTest extends TestCase
{
    use RefreshDatabase;

    public function test_donation_directory_uses_public_event_navigation_and_campaign_cards(): void
    {
        [$organization, $event] = $this->makeOrganizationAndEvent();

        $campaign = DonationCampaign::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'title' => 'Community Education Fund',
            'description' => 'Help us provide learning materials and scholarships.',
            'banner_image_path' => 'donations/community-education.jpg',
            'status' => DonationCampaign::STATUS_ACTIVE,
            'payment_mode' => DonationCampaign::PAYMENT_MODE_CLIENT_DIRECT,
            'direct_payment_behavior' => DonationCampaign::DIRECT_BEHAVIOR_DISPLAY_ONLY,
            'currency' => 'TZS',
            'is_public' => true,
        ]);

        $response = $this->get(route('public.donations.index'));

        $response->assertOk();
        $response->assertSee('eLive-Logo.png', false);
        $response->assertSee('Home');
        $response->assertSee('Events');
        $response->assertSee('Donations');
        $response->assertSee('Contact');
        $response->assertSee('Support a Campaign');
        $response->assertSee('Community Education Fund');
        $response->assertSee('Public Donation Event');
        $response->assertSee('storage/donations/community-education.jpg', false);
        $response->assertSee('Support Campaign');
        $response->assertSee(route('public.donations.show', ['campaign' => $campaign->slug]), false);
    }

    public function test_events_directory_displays_active_public_support_campaigns(): void
    {
        [$organization, $event] = $this->makeOrganizationAndEvent();

        $visible = DonationCampaign::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'title' => 'Support Our Event',
            'status' => DonationCampaign::STATUS_ACTIVE,
            'payment_mode' => DonationCampaign::PAYMENT_MODE_CLIENT_DIRECT,
            'direct_payment_behavior' => DonationCampaign::DIRECT_BEHAVIOR_DISPLAY_ONLY,
            'currency' => 'TZS',
            'is_public' => true,
        ]);

        DonationCampaign::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Hidden Draft Campaign',
            'status' => DonationCampaign::STATUS_DRAFT,
            'payment_mode' => DonationCampaign::PAYMENT_MODE_CLIENT_DIRECT,
            'direct_payment_behavior' => DonationCampaign::DIRECT_BEHAVIOR_DISPLAY_ONLY,
            'currency' => 'TZS',
            'is_public' => true,
        ]);

        $response = $this->get(route('public.events.index'));

        $response->assertOk();
        $response->assertSee('Support Campaigns');
        $response->assertSee('Support Our Event');
        $response->assertSee('Donation Campaign');
        $response->assertSee(route('public.donations.show', ['campaign' => $visible->slug]), false);
        $response->assertDontSee('Hidden Draft Campaign');
    }

    public function test_donation_detail_uses_the_same_public_navigation(): void
    {
        [$organization, $event] = $this->makeOrganizationAndEvent();

        $campaign = DonationCampaign::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'title' => 'Church Building Fund',
            'description' => 'Help us complete the next phase of construction.',
            'banner_image_path' => 'donations/church-building.jpg',
            'status' => DonationCampaign::STATUS_ACTIVE,
            'payment_mode' => DonationCampaign::PAYMENT_MODE_CLIENT_DIRECT,
            'direct_payment_behavior' => DonationCampaign::DIRECT_BEHAVIOR_DISPLAY_ONLY,
            'currency' => 'TZS',
            'is_public' => true,
        ]);

        $response = $this->get(route('public.donations.show', ['campaign' => $campaign->slug]));

        $response->assertOk();
        $response->assertSee('eLive-Logo.png', false);
        $response->assertSee('Home');
        $response->assertSee('Events');
        $response->assertSee('Donations');
        $response->assertSee('Contact');
        $response->assertSee('Church Building Fund');
        $response->assertSee('storage/donations/church-building.jpg', false);
        $response->assertSee('Back to campaigns');
    }

    private function makeOrganizationAndEvent(): array
    {
        $organization = Organization::query()->create([
            'name' => 'Public Donation Organization',
            'slug' => 'public-donation-organization-' . uniqid(),
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Public Donation Event',
            'slug' => 'public-donation-event-' . uniqid(),
            'status' => Event::STATUS_ACTIVE,
            'starts_at' => now()->addDay(),
        ]);

        return [$organization, $event];
    }
}
