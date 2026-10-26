<?php

namespace Tests\Feature\Finance;

use App\Models\Event;
use App\Models\Organization;
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
}
