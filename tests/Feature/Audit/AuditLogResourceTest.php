<?php

namespace Tests\Feature\Audit;

use App\Filament\Resources\AuditLogs\AuditLogResource;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Organization;
use App\Models\TicketOrder;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_resource_is_read_only_for_super_admin(): void
    {
        [, $order] = $this->makeOrder();
        $admin = User::factory()->create(['is_super_admin' => true]);
        app(AuditLogService::class)->record('ticket.access_resent', $order, $admin);
        $this->actingAs($admin);

        $record = AuditLogResource::getEloquentQuery()->firstOrFail();

        $this->assertTrue(AuditLogResource::canViewAny());
        $this->assertFalse(AuditLogResource::canCreate());
        $this->assertFalse(AuditLogResource::canEdit($record));
        $this->assertFalse(AuditLogResource::canDelete($record));
        $this->assertFalse(AuditLogResource::canDeleteAny());
    }

    public function test_check_in_officer_does_not_get_audit_log_resource(): void
    {
        [$event] = $this->makeOrder();
        $organization = $event->organization;
        $officer = User::factory()->create(['is_super_admin' => false]);

        $organization->users()->attach($officer->id, [
            'role' => User::ORGANIZATION_ROLE_CHECK_IN_OFFICER,
            'status' => User::ORGANIZATION_STATUS_ACTIVE,
            'is_owner' => false,
            'joined_at' => now(),
        ]);
        $event->assignUser($officer, User::ORGANIZATION_ROLE_CHECK_IN_OFFICER);
        $this->actingAs($officer);

        $this->assertFalse(AuditLogResource::canViewAny());
    }

    private function makeOrder(): array
    {
        $organization = Organization::query()->create([
            'name' => 'Audit Resource Organization',
            'slug' => 'audit-resource-org-' . uniqid(),
        ]);
        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Audit Resource Event',
            'slug' => 'audit-resource-event-' . uniqid(),
            'status' => Event::STATUS_ACTIVE,
        ]);
        $order = TicketOrder::query()->create([
            'event_id' => $event->id,
            'order_number' => 'AUD-RESOURCE-' . strtoupper(uniqid()),
            'buyer_name' => 'Audit Buyer',
            'quantity' => 1,
            'subtotal' => 10000,
            'discount_amount' => 0,
            'total' => 10000,
            'currency' => 'TZS',
            'status' => TicketOrder::STATUS_PAID,
            'paid_at' => now(),
        ]);

        return [$event, $order];
    }
}
