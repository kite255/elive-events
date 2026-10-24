<?php

namespace Tests\Feature\Finance;

use App\Models\Event;
use App\Models\EventPayoutRequest;
use App\Models\Organization;
use App\Models\User;
use App\Services\EventPayoutReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PayoutAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_review_writes_audit_row(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $request = $this->requestFor($admin);

        app(EventPayoutReviewService::class)->review($request, $admin, true, 'Reviewed');

        $this->assertDatabaseHas('event_payout_requests', [
            'id' => $request->id, 'status' => EventPayoutRequest::APPROVED,
        ]);
        $this->assertDatabaseHas('event_payout_audits', [
            'event_payout_request_id' => $request->id,
            'actor_id' => $admin->id,
            'action' => 'approved',
            'from_status' => EventPayoutRequest::REQUESTED,
            'to_status' => EventPayoutRequest::APPROVED,
        ]);
    }

    public function test_recording_transfer_creates_a_separate_audit_row(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $request = $this->requestFor($admin);
        app(EventPayoutReviewService::class)->review($request, $admin, true);
        app(EventPayoutReviewService::class)->recordCompletedTransfer($request, $admin, 'TEST-TRANSFER-42');

        $this->assertDatabaseHas('event_payout_audits', [
            'event_payout_request_id' => $request->id, 'actor_id' => $admin->id,
            'action' => 'paid', 'to_status' => EventPayoutRequest::PAID,
        ]);
        $this->assertSame(2, DB::table('event_payout_audits')->where('event_payout_request_id', $request->id)->count());
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

        return EventPayoutRequest::create([
            'event_id' => $event->id, 'requested_by' => $admin->id,
            'reference' => 'AUDIT-'.uniqid(),
            'amount' => '10.00', 'currency' => 'TZS',
            'destination_type' => 'bank',
            'destination_details' => 'Test destination',
        ]);
    }
}
