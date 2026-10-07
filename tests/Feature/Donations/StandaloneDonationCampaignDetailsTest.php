<?php

namespace Tests\Feature\Donations;

use App\Models\DonationCampaign;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StandaloneDonationCampaignDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_standalone_campaign_detail_columns_exist(): void
    {
        foreach ([
            'starts_at',
            'ends_at',
            'venue',
            'venue_address',
            'map_url',
            'organizer_contact_phone',
            'organizer_contact_email',
            'public_highlights',
        ] as $column) {
            $this->assertTrue(
                Schema::hasColumn('donation_campaigns', $column),
                "Missing donation_campaigns.{$column}"
            );
        }
    }

    public function test_standalone_campaign_casts_dates_and_highlights(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Standalone Campaign Organization',
            'slug' => 'standalone-campaign-organization-' . uniqid(),
        ]);

        $campaign = DonationCampaign::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Children Health Support',
            'status' => DonationCampaign::STATUS_ACTIVE,
            'payment_mode' => DonationCampaign::PAYMENT_MODE_CLIENT_DIRECT,
            'direct_payment_behavior' => DonationCampaign::DIRECT_BEHAVIOR_DISPLAY_ONLY,
            'currency' => 'TZS',
            'is_public' => true,
            'starts_at' => '2026-11-08 09:00:00',
            'ends_at' => '2026-11-08 17:00:00',
            'public_highlights' => [
                ['value' => 'Free', 'label' => 'Health Screenings'],
            ],
        ]);

        $campaign->refresh();

        $this->assertNotNull($campaign->starts_at);
        $this->assertSame('2026-11-08', $campaign->starts_at->format('Y-m-d'));
        $this->assertIsArray($campaign->public_highlights);
        $this->assertSame('Free', $campaign->public_highlights[0]['value']);
    }

    public function test_public_standalone_campaign_renders_event_details_without_linked_event(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Serunt Nutrition',
            'slug' => 'serunt-nutrition-' . uniqid(),
        ]);

        $campaign = DonationCampaign::query()->create([
            'organization_id' => $organization->id,
            'event_id' => null,
            'title' => 'Support the Children\'s Health Festival',
            'description' => 'Support children attending the health festival.',
            'status' => DonationCampaign::STATUS_ACTIVE,
            'payment_mode' => DonationCampaign::PAYMENT_MODE_CLIENT_DIRECT,
            'direct_payment_behavior' => DonationCampaign::DIRECT_BEHAVIOR_DISPLAY_ONLY,
            'currency' => 'TZS',
            'is_public' => true,
            'starts_at' => '2026-11-08 09:00:00',
            'ends_at' => '2026-11-08 17:00:00',
            'venue' => 'Magomeni Garden',
            'venue_address' => 'Magomeni, Dar es Salaam',
            'map_url' => 'https://maps.example.test/magomeni',
            'organizer_contact_phone' => '0755927444',
            'organizer_contact_email' => 'hello@serunt.test',
            'public_highlights' => [
                ['value' => 'Free', 'label' => 'Health Screenings'],
                ['value' => 'Kids', 'label' => 'Activities'],
            ],
        ]);

        $response = $this->get(route('public.donations.show', [
            'campaign' => $campaign->slug,
        ]));

        $response->assertOk();
        $response->assertSee('8 Nov 2026');
        $response->assertSee('Magomeni Garden');
        $response->assertSee('Magomeni, Dar es Salaam');
        $response->assertSee('0755927444');
        $response->assertSee('hello@serunt.test');
        $response->assertSee('Health Screenings');
        $response->assertSee('Activities');
        $response->assertSee('View Map');
        $response->assertDontSee('Linked to');
    }
}
