<?php

namespace Tests\Feature\Donations;

use App\Filament\Resources\DonationCampaigns\DonationCampaignResource;
use App\Filament\Resources\DonationPaymentMethods\DonationPaymentMethodResource;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DonationNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_planned_donation_admin_surfaces_exist(): void
    {
        $this->assertTrue(
            class_exists('App\\Filament\\Resources\\Donations\\DonationResource')
        );

        $this->assertTrue(
            class_exists('App\\Filament\\Pages\\ManualDonationVerification')
        );

        $this->assertTrue(
            class_exists('App\\Filament\\Pages\\DonationReports')
        );

        $this->assertTrue(
            class_exists('App\\Filament\\Pages\\DonationPaymentReconciliation')
        );
    }

    public function test_unrelated_member_cannot_access_donation_navigation(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Donation Navigation Organization',
            'slug' => 'donation-navigation-organization-' . uniqid(),
        ]);

        $member = User::factory()->create();
        $organization->attachUser(
            $member,
            User::ORGANIZATION_ROLE_MEMBER
        );

        $this->actingAs($member);

        $this->assertFalse(DonationCampaignResource::shouldRegisterNavigation());
        $this->assertFalse(DonationPaymentMethodResource::shouldRegisterNavigation());

        foreach ([
            'App\\Filament\\Resources\\Donations\\DonationResource',
            'App\\Filament\\Pages\\ManualDonationVerification',
            'App\\Filament\\Pages\\DonationReports',
            'App\\Filament\\Pages\\DonationPaymentReconciliation',
        ] as $class) {
            $this->assertTrue(class_exists($class));
            $this->assertFalse($class::shouldRegisterNavigation());
        }
    }

    public function test_assigned_event_manager_gets_donation_navigation_for_assigned_event(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Manager Navigation Organization',
            'slug' => 'manager-navigation-organization-' . uniqid(),
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Manager Donation Event',
            'slug' => 'manager-donation-event-' . uniqid(),
            'status' => Event::STATUS_ACTIVE,
        ]);

        $manager = User::factory()->create();

        $organization->attachUser(
            $manager,
            User::ORGANIZATION_ROLE_MEMBER
        );

        $event->assignUser(
            $manager,
            User::ORGANIZATION_ROLE_EVENT_MANAGER
        );

        $this->actingAs($manager);

        $this->assertTrue(DonationCampaignResource::shouldRegisterNavigation());
        $this->assertTrue(DonationPaymentMethodResource::shouldRegisterNavigation());

        foreach ([
            'App\\Filament\\Resources\\Donations\\DonationResource',
            'App\\Filament\\Pages\\ManualDonationVerification',
            'App\\Filament\\Pages\\DonationReports',
            'App\\Filament\\Pages\\DonationPaymentReconciliation',
        ] as $class) {
            $this->assertTrue(class_exists($class));
            $this->assertTrue($class::shouldRegisterNavigation());
        }
    }

    public function test_super_admin_gets_all_donation_navigation(): void
    {
        $admin = User::factory()->create([
            'is_super_admin' => true,
        ]);

        $this->actingAs($admin);

        $this->assertTrue(DonationCampaignResource::shouldRegisterNavigation());
        $this->assertTrue(DonationPaymentMethodResource::shouldRegisterNavigation());

        foreach ([
            'App\\Filament\\Resources\\Donations\\DonationResource',
            'App\\Filament\\Pages\\ManualDonationVerification',
            'App\\Filament\\Pages\\DonationReports',
            'App\\Filament\\Pages\\DonationPaymentReconciliation',
        ] as $class) {
            $this->assertTrue(class_exists($class));
            $this->assertTrue($class::shouldRegisterNavigation());
        }
    }
}
