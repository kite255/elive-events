<?php

namespace Tests\Feature;

use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\DashboardEventSwitcher;
use App\Filament\Widgets\DashboardQuickActions;
use App\Filament\Widgets\EventOperationsOverview;
use App\Filament\Widgets\DashboardActionRequired;
use App\Filament\Widgets\DashboardRecentActivity;
use App\Filament\Widgets\DashboardRevenueSummary;
use App\Filament\Widgets\DashboardUpcomingEvents;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GlobalDashboardWidgetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_global_dashboard_only_registers_approved_compact_widgets(): void
    {
        $this->assertSame([
            DashboardEventSwitcher::class,
            EventOperationsOverview::class,
            DashboardRevenueSummary::class,
            DashboardUpcomingEvents::class,
            DashboardQuickActions::class,
        ], (new Dashboard())->getWidgets());
    }

    public function test_dashboard_event_widgets_only_return_authorized_events(): void
    {
        $org = Organization::create(['name' => 'Dashboard organization']);
        $foreign = Organization::create(['name' => 'Unrelated dashboard organization']);
        $user = User::factory()->create(['is_super_admin' => false, 'is_gate_staff' => false]);
        $org->users()->attach($user->id, [
            'role' => User::ORGANIZATION_ROLE_EVENT_MANAGER,
            'status' => User::ORGANIZATION_STATUS_ACTIVE,
            'is_owner' => false,
            'joined_at' => now(),
        ]);

        $create = fn ($organization, $name) => Event::create([
            'organization_id' => $organization->id,
            'name' => $name,
            'status' => Event::STATUS_ACTIVE,
            'starts_at' => now()->addDay(),
        ]);

        $allowed = $create($org, 'Assigned event');
        $create($org, 'Unassigned event');
        $create($foreign, 'Foreign event');
        $allowed->assignUser($user, User::ORGANIZATION_ROLE_EVENT_MANAGER);
        $this->actingAs($user);

        $this->assertSame([$allowed->id], (new DashboardUpcomingEvents())->upcomingEvents()->modelKeys());
        $this->assertSame([$allowed->id], (new DashboardRecentActivity())->recentEvents()->modelKeys());
        $this->assertFalse(DashboardActionRequired::canView());
        $this->assertFalse(DashboardRevenueSummary::canView());
    }

    public function test_gate_staff_are_excluded_even_with_super_admin_flag(): void
    {
        $this->actingAs(User::factory()->create([
            'is_super_admin' => true,
            'is_gate_staff' => true,
        ]));

        $this->assertFalse(DashboardUpcomingEvents::canView());
        $this->assertFalse(DashboardRecentActivity::canView());
        $this->assertFalse(DashboardEventSwitcher::canView());
        $this->assertFalse(EventOperationsOverview::canView());
        $this->assertFalse(DashboardQuickActions::canView());
        $this->assertFalse(DashboardRevenueSummary::canView());
        $this->assertFalse(DashboardActionRequired::canView());
    }

    public function test_super_admin_can_view_operational_attention_summary(): void
    {
        $this->actingAs(User::factory()->create([
            'is_super_admin' => true,
            'is_gate_staff' => false,
        ]));
        $this->assertTrue(DashboardActionRequired::canView());
        $this->assertArrayHasKey('unfulfilled_payments', (new DashboardActionRequired())->issues());
    }
}
