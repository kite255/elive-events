<?php

namespace Tests\Feature\Donations;

use App\Models\DonationCampaign;
use App\Models\DonationPaymentMethod;
use App\Models\Event;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicDonationCampaignTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_directory_lists_only_active_public_campaigns(): void
    {
        [$organization, $event] = $this->makeOrganizationAndEvent();

        $visible = $this->makeCampaign($organization, $event, 'Visible Campaign', [
            'status' => DonationCampaign::STATUS_ACTIVE,
            'is_public' => true,
        ]);

        $this->makeCampaign($organization, null, 'Draft Campaign', [
            'status' => DonationCampaign::STATUS_DRAFT,
            'is_public' => true,
        ]);

        $this->makeCampaign($organization, null, 'Private Campaign', [
            'status' => DonationCampaign::STATUS_ACTIVE,
            'is_public' => false,
        ]);

        $response = $this->get(route('public.donations.index'));

        $response->assertOk();
        $response->assertSee('Visible Campaign');
        $response->assertDontSee('Draft Campaign');
        $response->assertDontSee('Private Campaign');
        $response->assertSee(route('public.donations.show', ['campaign' => $visible->slug]), false);
    }

    public function test_display_only_campaign_shows_client_payment_instructions_without_tracking_form(): void
    {
        [$organization, $event] = $this->makeOrganizationAndEvent();

        $campaign = $this->makeCampaign($organization, $event, 'Church Building Fund', [
            'status' => DonationCampaign::STATUS_ACTIVE,
            'is_public' => true,
            'payment_mode' => DonationCampaign::PAYMENT_MODE_CLIENT_DIRECT,
            'direct_payment_behavior' => DonationCampaign::DIRECT_BEHAVIOR_DISPLAY_ONLY,
        ]);

        DonationPaymentMethod::query()->create([
            'donation_campaign_id' => $campaign->id,
            'type' => 'mobile_money',
            'provider_name' => 'M-Pesa',
            'account_name' => 'ABC Church',
            'account_number_or_phone' => '0754000000',
            'instructions' => 'Use your name as payment reference.',
            'enabled' => true,
            'sort_order' => 1,
        ]);

        $response = $this->get(route('public.donations.show', ['campaign' => $campaign->slug]));

        $response->assertOk();
        $response->assertSee('Church Building Fund');
        $response->assertSee('M-Pesa');
        $response->assertSee('0754000000');
        $response->assertSee('ABC Church');
        $response->assertSee('Payment is made directly to the campaign organizer using the details below.');
        $response->assertDontSee('donor_name', false);
        $response->assertDontSee('transaction_reference', false);
        $response->assertDontSee('proof', false);
        $response->assertDontSee('Amount Raised');
        $response->assertDontSee('Donate Now');
    }

    public function test_non_public_or_non_active_campaign_is_not_publicly_accessible(): void
    {
        [$organization] = $this->makeOrganizationAndEvent();

        $draft = $this->makeCampaign($organization, null, 'Draft Donation', [
            'status' => DonationCampaign::STATUS_DRAFT,
            'is_public' => true,
        ]);

        $private = $this->makeCampaign($organization, null, 'Private Donation', [
            'status' => DonationCampaign::STATUS_ACTIVE,
            'is_public' => false,
        ]);

        $this->get(route('public.donations.show', ['campaign' => $draft->slug]))
            ->assertNotFound();

        $this->get(route('public.donations.show', ['campaign' => $private->slug]))
            ->assertNotFound();
    }

    public function test_event_page_embeds_active_public_linked_campaign(): void
    {
        [$organization, $event] = $this->makeOrganizationAndEvent();

        $campaign = $this->makeCampaign($organization, $event, 'Support This Event', [
            'status' => DonationCampaign::STATUS_ACTIVE,
            'is_public' => true,
            'description' => 'Support the work connected to this event.',
        ]);

        DonationPaymentMethod::query()->create([
            'donation_campaign_id' => $campaign->id,
            'type' => 'bank',
            'provider_name' => 'CRDB Bank',
            'account_name' => 'Event Support Account',
            'account_number_or_phone' => '1234567890',
            'enabled' => true,
            'sort_order' => 1,
        ]);

        $response = $this->get(route('public.events.show', ['event' => $event->slug]));

        $response->assertOk();
        $response->assertSee('Support This Event');
        $response->assertSee('Support the work connected to this event.');
        $response->assertSee('CRDB Bank');
        $response->assertSee('1234567890');
        $response->assertDontSee(
            route('public.donations.show', ['campaign' => $campaign->slug]),
            false
        );
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

    private function makeCampaign(
        Organization $organization,
        ?Event $event,
        string $title,
        array $overrides = []
    ): DonationCampaign {
        return DonationCampaign::query()->create(array_merge([
            'organization_id' => $organization->id,
            'event_id' => $event?->id,
            'title' => $title,
            'status' => DonationCampaign::STATUS_ACTIVE,
            'payment_mode' => DonationCampaign::PAYMENT_MODE_CLIENT_DIRECT,
            'direct_payment_behavior' => DonationCampaign::DIRECT_BEHAVIOR_DISPLAY_ONLY,
            'currency' => 'TZS',
            'is_public' => true,
        ], $overrides));
    }
}
