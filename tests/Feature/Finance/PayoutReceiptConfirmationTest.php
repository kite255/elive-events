<?php

namespace Tests\Feature\Finance;

use App\Models\Event;
use App\Models\EventPayoutRequest;
use App\Models\Organization;
use App\Models\User;
use App\Services\EventPayoutReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class PayoutReceiptConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_requester_can_confirm_a_recorded_transfer_once(): void
    {
        $requester = User::factory()->create();
        $other = User::factory()->create();
        $organization = Organization::create(['name' => 'Receipt Test Org']);
        $requester->organizations()->attach($organization->id, [
            'role' => User::ORGANIZATION_ROLE_ADMIN,
            'status' => User::ORGANIZATION_STATUS_ACTIVE,
            'is_owner' => false,
            'joined_at' => now(),
        ]);
        $event = Event::create([
            'organization_id' => $organization->id,
            'name' => 'Receipt Test Event',
            'slug' => 'receipt-test-event',
            'status' => Event::STATUS_ACTIVE,
        ]);
        $request = EventPayoutRequest::query()->create([
            'event_id' => $event->id,
            'requested_by' => $requester->id,
            'reference' => 'RECEIPT-TEST-001',
            'amount' => '200.00',
            'currency' => 'TZS',
            'destination_type' => 'bank',
            'destination_details' => 'Test only',
        ]);
        $request->forceFill(['status' => EventPayoutRequest::PAID, 'paid_at' => now(), 'transfer_reference' => 'TEST-TRANSFER-001'])->save();

        try {
            app(EventPayoutReviewService::class)->confirmReceipt($request, $other);
            $this->fail('Unrelated users must not confirm receipt.');
        } catch (ValidationException $exception) {
            $this->assertNull($request->fresh()->received_at);
        }

        $requester->organizations()->updateExistingPivot($organization->id, [
            'status' => User::ORGANIZATION_STATUS_SUSPENDED,
        ]);
        try {
            app(EventPayoutReviewService::class)->confirmReceipt($request, $requester);
            $this->fail('Suspended requesters must not confirm receipt.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('receipt', $exception->errors());
            $this->assertNull($request->fresh()->received_at);
        }

        $requester->organizations()->updateExistingPivot($organization->id, [
            'status' => User::ORGANIZATION_STATUS_ACTIVE,
        ]);
        $requester->forceFill(['is_gate_staff' => true])->save();
        try {
            app(EventPayoutReviewService::class)->confirmReceipt($request, $requester);
            $this->fail('Gate staff must not confirm receipt.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('receipt', $exception->errors());
            $this->assertNull($request->fresh()->received_at);
        }
        $requester->forceFill(['is_gate_staff' => false])->save();

        app(EventPayoutReviewService::class)->confirmReceipt($request, $requester);
        $this->assertNotNull($request->fresh()->received_at);
        $this->assertDatabaseHas('event_payout_audits', [
            'event_payout_request_id' => $request->id,
            'actor_id' => $requester->id,
            'action' => 'receipt_confirmed',
        ]);

        $this->expectException(ValidationException::class);
        app(EventPayoutReviewService::class)->confirmReceipt($request, $requester);
    }

    public function test_pending_transfer_cannot_be_confirmed_received(): void
    {
        $requester = User::factory()->create();
        $organization = Organization::create(['name' => 'Receipt Pending Org']);
        $requester->organizations()->attach($organization->id, [
            'role' => User::ORGANIZATION_ROLE_ADMIN,
            'status' => User::ORGANIZATION_STATUS_ACTIVE,
            'is_owner' => false,
            'joined_at' => now(),
        ]);
        $event = Event::create([
            'organization_id' => $organization->id,
            'name' => 'Receipt Pending Event',
            'slug' => 'receipt-pending-event',
            'status' => Event::STATUS_ACTIVE,
        ]);
        $request = EventPayoutRequest::query()->create([
            'event_id' => $event->id,
            'requested_by' => $requester->id,
            'reference' => 'RECEIPT-TEST-002',
            'amount' => '200.00',
            'currency' => 'TZS',
            'destination_type' => 'bank',
            'destination_details' => 'Test only',
        ]);
        $this->expectException(ValidationException::class);
        app(EventPayoutReviewService::class)->confirmReceipt($request, $requester);
    }
}
