<?php

namespace Tests\Feature\Donations;

use App\Filament\Resources\Events\RelationManagers\DonationCampaignsRelationManager;
use App\Filament\Resources\Events\RelationManagers\DonationsRelationManager;
use App\Models\Donation;
use App\Models\DonationCampaign;
use App\Models\DonationPaymentMethod;
use App\Models\Event;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventDonationIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_donation_relations_only_appear_for_event_types_that_use_donations(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Donation Events Organization',
            'slug' => 'donation-events-' . uniqid(),
        ]);

        $healthEvent = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Health Event',
            'slug' => 'health-event-' . uniqid(),
            'event_type' => 'health_event',
            'status' => Event::STATUS_ACTIVE,
            'starts_at' => now()->addWeek(),
        ]);

        $conference = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Conference',
            'slug' => 'conference-' . uniqid(),
            'event_type' => 'conference',
            'status' => Event::STATUS_ACTIVE,
            'starts_at' => now()->addWeek(),
        ]);

        $this->assertTrue(
            DonationCampaignsRelationManager::canViewForRecord($healthEvent, '')
        );
        $this->assertTrue(
            DonationsRelationManager::canViewForRecord($healthEvent, '')
        );
        $this->assertFalse(
            DonationCampaignsRelationManager::canViewForRecord($conference, '')
        );
        $this->assertFalse(
            DonationsRelationManager::canViewForRecord($conference, '')
        );
    }

    public function test_event_page_contains_complete_tracked_donation_experience(): void
    {
        [$organization, $event] = $this->makeHealthEvent();

        $campaign = DonationCampaign::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'title' => 'Support Our Health Event',
            'description' => 'Help us provide health services and supplies.',
            'status' => DonationCampaign::STATUS_ACTIVE,
            'payment_mode' => DonationCampaign::PAYMENT_MODE_HYBRID,
            'direct_payment_behavior' => DonationCampaign::DIRECT_BEHAVIOR_TRACKED,
            'currency' => 'TZS',
            'minimum_amount' => 5000,
            'suggested_amounts' => [10000, 25000],
            'allow_custom_amount' => true,
            'goal_amount' => 100000,
            'show_goal' => true,
            'show_amount_raised' => true,
            'show_percentage' => true,
            'show_donor_count' => true,
            'donor_wall_enabled' => true,
            'is_public' => true,
        ]);

        DonationPaymentMethod::query()->create([
            'donation_campaign_id' => $campaign->id,
            'type' => 'bank',
            'provider_name' => 'CRDB Bank',
            'account_name' => 'Health Event Fund',
            'account_number_or_phone' => '1234567890',
            'enabled' => true,
            'sort_order' => 1,
        ]);

        Donation::query()->create([
            'donation_campaign_id' => $campaign->id,
            'donor_name' => 'Public Supporter',
            'amount' => 20000,
            'currency' => 'TZS',
            'payment_type' => 'client_direct',
            'status' => Donation::STATUS_COMPLETED,
            'public_display_consent' => true,
            'completed_at' => now(),
        ]);

        $response = $this->get(route('public.events.show', [
            'event' => $event->slug,
        ]));

        $response->assertOk();
        $response->assertSee('Support This Event');
        $response->assertSee('Support Our Health Event');
        $response->assertSee('Amount Raised');
        $response->assertSee('Goal');
        $response->assertSee('Progress');
        $response->assertSee('Donors');
        $response->assertSee('TZS 10,000');
        $response->assertSee('TZS 25,000');
        $response->assertSee('donor_name', false);
        $response->assertSee('donor_phone', false);
        $response->assertSee('donor_email', false);
        $response->assertSee('is_anonymous', false);
        $response->assertSee('public_display_consent', false);
        $response->assertSee('Public Supporter');
        $response->assertSee('CRDB Bank');
        $response->assertSee('1234567890');
    }

    public function test_event_linked_tracked_donation_returns_to_event_page(): void
    {
        [$organization, $event] = $this->makeHealthEvent();

        $campaign = DonationCampaign::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'title' => 'Event Giving',
            'status' => DonationCampaign::STATUS_ACTIVE,
            'payment_mode' => DonationCampaign::PAYMENT_MODE_CLIENT_DIRECT,
            'direct_payment_behavior' => DonationCampaign::DIRECT_BEHAVIOR_TRACKED,
            'currency' => 'TZS',
            'allow_custom_amount' => true,
            'is_public' => true,
        ]);

        $response = $this->post(route('public.donations.store', [
            'campaign' => $campaign->slug,
        ]), [
            'donor_name' => 'Event Donor',
            'donor_phone' => '0755000000',
            'amount' => 15000,
            'is_anonymous' => false,
            'public_display_consent' => false,
        ]);

        $response->assertRedirect(route('public.events.show', [
            'event' => $event->slug,
        ]));

        $this->assertDatabaseHas('donations', [
            'donation_campaign_id' => $campaign->id,
            'donor_name' => 'Event Donor',
            'amount' => 15000,
            'status' => Donation::STATUS_PENDING,
        ]);
    }

    private function makeHealthEvent(): array
    {
        $organization = Organization::query()->create([
            'name' => 'Health Support Organization',
            'slug' => 'health-support-' . uniqid(),
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Community Health Day',
            'slug' => 'community-health-day-' . uniqid(),
            'event_type' => 'health_event',
            'status' => Event::STATUS_ACTIVE,
            'starts_at' => now()->addMonth(),
            'registration_is_open' => false,
        ]);

        return [$organization, $event];
    }
}
