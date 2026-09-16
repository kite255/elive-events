<?php

namespace Tests\Feature\Finance;

use App\Filament\Pages\AdminFinanceOverview;
use App\Filament\Pages\OrganizerFinanceOverview;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinancePageSeparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_organizer_finance_page_exists(): void
    {
        $this->assertTrue(
            class_exists(OrganizerFinanceOverview::class),
            'OrganizerFinanceOverview page must exist.'
        );
    }

    public function test_super_admin_finance_page_exists(): void
    {
        $this->assertTrue(
            class_exists(AdminFinanceOverview::class),
            'AdminFinanceOverview page must exist.'
        );
    }

    public function test_organization_owner_can_access_organizer_finance_page(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Organizer Finance Test',
            'email' => 'finance-owner@example.com',
        ]);

        $owner = User::query()->create([
            'name' => 'Organization Owner',
            'email' => 'owner@example.com',
            'password' => 'password',
            'is_super_admin' => false,
        ]);

        $owner->organizations()->attach(
            $organization->id,
            [
                'role' => User::ORGANIZATION_ROLE_OWNER,
                'status' => User::ORGANIZATION_STATUS_ACTIVE,
                'is_owner' => true,
                'joined_at' => now(),
            ]
        );

        $this->actingAs($owner);

        $this->assertTrue(OrganizerFinanceOverview::canAccess());
        $this->assertFalse(AdminFinanceOverview::canAccess());
    }

    public function test_super_admin_can_access_only_admin_finance_page(): void
    {
        $superAdmin = User::query()->create([
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
            'password' => 'password',
            'is_super_admin' => true,
        ]);

        $this->actingAs($superAdmin);

        $this->assertFalse(OrganizerFinanceOverview::canAccess());
        $this->assertTrue(AdminFinanceOverview::canAccess());
    }

    public function test_regular_member_cannot_access_finance_pages(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Member Finance Test',
            'email' => 'finance-member@example.com',
        ]);

        $member = User::query()->create([
            'name' => 'Regular Member',
            'email' => 'member@example.com',
            'password' => 'password',
            'is_super_admin' => false,
        ]);

        $member->organizations()->attach(
            $organization->id,
            [
                'role' => User::ORGANIZATION_ROLE_MEMBER,
                'status' => User::ORGANIZATION_STATUS_ACTIVE,
                'is_owner' => false,
                'joined_at' => now(),
            ]
        );

        $this->actingAs($member);

        $this->assertFalse(OrganizerFinanceOverview::canAccess());
        $this->assertFalse(AdminFinanceOverview::canAccess());
    }

    public function test_organizer_finance_metrics_hide_internal_charge_breakdown(): void
    {
        $page = new OrganizerFinanceOverview();

        $definitions = $page->financeMetricDefinitions();

        $this->assertSame(
            [
                'gross_sales',
                'total_charges',
                'net_payable',
            ],
            array_keys($definitions)
        );

        $this->assertArrayNotHasKey('platform_commission_amount', $definitions);
        $this->assertArrayNotHasKey('platform_commission_rate', $definitions);
        $this->assertArrayNotHasKey('gateway_fee_amount', $definitions);
        $this->assertArrayNotHasKey('gateway_fee_rate', $definitions);
        $this->assertArrayNotHasKey('gateway_fee_bearer', $definitions);
    }

    public function test_super_admin_finance_metrics_show_full_internal_breakdown(): void
    {
        $page = new AdminFinanceOverview();

        $definitions = $page->financeMetricDefinitions();

        foreach ([
            'gross_sales',
            'platform_commission_amount',
            'platform_commission_rate',
            'gateway_fee_amount',
            'gateway_fee_rate',
            'gateway_fee_bearer',
            'total_charges',
            'organizer_net_amount',
        ] as $key) {
            $this->assertArrayHasKey($key, $definitions);
        }
    }
}
