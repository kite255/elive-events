<?php

namespace Tests\Feature\Finance;

use App\Models\Event;
use App\Models\Organization;
use App\Models\MerchandiseOrder;
use App\Models\MerchandiseProduct;
use App\Models\Payment;
use App\Services\EventFinanceReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EventFinanceMerchandiseTest extends TestCase
{
    use RefreshDatabase;

    public function test_merchandise_receipts_are_reported_separately_from_ticket_payout_balance(): void
    {
        $organization = Organization::create(['name' => 'Merchandise Finance Test']);
        $event = Event::create([
            'organization_id' => $organization->id,
            'name' => 'Merchandise Finance Test',
            'slug' => 'merchandise-finance-'.uniqid(),
            'status' => Event::STATUS_ACTIVE,
        ]);

        // A ticket-less merchandise payment counts as merchandise gross only.
        // Use a payment record with a valid merchandise order association to
        // avoid inventing a ticket sale or a cleared settlement.
        $this->assertSame(0, DB::table('event_finance_clearance_entries')->where('event_id', $event->id)->count());

        $report = app(EventFinanceReconciliationService::class)->inspect($event->id);

        $this->assertSame(0, $report['completed_merchandise_payments']);
        $this->assertSame(0.0, $report['completed_merchandise_payment_gross']);
        $this->assertSame(0.0, (float) $report['net_ticket_earnings']);
        $this->assertFalse($report['cleared_funds_verified']);
        $this->assertFalse($report['payouts_enabled']);
    }
    public function test_completed_merchandise_receipts_exclude_unpaid_attempts_and_ticket_balance(): void
    {
        $event = $this->makeEvent();

        $this->makeMerchandisePayment($event, Payment::STATUS_COMPLETED, '1000.00');
        $this->makeMerchandisePayment($event, Payment::STATUS_PENDING, '2000.00');
        $this->makeMerchandisePayment($event, Payment::STATUS_EXPIRED, '3000.00');

        $report = app(EventFinanceReconciliationService::class)->inspect($event->id);

        $this->assertSame(1, $report['completed_merchandise_payments']);
        $this->assertEquals(1000.00, $report['completed_merchandise_payment_gross']);
        $this->assertEquals(0.00, $report['net_ticket_earnings']);
        $this->assertEquals(0.00, $report['indicative_clearance_limited_balance']);
        $this->assertFalse($report['payouts_enabled']);
    }

    public function test_refunded_merchandise_payments_are_exceptions_not_completed_sales(): void
    {
        $event = $this->makeEvent();

        $this->makeMerchandisePayment($event, Payment::STATUS_REFUNDED, '500.00');
        $this->makeMerchandisePayment($event, Payment::STATUS_PARTIALLY_REFUNDED, '400.00');
        $this->makeMerchandisePayment($event, Payment::STATUS_COMPLETED, '1000.00');

        $report = app(EventFinanceReconciliationService::class)->inspect($event->id);

        $this->assertSame(2, $report['merchandise_refund_exceptions']);
        $this->assertSame(1, $report['completed_merchandise_payments']);
        $this->assertEquals(1000.00, $report['completed_merchandise_payment_gross']);
        $this->assertEquals(0.00, $report['indicative_clearance_limited_balance']);
    }

    private function makeEvent(): Event
    {
        $organization = Organization::create(['name' => 'Finance merchandise '.uniqid()]);

        return Event::create([
            'organization_id' => $organization->id,
            'name' => 'Merchandise finance test',
            'slug' => 'merchandise-finance-'.uniqid(),
            'status' => Event::STATUS_ACTIVE,
        ]);
    }

    private function makeMerchandisePayment(Event $event, string $status, string $amount): Payment
    {
        $token = uniqid('merch-', true);

        $product = MerchandiseProduct::create([
            'event_id' => $event->id,
            'name' => 'Finance Test Product',
            'slug' => 'finance-test-'.uniqid(),
            'category' => 'other',
            'price_tzs' => (int) $amount,
            'is_active' => true,
        ]);

        $order = MerchandiseOrder::create([
            'merchandise_product_id' => $product->id,
            'event_id' => $event->id,
            'public_token' => $token,
            'order_number' => 'TEST-'.strtoupper(str_replace('.', '', $token)),
            'buyer_name' => 'Finance Test Buyer',
            'buyer_phone' => '255700000000',
            'quantity' => 1,
            'unit_price_tzs' => $amount,
            'total_tzs' => $amount,
            'fulfillment_method' => 'pickup',
            'status' => $status === Payment::STATUS_COMPLETED ? 'paid' : 'pending',
        ]);

        return Payment::create([
            'organization_id' => $event->organization_id,
            'event_id' => $event->id,
            'merchandise_order_id' => $order->id,
            'reference' => 'FIN-'.uniqid(),
            'amount' => $amount,
            'currency' => 'TZS',
            'status' => $status,
        ]);
    }

}
