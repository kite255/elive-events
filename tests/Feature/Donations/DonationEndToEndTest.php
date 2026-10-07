<?php

namespace Tests\Feature\Donations;

use App\Models\Donation;
use App\Models\DonationCampaign;
use App\Models\DonationPaymentMethod;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\User;
use App\Services\Donations\DonationAuthorizationService;
use App\Services\Donations\DonationPaymentService;
use App\Services\Donations\DonationService;
use App\Services\Donations\ManualDonationService;
use App\Services\Payments\PaymentFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DonationEndToEndTest extends TestCase
{
    use RefreshDatabase;

    public function test_display_only_campaign_cannot_create_tracked_donation(): void
    {
        [$organization, $event] = $this->organizationAndEvent();

        $campaign = DonationCampaign::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'title' => 'Display Only Campaign',
            'status' => DonationCampaign::STATUS_ACTIVE,
            'payment_mode' => DonationCampaign::PAYMENT_MODE_CLIENT_DIRECT,
            'direct_payment_behavior' => DonationCampaign::DIRECT_BEHAVIOR_DISPLAY_ONLY,
            'currency' => 'TZS',
            'is_public' => true,
        ]);

        $this->post(
            route('public.donations.store', [
                'campaign' => $campaign->slug,
            ]),
            [
                'donor_name' => 'Blocked Donor',
                'donor_phone' => '255700000001',
                'amount' => 10000,
            ]
        )->assertNotFound();

        $this->assertDatabaseCount('donations', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_tracked_direct_donation_reaches_completed_only_after_authorized_approval(): void
    {
        [$organization, $event] = $this->organizationAndEvent();

        $campaign = DonationCampaign::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'title' => 'Tracked Direct Campaign',
            'status' => DonationCampaign::STATUS_ACTIVE,
            'payment_mode' => DonationCampaign::PAYMENT_MODE_CLIENT_DIRECT,
            'direct_payment_behavior' => DonationCampaign::DIRECT_BEHAVIOR_TRACKED,
            'currency' => 'TZS',
            'minimum_amount' => 5000,
            'is_public' => true,
        ]);

        $method = DonationPaymentMethod::query()->create([
            'donation_campaign_id' => $campaign->id,
            'type' => 'mobile_money',
            'provider_name' => 'M-Pesa',
            'account_name' => 'Tracked Campaign',
            'account_number_or_phone' => '255700000002',
            'enabled' => true,
            'sort_order' => 1,
        ]);

        $donation = app(DonationService::class)->createTracked(
            $campaign,
            [
                'donor_name' => 'Tracked Donor',
                'donor_phone' => '255700000003',
                'amount' => 15000,
            ]
        );

        $this->assertSame(
            Donation::STATUS_PENDING,
            $donation->status
        );

        app(ManualDonationService::class)->submit(
            $donation,
            $method,
            'M-PESA-E2E-001'
        );

        $this->assertSame(
            Donation::STATUS_AWAITING_VERIFICATION,
            $donation->fresh()->status
        );

        $admin = User::factory()->create();
        $organization->attachUser(
            $admin,
            User::ORGANIZATION_ROLE_ADMIN
        );

        app(ManualDonationService::class)->approve(
            $donation->fresh(),
            $admin
        );

        $this->assertSame(
            Donation::STATUS_COMPLETED,
            $donation->fresh()->status
        );
    }

    public function test_platform_donation_completes_only_through_completed_payment_fulfillment(): void
    {
        [$organization, $event] = $this->organizationAndEvent();

        PaymentGateway::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Pesapal',
            'code' => 'pesapal',
            'is_enabled' => true,
            'is_default' => true,
            'environment' => 'sandbox',
            'configuration' => [],
        ]);

        $campaign = DonationCampaign::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'title' => 'Platform Campaign',
            'status' => DonationCampaign::STATUS_ACTIVE,
            'payment_mode' => DonationCampaign::PAYMENT_MODE_PLATFORM,
            'currency' => 'TZS',
            'is_public' => true,
        ]);

        $donation = app(DonationService::class)->createTracked(
            $campaign,
            [
                'donor_name' => 'Online Donor',
                'donor_phone' => '255700000004',
                'donor_email' => 'online@example.com',
                'amount' => 25000,
            ]
        );

        $payment = app(DonationPaymentService::class)->create(
            $donation
        );

        $this->assertNotSame(
            Donation::STATUS_COMPLETED,
            $donation->fresh()->status
        );

        $payment->forceFill([
            'status' => Payment::STATUS_COMPLETED,
            'paid_at' => now(),
        ])->save();

        app(PaymentFulfillmentService::class)->fulfill(
            $payment->fresh()
        );

        $this->assertSame(
            Donation::STATUS_COMPLETED,
            $donation->fresh()->status
        );

        $this->assertNotNull(
            $payment->fresh()->fulfilled_at
        );
    }

    public function test_event_manager_cannot_access_unassigned_campaign_even_with_numeric_id(): void
    {
        [$organization, $assignedEvent] =
            $this->organizationAndEvent();

        $otherEvent = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Unassigned Donation Event',
            'slug' => 'unassigned-donation-event-' . uniqid(),
            'status' => Event::STATUS_ACTIVE,
        ]);

        $assignedCampaign = DonationCampaign::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $assignedEvent->id,
            'title' => 'Assigned Campaign',
            'status' => DonationCampaign::STATUS_ACTIVE,
            'payment_mode' => DonationCampaign::PAYMENT_MODE_CLIENT_DIRECT,
            'direct_payment_behavior' => DonationCampaign::DIRECT_BEHAVIOR_TRACKED,
            'currency' => 'TZS',
        ]);

        $unassignedCampaign = DonationCampaign::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $otherEvent->id,
            'title' => 'Unassigned Campaign',
            'status' => DonationCampaign::STATUS_ACTIVE,
            'payment_mode' => DonationCampaign::PAYMENT_MODE_CLIENT_DIRECT,
            'direct_payment_behavior' => DonationCampaign::DIRECT_BEHAVIOR_TRACKED,
            'currency' => 'TZS',
        ]);

        $manager = User::factory()->create();

        $organization->attachUser(
            $manager,
            User::ORGANIZATION_ROLE_MEMBER
        );

        $assignedEvent->assignUser(
            $manager,
            User::ORGANIZATION_ROLE_EVENT_MANAGER
        );

        $authorization =
            app(DonationAuthorizationService::class);

        $this->assertTrue(
            $authorization->canManageCampaign(
                $manager,
                $assignedCampaign
            )
        );

        $this->assertFalse(
            $authorization->canManageCampaign(
                $manager,
                $unassignedCampaign
            )
        );

        $this->assertNull(
            DonationCampaign::query()
                ->accessibleBy($manager)
                ->whereKey($unassignedCampaign->id)
                ->first()
        );
    }

    private function organizationAndEvent(): array
    {
        $organization = Organization::query()->create([
            'name' => 'Donation E2E Organization',
            'slug' => 'donation-e2e-organization-' . uniqid(),
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Donation E2E Event',
            'slug' => 'donation-e2e-event-' . uniqid(),
            'status' => Event::STATUS_ACTIVE,
        ]);

        return [$organization, $event];
    }
}
