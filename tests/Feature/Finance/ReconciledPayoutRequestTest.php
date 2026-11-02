<?php

namespace Tests\Feature\Finance;

use App\Models\Event;
use App\Models\Organization;
use App\Models\TicketOrder;
use App\Models\User;
use App\Services\EventFinanceClearanceService;
use App\Services\EventPayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ReconciledPayoutRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_switch_rejects_payout_even_with_recorded_funds(): void
    {
        [$event, $admin] = $this->eventWithPaidTickets();
        app(EventFinanceClearanceService::class)->record(
            $event, $admin, 'settlement', '100.00', 'TEST-CLEAR-001', 'Independently checked statement'
        );
        $this->expectException(ValidationException::class);
        app(EventPayoutService::class)->request($event, $admin, 50, 'bank', 'Test account');
    }

    public function test_enabled_request_never_exceeds_unpaid_earnings_after_reservations(): void
    {
        config(['finance.payout_requests_enabled' => true]);
        [$event, $admin] = $this->eventWithPaidTickets();
        app(EventFinanceClearanceService::class)->record(
            $event, $admin, 'settlement', '80.00', 'TEST-CLEAR-002', 'Independently checked statement'
        );
        $unverifiedBalance = app(EventPayoutService::class)->ticketBalance($event->id);
        $this->assertSame(0.0, $unverifiedBalance['requestable_ticket_balance']);

        $verifier = User::factory()->create(['is_super_admin' => true]);
        $entryId = (int) DB::table('event_finance_clearance_entries')
            ->where('external_reference', 'TEST-CLEAR-002')->value('id');
        app(EventFinanceClearanceService::class)->independentlyVerify(
            $entryId, $verifier, 'Second administrator verified the settlement against provider evidence'
        );

        $first = app(EventPayoutService::class)->request($event, $admin, 60, 'bank', 'Test account');

        $this->assertSame('60.00', $first->amount);
        $this->assertSame(20.0, app(EventPayoutService::class)->ticketBalance($event->id)['requestable_ticket_balance']);
        $this->assertSame(40.0, app(EventPayoutService::class)->ticketBalance($event->id)['requestable_pending_verification_balance']);
        $this->expectException(ValidationException::class);
        app(EventPayoutService::class)->request($event, $admin, 50, 'bank', 'Test account');
    }

    public function test_request_can_be_submitted_before_settlement_but_cannot_be_approved(): void
    {
        config(['finance.payout_requests_enabled' => true]);
        [$event, $admin] = $this->eventWithPaidTickets();
        $balance = app(EventPayoutService::class)->ticketBalance($event->id);
        $this->assertSame(0.0, $balance['requestable_ticket_balance']);
        $this->assertSame(100.0, $balance['requestable_pending_verification_balance']);

        $request = app(EventPayoutService::class)->request($event, $admin, 60, 'bank', 'Test account');
        $this->assertSame('requested', $request->status);
        $this->assertSame(40.0, app(EventPayoutService::class)->ticketBalance($event->id)['requestable_pending_verification_balance']);
        $this->expectException(ValidationException::class);
        app(\App\Services\EventPayoutReviewService::class)->review(
            $request, User::factory()->create(['is_super_admin' => true]), true
        );
    }

    private function eventWithPaidTickets(): array
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $organization = Organization::create(['name' => 'Cleared Payout Test']);
        $event = Event::create([
            'organization_id' => $organization->id, 'name' => 'Cleared Funds Event',
            'slug' => 'cleared-funds-event-'.uniqid(), 'status' => Event::STATUS_ACTIVE,
        ]);
        TicketOrder::create([
            'event_id' => $event->id, 'order_number' => 'SETTLE-'.uniqid(),
            'buyer_name' => 'Test Buyer', 'buyer_phone' => '255700000111',
            'quantity' => 1, 'subtotal' => 100, 'total' => 100,
            'gross_amount' => 100, 'organizer_net_amount' => 100,
            'currency' => 'TZS', 'status' => TicketOrder::STATUS_PAID,
            'paid_at' => now(),
        ]);

        return [$event, $admin];
    }
}
