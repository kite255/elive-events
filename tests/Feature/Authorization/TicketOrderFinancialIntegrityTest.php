<?php

namespace Tests\Feature\Authorization;

use App\Filament\Resources\TicketOrders\TicketOrderResource;
use App\Models\Event;
use App\Models\Organization;
use App\Models\TicketOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketOrderFinancialIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_but_cannot_generically_mutate_orders(): void
    {
        $order = $this->order();
        $this->actingAs(User::factory()->create(['is_super_admin' => true]));
        $this->assertTrue(TicketOrderResource::canViewAny());
        $this->assertTrue(TicketOrderResource::getEloquentQuery()->whereKey($order->id)->exists());
        $this->assertMutationDenied($order);
        $this->get(TicketOrderResource::getUrl('index'))->assertOk();
        $this->get(TicketOrderResource::getUrl('create'))->assertForbidden();
        $this->get(TicketOrderResource::getUrl('edit', ['record' => $order]))->assertForbidden();
        $this->assertSame(TicketOrder::STATUS_PENDING, $order->fresh()->status);
    }

    public function test_organization_admin_cannot_generically_mutate_own_orders(): void
    {
        $order = $this->order();
        $user = User::factory()->create(['is_super_admin' => false]);
        $user->organizations()->attach($order->event->organization_id, [
            'role' => User::ORGANIZATION_ROLE_ADMIN,
            'status' => User::ORGANIZATION_STATUS_ACTIVE,
            'is_owner' => false,
            'joined_at' => now(),
        ]);
        $this->actingAs($user);
        $this->assertTrue(TicketOrderResource::canViewAny());
        $this->assertTrue(TicketOrderResource::getEloquentQuery()->whereKey($order->id)->exists());
        $this->assertMutationDenied($order);
        $this->get(TicketOrderResource::getUrl('create'))->assertForbidden();
        $this->get(TicketOrderResource::getUrl('edit', ['record' => $order]))->assertForbidden();
    }

    public function test_gate_staff_with_super_admin_flag_cannot_query_orders(): void
    {
        $order = $this->order();
        $this->actingAs(User::factory()->create(['is_super_admin' => true, 'is_gate_staff' => true]));
        $this->assertFalse(TicketOrderResource::canViewAny());
        $this->assertFalse(TicketOrderResource::getEloquentQuery()->whereKey($order->id)->exists());
        $this->assertMutationDenied($order);
    }

    private function assertMutationDenied(TicketOrder $order): void
    {
        $this->assertFalse(TicketOrderResource::canCreate());
        $this->assertFalse(TicketOrderResource::canEdit($order));
        $this->assertFalse(TicketOrderResource::canDelete($order));
        $this->assertFalse(TicketOrderResource::canDeleteAny());
    }

    private function order(): TicketOrder
    {
        $organization = Organization::create(['name' => 'Order Integrity Organization']);
        $event = Event::create(['organization_id' => $organization->id, 'name' => 'Order Integrity Event', 'slug' => 'order-integrity-event', 'status' => Event::STATUS_ACTIVE]);

        return TicketOrder::create([
            'event_id' => $event->id,
            'order_number' => 'ORD-INTEGRITY-001',
            'buyer_name' => 'Test Buyer',
            'buyer_phone' => '255700000001',
            'quantity' => 1,
            'subtotal' => 50000,
            'discount_amount' => 0,
            'total' => 50000,
            'currency' => 'TZS',
            'status' => TicketOrder::STATUS_PENDING,
        ]);
    }
}
