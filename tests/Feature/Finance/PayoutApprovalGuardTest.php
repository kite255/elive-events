<?php

namespace Tests\Feature\Finance;

use App\Models\Event;
use App\Models\EventPayoutRequest;
use App\Models\Organization;
use App\Models\TicketOrder;
use App\Models\User;
use App\Services\EventFinanceClearanceService;
use App\Services\EventPayoutReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PayoutApprovalGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_approval_is_blocked_while_feature_is_disabled(): void
    {
        [$request, $requester, $reviewer] = $this->fixture();
        $this->expectException(ValidationException::class);
        app(EventPayoutReviewService::class)->review($request, $reviewer, true);
    }

    public function test_requester_cannot_approve_own_request(): void
    {
        config(['finance.payout_requests_enabled' => true]);
        [$request, $requester] = $this->fixture();
        $this->expectException(ValidationException::class);
        app(EventPayoutReviewService::class)->review($request, $requester, true);
    }

    public function test_manual_approval_is_allowed_before_settlement_clearance(): void
    {
        config(['finance.payout_requests_enabled' => true]);
        [$request, $requester, $reviewer] = $this->fixture('30.00');
        $approved = app(EventPayoutReviewService::class)->review($request, $reviewer, true);
        $this->assertSame(EventPayoutRequest::APPROVED, $approved->status);
        $this->assertEquals(30.0, (float) \Illuminate\Support\Facades\DB::table('event_finance_clearance_entries')->where('event_id', $request->event_id)->value('amount'));
    }

    public function test_manual_approval_does_not_override_unverified_evidence(): void
    {
        config(['finance.payout_requests_enabled' => true]);
        [$request, $requester, $reviewer] = $this->fixture();
        \Illuminate\Support\Facades\DB::table('event_finance_clearance_entries')
            ->where('event_id', $request->event_id)->update([
                'independently_verified_at' => null,
                'independently_verified_by' => null,
                'independent_verification_note' => null,
            ]);
        $approved = app(EventPayoutReviewService::class)->review($request, $reviewer, true);
        $this->assertSame(EventPayoutRequest::APPROVED, $approved->status);
        $this->assertSame(0.0, app(\App\Services\EventPayoutService::class)->ticketBalance($request->event_id)['verified_cleared_funds']);
    }

    public function test_approval_succeeds_for_distinct_reviewer_with_sufficient_clearance(): void
    {
        config(['finance.payout_requests_enabled' => true]);
        [$request, $requester, $reviewer] = $this->fixture();
        $approved = app(EventPayoutReviewService::class)->review($request, $reviewer, true);

        $this->assertSame(EventPayoutRequest::APPROVED, $approved->status);
        $this->assertDatabaseHas('event_payout_audits', [
            'event_payout_request_id' => $request->id,
            'actor_id' => $reviewer->id,
            'action' => 'approved',
        ]);
    }

    public function test_unverified_settlement_blocks_transfer_after_manual_approval(): void
    {
        config(['finance.payout_requests_enabled' => true]);
        [$request, $requester, $reviewer] = $this->fixture();
        \Illuminate\Support\Facades\DB::table('event_finance_clearance_entries')
            ->where('event_id', $request->event_id)->update([
                'independently_verified_at' => null,
                'independently_verified_by' => null,
                'independent_verification_note' => null,
            ]);
        app(EventPayoutReviewService::class)->review($request, $reviewer, true);

        \Illuminate\Support\Facades\Storage::fake('local');
        \Illuminate\Support\Facades\Storage::disk('local')->put('payout-proofs/receipt.pdf', 'test proof');

        try {
            app(EventPayoutReviewService::class)->recordCompletedTransfer(
                $request, $reviewer, 'MANUAL-TEST-TRANSFER', 'bank',
                now()->toDateString(), '60.00', 'payout-proofs/receipt.pdf'
            );
            $this->fail('Transfer recording must require verified settlement.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('transfer', $exception->errors());
        }

        $this->assertSame(EventPayoutRequest::APPROVED, $request->fresh()->status);
        $this->assertNull($request->fresh()->paid_at);
    }

    private function fixture(string $settlement = '100.00'): array
    {
        $requester = User::factory()->create(['is_super_admin' => true]);
        $reviewer = User::factory()->create(['is_super_admin' => true]);
        $organization = Organization::create(['name' => 'Approval Guard '.uniqid()]);
        $event = Event::create([
            'organization_id' => $organization->id,
            'name' => 'Approval Guard Event',
            'slug' => 'approval-guard-'.uniqid(),
            'status' => Event::STATUS_ACTIVE,
        ]);

        TicketOrder::create([
            'event_id' => $event->id,
            'order_number' => 'APPROVAL-'.uniqid(),
            'buyer_name' => 'Test Buyer',
            'buyer_phone' => '255700000111',
            'quantity' => 1,
            'subtotal' => 100,
            'total' => 100,
            'gross_amount' => 100,
            'organizer_net_amount' => 100,
            'currency' => 'TZS',
            'status' => TicketOrder::STATUS_PAID,
            'paid_at' => now(),
        ]);
        $reference = 'APPROVAL-SETTLE-'.uniqid();
        app(EventFinanceClearanceService::class)->record(
            $event, $requester, 'settlement', $settlement,
            $reference, 'Independent test statement evidence'
        );
        $entryId = (int) \Illuminate\Support\Facades\DB::table('event_finance_clearance_entries')
            ->where('external_reference', $reference)->value('id');
        app(EventFinanceClearanceService::class)->independentlyVerify(
            $entryId, $reviewer, 'Verified second-person statement evidence'
        );

        $request = EventPayoutRequest::create([
            'event_id' => $event->id,
            'requested_by' => $requester->id,
            'reference' => 'APPROVAL-REQUEST-'.uniqid(),
            'amount' => '60.00',
            'currency' => 'TZS',
            'destination_type' => 'bank',
            'destination_details' => 'Test account',
        ]);

        return [$request, $requester, $reviewer];
    }
}
