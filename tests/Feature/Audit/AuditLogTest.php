<?php

namespace Tests\Feature\Audit;

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Organization;
use App\Models\TicketOrder;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_log_records_actor_event_subject_and_metadata(): void
    {
        [$event, $order] = $this->makeOrder();
        $admin = User::factory()->create(['is_super_admin' => true]);

        $log = app(AuditLogService::class)->record(
            'ticket.access_resent',
            $order,
            $admin,
            ['channels' => ['sms']]
        );

        $this->assertSame('ticket.access_resent', $log->action);
        $this->assertSame($admin->id, $log->actor_id);
        $this->assertSame($event->id, $log->event_id);
        $this->assertSame(TicketOrder::class, $log->subject_type);
        $this->assertSame($order->id, $log->subject_id);
        $this->assertSame($order->order_number, $log->reference);
        $this->assertSame(['sms'], $log->metadata['channels']);
    }

    public function test_audit_log_redacts_sensitive_metadata_recursively(): void
    {
        [, $order] = $this->makeOrder();
        $admin = User::factory()->create(['is_super_admin' => true]);

        $log = app(AuditLogService::class)->record(
            'security.test',
            $order,
            $admin,
            [
                'payment_reference' => 'SAFE-REFERENCE',
                'pesapal_consumer_secret' => 'provider-secret',
                'whatsapp_access_token' => 'access-secret',
                'nested' => [
                    'qr_token_hash' => 'qr-secret',
                    'password_confirmation' => 'password-secret',
                    'safe' => 'visible',
                ],
            ]
        );

        $json = json_encode($log->metadata);

        $this->assertStringContainsString('SAFE-REFERENCE', $json);
        $this->assertStringContainsString('visible', $json);
        $this->assertStringNotContainsString('provider-secret', $json);
        $this->assertStringNotContainsString('access-secret', $json);
        $this->assertStringNotContainsString('qr-secret', $json);
        $this->assertStringNotContainsString('password-secret', $json);
    }

    public function test_ticket_organizer_audit_scope_is_limited_to_assigned_events(): void
    {
        [$assignedEvent, $assignedOrder] = $this->makeOrder('Assigned');
        [, $otherOrder] = $this->makeOrder('Other');
        $organizer = User::factory()->create(['is_super_admin' => false]);

        $assignedEvent->assignUser($organizer, User::ORGANIZATION_ROLE_TICKET_ORGANIZER);

        app(AuditLogService::class)->record('ticket.access_resent', $assignedOrder, null);
        app(AuditLogService::class)->record('ticket.access_resent', $otherOrder, null);

        $ids = AuditLog::query()
            ->accessibleBy($organizer)
            ->pluck('subject_id')
            ->all();

        $this->assertSame([$assignedOrder->id], $ids);
    }

    private function makeOrder(string $suffix = 'Audit'): array
    {
        $organization = Organization::query()->create([
            'name' => "{$suffix} Organization",
            'slug' => strtolower($suffix) . '-org-' . uniqid(),
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => "{$suffix} Event",
            'slug' => strtolower($suffix) . '-event-' . uniqid(),
            'status' => Event::STATUS_ACTIVE,
        ]);

        $order = TicketOrder::query()->create([
            'event_id' => $event->id,
            'order_number' => 'ELV-' . strtoupper($suffix) . '-' . strtoupper(uniqid()),
            'buyer_name' => 'Audit Buyer',
            'buyer_phone' => '255700000001',
            'buyer_email' => 'audit@example.com',
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
