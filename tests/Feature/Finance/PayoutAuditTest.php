<?php

namespace Tests\Feature\Finance;

use App\Models\Event;
use App\Models\EventPayoutRequest;
use App\Models\Organization;
use App\Models\TicketOrder;
use App\Services\EventFinanceClearanceService;
use App\Models\User;
use App\Services\EventPayoutReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PayoutAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_review_writes_audit_row(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        config(['finance.payout_requests_enabled' => true]);
        $request = $this->requestFor($admin);
        $reviewer = User::factory()->create(['is_super_admin' => true]);

        app(EventPayoutReviewService::class)->review($request, $reviewer, true, 'Reviewed');

        $this->assertDatabaseHas('event_payout_requests', [
            'id' => $request->id, 'status' => EventPayoutRequest::APPROVED,
        ]);
        $this->assertDatabaseHas('event_payout_audits', [
            'event_payout_request_id' => $request->id,
            'actor_id' => $reviewer->id,
            'action' => 'approved',
            'from_status' => EventPayoutRequest::REQUESTED,
            'to_status' => EventPayoutRequest::APPROVED,
        ]);
    }

    public function test_recording_transfer_creates_a_separate_audit_row(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        config(['finance.payout_requests_enabled' => true]);
        $request = $this->requestFor($admin);
        $reviewer = User::factory()->create(['is_super_admin' => true]);
        app(EventPayoutReviewService::class)->review($request, $reviewer, true);
        Storage::fake('local');
        Storage::disk('local')->put('payout-proofs/receipt.pdf', 'test transfer receipt');
        app(EventPayoutReviewService::class)->recordCompletedTransfer(
            $request, $reviewer, 'TEST-TRANSFER-42', 'bank', now()->toDateString(),
            '10.00', 'payout-proofs/receipt.pdf'
        );
        $this->assertSame('bank', $request->fresh()->transfer_method);
        $this->assertSame('10.00', $request->fresh()->transferred_amount);

        $this->assertDatabaseHas('event_payout_audits', [
            'event_payout_request_id' => $request->id, 'actor_id' => $reviewer->id,
            'action' => 'paid', 'to_status' => EventPayoutRequest::PAID,
        ]);
        $this->assertSame(2, DB::table('event_payout_audits')->where('event_payout_request_id', $request->id)->count());
    }

    public function test_transfer_amount_mismatch_cannot_mark_payout_paid(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('payout-proofs/mismatch.pdf', 'test proof');
        $admin = User::factory()->create(['is_super_admin' => true]);
        $request = $this->requestFor($admin);
        $request->forceFill(['status' => EventPayoutRequest::APPROVED])->save();

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(EventPayoutReviewService::class)->recordCompletedTransfer(
            $request, $admin, 'TEST-MISMATCH-42', 'bank', now()->toDateString(),
            '9.00', 'payout-proofs/mismatch.pdf'
        );
    }

    private function requestFor(User $admin): EventPayoutRequest
    {
        $organization = Organization::create(['name' => 'Payout Audit Organization']);
        $event = Event::create([
            'organization_id' => $organization->id,
            'name' => 'Payout Audit Event',
            'slug' => 'payout-audit-event',
            'status' => Event::STATUS_ACTIVE,
        ]);

        TicketOrder::create([
            'event_id' => $event->id,
            'order_number' => 'AUDIT-ORDER-'.uniqid(),
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
        $reference = 'AUDIT-SETTLEMENT-'.uniqid();
        app(EventFinanceClearanceService::class)->record(
            $event, $admin, 'settlement', '100.00', $reference, 'Test evidence for audit workflow'
        );
        $verifier = User::factory()->create(['is_super_admin' => true]);
        $entryId = (int) DB::table('event_finance_clearance_entries')
            ->where('external_reference', $reference)->value('id');
        app(EventFinanceClearanceService::class)->independentlyVerify(
            $entryId, $verifier, 'Second administrator reviewed statement evidence'
        );

        return EventPayoutRequest::create([
            'event_id' => $event->id, 'requested_by' => $admin->id,
            'reference' => 'AUDIT-'.uniqid(),
            'amount' => '10.00', 'currency' => 'TZS',
            'destination_type' => 'bank',
            'destination_details' => 'Test destination',
        ]);
    }
}
