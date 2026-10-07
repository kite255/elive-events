<?php

namespace Tests\Feature\Donations;

use App\Models\DonationCampaign;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DonationCampaignGalleryTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_gallery_column_exists_and_is_cast_to_array(): void
    {
        $this->assertTrue(Schema::hasColumn('donation_campaigns', 'gallery_image_paths'));

        $organization = Organization::query()->create([
            'name' => 'Gallery Organization',
            'slug' => 'gallery-organization-' . uniqid(),
        ]);

        $campaign = DonationCampaign::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Children Health Festival',
            'status' => DonationCampaign::STATUS_ACTIVE,
            'payment_mode' => DonationCampaign::PAYMENT_MODE_CLIENT_DIRECT,
            'direct_payment_behavior' => DonationCampaign::DIRECT_BEHAVIOR_DISPLAY_ONLY,
            'currency' => 'TZS',
            'is_public' => true,
            'gallery_image_paths' => [
                'donations/campaigns/poster-one.jpg',
                'donations/campaigns/poster-two.jpg',
            ],
        ]);

        $campaign->refresh();

        $this->assertSame([
            'donations/campaigns/poster-one.jpg',
            'donations/campaigns/poster-two.jpg',
        ], $campaign->gallery_image_paths);
    }

    public function test_public_campaign_page_renders_gallery_images(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Gallery Organization',
            'slug' => 'gallery-organization-' . uniqid(),
        ]);

        $campaign = DonationCampaign::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Support the Children Health Festival',
            'status' => DonationCampaign::STATUS_ACTIVE,
            'payment_mode' => DonationCampaign::PAYMENT_MODE_CLIENT_DIRECT,
            'direct_payment_behavior' => DonationCampaign::DIRECT_BEHAVIOR_DISPLAY_ONLY,
            'currency' => 'TZS',
            'is_public' => true,
            'gallery_image_paths' => [
                'donations/campaigns/support-poster.jpg',
                'donations/campaigns/wishlist-poster.jpg',
            ],
        ]);

        $response = $this->get(route('public.donations.show', ['campaign' => $campaign->slug]));

        $response->assertOk();
        $response->assertSee('Campaign Gallery');
        $response->assertSee('storage/donations/campaigns/support-poster.jpg', false);
        $response->assertSee('storage/donations/campaigns/wishlist-poster.jpg', false);
    }
}
