<?php

namespace Tests\Feature\Donations;

use App\Models\Donation;
use App\Models\DonationCampaign;
use App\Models\DonationManualSubmission;
use App\Models\DonationPaymentMethod;
use App\Models\Event;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DonationSchemaAndModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_donation_tables_and_required_columns_exist(): void
    {
        $this->assertTrue(Schema::hasColumns('donation_campaigns', [
            'id',
            'organization_id',
            'event_id',
            'title',
            'slug',
            'description',
            'banner_image_path',
            'status',
            'payment_mode',
            'direct_payment_behavior',
            'currency',
            'minimum_amount',
            'suggested_amounts',
            'allow_custom_amount',
            'goal_amount',
            'show_goal',
            'show_amount_raised',
            'show_percentage',
            'show_donor_count',
            'donor_wall_enabled',
            'notification_settings',
            'is_public',
            'created_at',
            'updated_at',
        ]));

        $this->assertTrue(Schema::hasColumns('donation_payment_methods', [
            'id',
            'donation_campaign_id',
            'type',
            'provider_name',
            'account_name',
            'account_number_or_phone',
            'instructions',
            'enabled',
            'sort_order',
            'created_at',
            'updated_at',
        ]));

        $this->assertTrue(Schema::hasColumns('donations', [
            'id',
            'donation_campaign_id',
            'public_token',
            'reference',
            'donor_name',
            'donor_phone',
            'donor_email',
            'amount',
            'currency',
            'is_anonymous',
            'public_display_consent',
            'payment_type',
            'status',
            'completed_at',
            'created_at',
            'updated_at',
        ]));

        $this->assertTrue(Schema::hasColumns('donation_manual_submissions', [
            'id',
            'donation_id',
            'donation_payment_method_id',
            'transaction_reference',
            'proof_path',
            'submitted_at',
            'verified_by',
            'verified_at',
            'rejection_reason',
            'created_at',
            'updated_at',
        ]));
    }

    public function test_donation_models_define_required_domain_constants(): void
    {
        $this->assertTrue(class_exists(DonationCampaign::class));
        $this->assertTrue(class_exists(Donation::class));
        $this->assertTrue(class_exists(DonationPaymentMethod::class));
        $this->assertTrue(class_exists(DonationManualSubmission::class));

        $this->assertSame('draft', DonationCampaign::STATUS_DRAFT);
        $this->assertSame('active', DonationCampaign::STATUS_ACTIVE);
        $this->assertSame('paused', DonationCampaign::STATUS_PAUSED);
        $this->assertSame('completed', DonationCampaign::STATUS_COMPLETED);
        $this->assertSame('archived', DonationCampaign::STATUS_ARCHIVED);

        $this->assertSame('platform', DonationCampaign::PAYMENT_MODE_PLATFORM);
        $this->assertSame('client_direct', DonationCampaign::PAYMENT_MODE_CLIENT_DIRECT);
        $this->assertSame('hybrid', DonationCampaign::PAYMENT_MODE_HYBRID);
        $this->assertSame('display_only', DonationCampaign::DIRECT_BEHAVIOR_DISPLAY_ONLY);
        $this->assertSame('tracked', DonationCampaign::DIRECT_BEHAVIOR_TRACKED);

        $this->assertSame('pending', Donation::STATUS_PENDING);
        $this->assertSame('awaiting_payment', Donation::STATUS_AWAITING_PAYMENT);
        $this->assertSame('awaiting_verification', Donation::STATUS_AWAITING_VERIFICATION);
        $this->assertSame('completed', Donation::STATUS_COMPLETED);
        $this->assertSame('rejected', Donation::STATUS_REJECTED);
        $this->assertSame('failed', Donation::STATUS_FAILED);
        $this->assertSame('cancelled', Donation::STATUS_CANCELLED);
    }

    public function test_campaign_defaults_hide_progress_and_generate_scoped_slug(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Donation Organization',
            'slug' => 'donation-organization-' . uniqid(),
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Donation Event',
            'slug' => 'donation-event-' . uniqid(),
            'status' => Event::STATUS_ACTIVE,
        ]);

        $first = DonationCampaign::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'title' => 'Church Building Fund',
            'status' => DonationCampaign::STATUS_ACTIVE,
            'payment_mode' => DonationCampaign::PAYMENT_MODE_CLIENT_DIRECT,
            'direct_payment_behavior' => DonationCampaign::DIRECT_BEHAVIOR_DISPLAY_ONLY,
            'currency' => 'TZS',
        ]);

        $second = DonationCampaign::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Church Building Fund',
            'status' => DonationCampaign::STATUS_ACTIVE,
            'payment_mode' => DonationCampaign::PAYMENT_MODE_CLIENT_DIRECT,
            'direct_payment_behavior' => DonationCampaign::DIRECT_BEHAVIOR_DISPLAY_ONLY,
            'currency' => 'TZS',
        ]);

        $this->assertSame('church-building-fund', $first->slug);
        $this->assertNotSame($first->slug, $second->slug);
        $this->assertFalse($first->show_goal);
        $this->assertFalse($first->show_amount_raised);
        $this->assertFalse($first->show_percentage);
        $this->assertFalse($first->show_donor_count);
        $this->assertFalse($first->donor_wall_enabled);
        $this->assertCount(0, $first->donations);
        $this->assertTrue($first->organization->is($organization));
        $this->assertTrue($first->event->is($event));
    }

    public function test_donation_generates_opaque_public_token_and_relationships(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Tracked Donation Organization',
            'slug' => 'tracked-donation-organization-' . uniqid(),
        ]);

        $campaign = DonationCampaign::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Education Support',
            'status' => DonationCampaign::STATUS_ACTIVE,
            'payment_mode' => DonationCampaign::PAYMENT_MODE_CLIENT_DIRECT,
            'direct_payment_behavior' => DonationCampaign::DIRECT_BEHAVIOR_TRACKED,
            'currency' => 'TZS',
        ]);

        $method = DonationPaymentMethod::query()->create([
            'donation_campaign_id' => $campaign->id,
            'type' => 'mobile_money',
            'provider_name' => 'M-Pesa',
            'account_name' => 'Education Support',
            'account_number_or_phone' => '255700000001',
            'enabled' => true,
            'sort_order' => 1,
        ]);

        $donation = Donation::query()->create([
            'donation_campaign_id' => $campaign->id,
            'reference' => 'ELV-DON-TEST-' . uniqid(),
            'donor_name' => 'Test Donor',
            'donor_phone' => '255700000002',
            'amount' => 50000,
            'currency' => 'TZS',
            'payment_type' => 'client_direct',
            'status' => Donation::STATUS_AWAITING_VERIFICATION,
        ]);

        $submission = DonationManualSubmission::query()->create([
            'donation_id' => $donation->id,
            'donation_payment_method_id' => $method->id,
            'transaction_reference' => 'MPESA-REF-001',
            'submitted_at' => now(),
        ]);

        $this->assertNotEmpty($donation->public_token);
        $this->assertSame(48, strlen($donation->public_token));
        $this->assertTrue($donation->campaign->is($campaign));
        $this->assertTrue($campaign->donations->contains($donation));
        $this->assertTrue($campaign->paymentMethods->contains($method));
        $this->assertTrue($donation->manualSubmission->is($submission));
        $this->assertTrue($submission->paymentMethod->is($method));
        $this->assertFalse($donation->isCompleted());
    }
}
