<?php

namespace Tests\Feature\Donations;

use App\Models\DonationCampaign;
use App\Models\DonationPaymentMethod;
use App\Models\Event;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventEmbeddedDonationCampaignTest extends TestCase
{
    use RefreshDatabase;

    public function test_linked_campaign_is_embedded_on_event_page(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Serunt Nutrition',
            'slug' => 'serunt-nutrition-' . uniqid(),
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Children Health Festival',
            'slug' => 'children-health-festival-' . uniqid(),
            'status' => Event::STATUS_ACTIVE,
            'starts_at' => now()->addMonth(),
            'venue' => 'Magomeni Garden',
            'registration_is_open' => false,
        ]);

        $campaign = DonationCampaign::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'title' => 'Support the Children Health Festival',
            'description' => 'Help provide meals, health supplies and learning materials.',
            'banner_image_path' => 'donations/children-health.jpg',
            'gallery_image_paths' => [
                'donations/campaigns/gallery/wishlist.jpg',
            ],
            'status' => DonationCampaign::STATUS_ACTIVE,
            'payment_mode' => DonationCampaign::PAYMENT_MODE_CLIENT_DIRECT,
            'direct_payment_behavior' => DonationCampaign::DIRECT_BEHAVIOR_DISPLAY_ONLY,
            'currency' => 'TZS',
            'is_public' => true,
        ]);

        DonationPaymentMethod::query()->create([
            'donation_campaign_id' => $campaign->id,
            'type' => 'bank',
            'provider_name' => 'CRDB Bank',
            'account_name' => 'SERUNT NUTRITION',
            'account_number_or_phone' => '10475831891',
            'instructions' => 'Lipa Number: 101709606',
            'enabled' => true,
            'sort_order' => 1,
        ]);

        $response = $this->get(route('public.events.show', [
            'event' => $event->slug,
        ]));

        $response->assertOk();
        $response->assertSee('Support This Event');
        $response->assertSee('Support the Children Health Festival');
        $response->assertSee('Help provide meals, health supplies and learning materials.');
        $response->assertSee('CRDB Bank');
        $response->assertSee('SERUNT NUTRITION');
        $response->assertSee('10475831891');
        $response->assertSee('Lipa Number: 101709606');
        $response->assertSee('storage/donations/campaigns/gallery/wishlist.jpg', false);
        $response->assertDontSee('Support this event</a>', false);
    }

    public function test_events_directory_does_not_render_a_separate_support_campaigns_section(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Simple Events Organization',
            'slug' => 'simple-events-' . uniqid(),
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Simple Public Event',
            'slug' => 'simple-public-event-' . uniqid(),
            'status' => Event::STATUS_ACTIVE,
            'starts_at' => now()->addMonth(),
            'registration_is_open' => false,
        ]);

        DonationCampaign::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'title' => 'Do Not Duplicate This Campaign',
            'status' => DonationCampaign::STATUS_ACTIVE,
            'payment_mode' => DonationCampaign::PAYMENT_MODE_CLIENT_DIRECT,
            'direct_payment_behavior' => DonationCampaign::DIRECT_BEHAVIOR_DISPLAY_ONLY,
            'currency' => 'TZS',
            'is_public' => true,
        ]);

        $response = $this->get(route('public.events.index'));

        $response->assertOk();
        $response->assertSee('Simple Public Event');
        $response->assertDontSee('Support Campaigns');
        $response->assertDontSee('Do Not Duplicate This Campaign');
        $response->assertDontSee('Registration Closed');
        $response->assertSee('View Event');
    }
}
