<?php

namespace Tests\Feature\Tickets;

use App\Filament\Pages\AdminTicketLookup;
use App\Filament\Pages\OrganizerSalesDashboard;
use App\Filament\Pages\PaymentReconciliation;
use App\Filament\Pages\TicketCapacityDashboard;
use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Filament\Resources\TicketCheckIns\TicketCheckInResource;
use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketingOperationsNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_access_all_operational_pages(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $this->actingAs($admin);

        $this->assertTrue(TicketResource::canViewAny());
        $this->assertTrue(AdminTicketLookup::canAccess());
        $this->assertTrue(OrganizerSalesDashboard::canAccess());
        $this->assertTrue(TicketCapacityDashboard::canAccess());
        $this->assertTrue(PaymentReconciliation::canAccess());
        $this->assertTrue(TicketCheckInResource::canViewAny());
        $this->assertTrue(AuditLogResource::canViewAny());
    }

    public function test_check_in_officer_only_gets_check_in_history_from_new_operational_features(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Check-in Organization',
            'slug' => 'check-in-org-' . uniqid(),
        ]);
        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Check-in Event',
            'slug' => 'check-in-event-' . uniqid(),
            'status' => Event::STATUS_ACTIVE,
        ]);
        $officer = User::factory()->create(['is_super_admin' => false]);

        $organization->users()->attach($officer->id, [
            'role' => User::ORGANIZATION_ROLE_CHECK_IN_OFFICER,
            'status' => User::ORGANIZATION_STATUS_ACTIVE,
            'is_owner' => false,
            'joined_at' => now(),
        ]);
        $event->assignUser($officer, User::ORGANIZATION_ROLE_CHECK_IN_OFFICER);
        $this->actingAs($officer);

        $this->assertTrue(TicketCheckInResource::canViewAny());
        $this->assertFalse(TicketResource::canViewAny());
        $this->assertFalse(AdminTicketLookup::canAccess());
        $this->assertFalse(OrganizerSalesDashboard::canAccess());
        $this->assertFalse(TicketCapacityDashboard::canAccess());
        $this->assertFalse(PaymentReconciliation::canAccess());
        $this->assertFalse(AuditLogResource::canViewAny());
    }

    public function test_reconciliation_view_does_not_contain_manual_paid_override(): void
    {
        $view = file_get_contents(resource_path('views/filament/pages/payment-reconciliation.blade.php'));

        $this->assertStringNotContainsString('Mark Paid', $view);
        $this->assertStringNotContainsString('Force Success', $view);
        $this->assertStringContainsString('Re-sync with Pesapal', $view);
        $this->assertStringContainsString('Retry Fulfillment', $view);
    }
}
