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

    public function test_review_fails_when_reserved_amount_exceeds_current_settlement(): void
    {
        config(['finance.payout_requests_enabled' => true]);
        [$request, $requester, $reviewer] = $this->fixture('30.00');
        $this->expectException(ValidationException::class);
        app(EventPayoutReviewService::class)->review($request, $reviewer, true);
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
