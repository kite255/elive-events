<?php

namespace Tests\Feature\Finance;

use App\Models\Event;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\TicketOrder;
use App\Services\EventFinanceReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventFinancePaymentLinkageTest extends TestCase
{
    use RefreshDatabase;

    public function test_positive_paid_ticket_without_completed_payment_is_flagged_but_free_tickets_are_not(): void
    {
        $org = Organization::create(['name' => 'Linkage Test']);
        $event = Event::create([
            'organization_id' => $org->id, 'name' => 'Linkage Test Event',
            'slug' => 'linkage-'.uniqid(), 'status' => Event::STATUS_ACTIVE,
        ]);

        foreach (['1000.00', '0.00'] as $amount) {
            TicketOrder::create([
                'event_id' => $event->id, 'order_number' => 'LINK-'.uniqid(),
                'buyer_name' => 'Test Buyer', 'quantity' => 1,
                'subtotal' => $amount, 'total' => $amount,
                'status' => TicketOrder::STATUS_PAID,
            ]);
        }

        $result = app(EventFinanceReconciliationService::class)->inspect($event->id);
        $this->assertSame(1, $result['paid_tickets_missing_completed_payment']);
        $this->assertSame(0, $result['unclassified_completed_payments']);
        $this->assertFalse($result['cleared_funds_verified']);
        $this->assertFalse($result['payouts_enabled']);
    }

    public function test_unclassified_completed_payment_is_flagged_as_diagnostic_only(): void
    {
        $org = Organization::create(['name' => 'Unclassified Test']);
        $event = Event::create([
            'organization_id' => $org->id, 'name' => 'Unclassified Event',
            'slug' => 'unclassified-'.uniqid(), 'status' => Event::STATUS_ACTIVE,
        ]);
        Payment::create([
            'organization_id' => $org->id,
            'event_id' => $event->id,
            'reference' => 'LINK-'.uniqid(),
            'amount' => '1000.00', 'currency' => 'TZS',
            'status' => Payment::STATUS_COMPLETED,
        ]);

        $result = app(EventFinanceReconciliationService::class)->inspect($event->id);
        $this->assertSame(1, $result['unclassified_completed_payments']);
        $this->assertEquals(0, $result['indicative_clearance_limited_balance']);
        $this->assertFalse($result['payouts_enabled']);
    }
}
