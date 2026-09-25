<?php

namespace Tests\Feature\Payments;

use App\Models\Event;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\TicketOrder;
use App\Models\User;
use App\Services\Payments\PaymentReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class PaymentReconciliationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_detects_completed_unfulfilled_and_amount_currency_mismatches(): void
    {
        [$event, $order] = $this->makeOrder();

        $payment = Payment::query()->create([
            'organization_id' => $event->organization_id,
            'event_id' => $event->id,
            'ticket_order_id' => $order->id,
            'reference' => 'PAY-' . strtoupper(uniqid()),
            'provider_tracking_id' => 'TRACK-' . strtoupper(uniqid()),
            'amount' => 9000,
            'currency' => 'USD',
            'status' => Payment::STATUS_COMPLETED,
            'initiated_at' => now()->subMinute(),
            'paid_at' => now(),
            'fulfilled_at' => null,
        ]);

        $rows = app(PaymentReconciliationService::class)->issuesForEvent($event);
        $row = $rows->firstWhere('payment_id', $payment->id);
        $codes = collect($row['issues'])->pluck('code')->all();

        $this->assertContains('amount_mismatch', $codes);
        $this->assertContains('currency_mismatch', $codes);
        $this->assertContains('completed_unfulfilled', $codes);
        $this->assertContains('tickets_missing', $codes);
        $this->assertContains('completed_payment_order_not_paid', $codes);
    }

    public function test_retry_fulfillment_rejects_payment_that_is_not_verified_completed(): void
    {
        [$event, $order] = $this->makeOrder();
        $admin = User::factory()->create(['is_super_admin' => true]);

        $payment = Payment::query()->create([
            'organization_id' => $event->organization_id,
            'event_id' => $event->id,
            'ticket_order_id' => $order->id,
            'reference' => 'PAY-PENDING-' . strtoupper(uniqid()),
            'provider_tracking_id' => 'TRACK-PENDING-' . strtoupper(uniqid()),
            'amount' => 10000,
            'currency' => 'TZS',
            'status' => Payment::STATUS_PROCESSING,
            'initiated_at' => now(),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Only verified completed payments can be fulfilled.');

        app(PaymentReconciliationService::class)
            ->retryFulfillment($payment, $admin);
    }

    public function test_pending_provider_payment_is_flagged_for_resynchronization(): void
    {
        [$event, $order] = $this->makeOrder();

        $payment = Payment::query()->create([
            'organization_id' => $event->organization_id,
            'event_id' => $event->id,
            'ticket_order_id' => $order->id,
            'reference' => 'PAY-SYNC-' . strtoupper(uniqid()),
            'provider_tracking_id' => 'TRACK-SYNC-' . strtoupper(uniqid()),
            'amount' => 10000,
            'currency' => 'TZS',
            'status' => Payment::STATUS_PROCESSING,
            'initiated_at' => now(),
        ]);

        $row = app(PaymentReconciliationService::class)
            ->issuesForEvent($event)
            ->firstWhere('payment_id', $payment->id);

        $codes = collect($row['issues'])->pluck('code')->all();

        $this->assertContains('pending_provider_verification', $codes);
        $this->assertTrue($row['can_resync']);
        $this->assertFalse($row['can_retry_fulfillment']);
    }

    private function makeOrder(): array
    {
        $organization = Organization::query()->create([
            'name' => 'Reconciliation Organization',
            'slug' => 'reconciliation-org-' . uniqid(),
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Reconciliation Event',
            'slug' => 'reconciliation-event-' . uniqid(),
            'status' => Event::STATUS_ACTIVE,
        ]);

        $order = TicketOrder::query()->create([
            'event_id' => $event->id,
            'order_number' => 'ELV-REC-' . strtoupper(uniqid()),
            'buyer_name' => 'Reconciliation Buyer',
            'buyer_phone' => '255700000001',
            'buyer_email' => 'reconcile@example.com',
            'quantity' => 1,
            'subtotal' => 10000,
            'discount_amount' => 0,
            'total' => 10000,
            'currency' => 'TZS',
            'status' => TicketOrder::STATUS_PROCESSING,
            'expires_at' => now()->addMinutes(15),
        ]);

        return [$event, $order];
    }
}
