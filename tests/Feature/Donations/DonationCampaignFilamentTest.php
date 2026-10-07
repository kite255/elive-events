<?php

namespace Tests\Feature\Donations;

use App\Filament\Resources\DonationCampaigns\DonationCampaignResource;
use App\Filament\Resources\DonationCampaigns\Schemas\DonationCampaignForm;
use App\Filament\Resources\DonationPaymentMethods\DonationPaymentMethodResource;
use App\Models\DonationCampaign;
use App\Models\DonationPaymentMethod;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DonationCampaignFilamentTest extends TestCase
{
    use RefreshDatabase;

    public function test_organization_admin_sees_only_own_campaigns_and_can_create(): void
    {
        [$firstOrganization, $firstEvent, $firstCampaign] = $this->makeCampaign('First');
        [$secondOrganization, $secondEvent, $secondCampaign] = $this->makeCampaign('Second');

        $admin = User::factory()->create();
        $firstOrganization->attachUser($admin, User::ORGANIZATION_ROLE_ADMIN);

        $this->actingAs($admin);

        $this->assertTrue(DonationCampaignResource::canViewAny());
        $this->assertTrue(DonationCampaignResource::canCreate());

        $ids = DonationCampaignResource::getEloquentQuery()->pluck('id')->all();

        $this->assertContains($firstCampaign->id, $ids);
        $this->assertNotContains($secondCampaign->id, $ids);

        $this->assertSame(
            [$firstOrganization->id => $firstOrganization->name],
            DonationCampaignForm::organizationOptions()
        );

        $this->assertSame(
            [$firstEvent->id => $firstEvent->name],
            DonationCampaignForm::eventOptions($firstOrganization->id)
        );
    }

    public function test_event_manager_can_manage_only_assigned_event_campaigns_and_select_only_assigned_event(): void
    {
        [$organization, $assignedEvent, $assignedCampaign] = $this->makeCampaign('Assigned');

        $otherEvent = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Other Event',
            'slug' => 'other-event-' . uniqid(),
            'status' => Event::STATUS_ACTIVE,
        ]);

        $otherCampaign = DonationCampaign::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $otherEvent->id,
            'title' => 'Other Campaign',
            'status' => DonationCampaign::STATUS_ACTIVE,
            'payment_mode' => DonationCampaign::PAYMENT_MODE_CLIENT_DIRECT,
            'direct_payment_behavior' => DonationCampaign::DIRECT_BEHAVIOR_DISPLAY_ONLY,
            'currency' => 'TZS',
        ]);

        $manager = User::factory()->create();
        $organization->attachUser($manager, User::ORGANIZATION_ROLE_MEMBER);
        $assignedEvent->assignUser($manager, User::ORGANIZATION_ROLE_EVENT_MANAGER);

        $this->actingAs($manager);

        $this->assertTrue(DonationCampaignResource::canViewAny());
        $this->assertTrue(DonationCampaignResource::canCreate());

        $ids = DonationCampaignResource::getEloquentQuery()->pluck('id')->all();

        $this->assertContains($assignedCampaign->id, $ids);
        $this->assertNotContains($otherCampaign->id, $ids);

        $this->assertSame(
            [$organization->id => $organization->name],
            DonationCampaignForm::organizationOptions()
        );

        $this->assertSame(
            [$assignedEvent->id => $assignedEvent->name],
            DonationCampaignForm::eventOptions($organization->id)
        );
    }

    public function test_unrelated_member_cannot_open_donation_admin_resources(): void
    {
        [$organization] = $this->makeCampaign('Restricted');

        $member = User::factory()->create();
        $organization->attachUser($member, User::ORGANIZATION_ROLE_MEMBER);

        $this->actingAs($member);

        $this->assertFalse(DonationCampaignResource::canViewAny());
        $this->assertFalse(DonationCampaignResource::canCreate());
        $this->assertFalse(DonationPaymentMethodResource::canViewAny());
        $this->assertFalse(DonationPaymentMethodResource::canCreate());
    }

    public function test_payment_method_resource_is_scoped_to_manageable_campaigns(): void
    {
        [$firstOrganization, $firstEvent, $firstCampaign] = $this->makeCampaign('Managed');
        [$secondOrganization, $secondEvent, $secondCampaign] = $this->makeCampaign('Hidden');

        $firstMethod = DonationPaymentMethod::query()->create([
            'donation_campaign_id' => $firstCampaign->id,
            'type' => 'mobile_money',
            'provider_name' => 'M-Pesa',
            'account_name' => 'Managed Account',
            'account_number_or_phone' => '255700000001',
            'enabled' => true,
            'sort_order' => 1,
        ]);

        $secondMethod = DonationPaymentMethod::query()->create([
            'donation_campaign_id' => $secondCampaign->id,
            'type' => 'bank',
            'provider_name' => 'CRDB',
            'account_name' => 'Hidden Account',
            'account_number_or_phone' => '0152000000000',
            'enabled' => true,
            'sort_order' => 1,
        ]);

        $admin = User::factory()->create();
        $firstOrganization->attachUser($admin, User::ORGANIZATION_ROLE_ADMIN);

        $this->actingAs($admin);

        $this->assertTrue(DonationPaymentMethodResource::canViewAny());
        $this->assertTrue(DonationPaymentMethodResource::canCreate());

        $ids = DonationPaymentMethodResource::getEloquentQuery()->pluck('id')->all();

        $this->assertContains($firstMethod->id, $ids);
        $this->assertNotContains($secondMethod->id, $ids);
    }

    public function test_display_only_campaign_suppresses_tracking_fields(): void
    {
        $this->assertFalse(
            DonationCampaignForm::trackingFieldsVisible(
                DonationCampaign::PAYMENT_MODE_CLIENT_DIRECT,
                DonationCampaign::DIRECT_BEHAVIOR_DISPLAY_ONLY
            )
        );

        $this->assertTrue(
            DonationCampaignForm::trackingFieldsVisible(
                DonationCampaign::PAYMENT_MODE_CLIENT_DIRECT,
                DonationCampaign::DIRECT_BEHAVIOR_TRACKED
            )
        );

        $this->assertTrue(
            DonationCampaignForm::trackingFieldsVisible(
                DonationCampaign::PAYMENT_MODE_PLATFORM,
                null
            )
        );
    }

    private function makeCampaign(string $prefix): array
    {
        $organization = Organization::query()->create([
            'name' => $prefix . ' Organization',
            'slug' => strtolower($prefix) . '-organization-' . uniqid(),
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => $prefix . ' Event',
            'slug' => strtolower($prefix) . '-event-' . uniqid(),
            'status' => Event::STATUS_ACTIVE,
        ]);

        $campaign = DonationCampaign::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'title' => $prefix . ' Campaign',
            'status' => DonationCampaign::STATUS_ACTIVE,
            'payment_mode' => DonationCampaign::PAYMENT_MODE_CLIENT_DIRECT,
            'direct_payment_behavior' => DonationCampaign::DIRECT_BEHAVIOR_DISPLAY_ONLY,
            'currency' => 'TZS',
        ]);

        return [$organization, $event, $campaign];
    }
}
