<?php

namespace Tests\Feature\Finance;

use App\Models\Event;
use App\Models\EventPayoutRequest;
use App\Models\Organization;
use App\Models\TicketOrder;
use App\Models\User;
use App\Services\EventFinanceClearanceService;
use App\Services\EventPayoutReviewService;
use App\Services\EventPayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class SingleAdminPayoutTransferTest extends TestCase
{
    use RefreshDatabase;

    public function test_conditional_approval_requires_explicit_single_admin_attestation_before_transfer_recording(): void
    {
        config(['finance.payout_requests_enabled' => true]);
        $admin = User::factory()->create(['is_super_admin' => true]);
        $organizer = User::factory()->create(['is_super_admin' => false]);
        $organization = Organization::create(['name' => 'Single Admin Transfer Test']);
        $event = Event::create([
            'organization_id' => $organization->id,
            'name' => 'Single Admin Test Event',
            'slug' => 'single-admin-transfer-'.uniqid(),
            'status' => Event::STATUS_ACTIVE,
        ]);
        TicketOrder::create([
            'event_id' => $event->id,
            'order_number' => 'SOLO-'.uniqid(),
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
        $request = EventPayoutRequest::create([
            'event_id' => $event->id,
            'requested_by' => $organizer->id,
            'reference' => 'SOLO-PAYOUT-'.uniqid(),
            'amount' => '60.00',
            'currency' => 'TZS',
            'destination_type' => 'bank',
            'destination_details' => 'Test account',
        ]);
        $service = app(EventPayoutReviewService::class);
        $service->review($request, $admin, true);
        $this->assertSame(EventPayoutRequest::APPROVED, $request->fresh()->status);

        $clearance = app(EventFinanceClearanceService::class);
        $clearance->record($event, $admin, 'settlement', '100.00', 'SOLO-BANK-'.uniqid(), 'Original bank statement reviewed');
        $entryId = (int) DB::table('event_finance_clearance_entries')->where('event_id', $event->id)->value('id');

        Storage::fake('local');
        Storage::disk('local')->put('payout-proofs/solo.pdf', 'test');
        try {
            $service->recordCompletedTransfer(
                $request, $admin, 'SOLO-TRANSFER-BLOCKED', 'bank',
                now()->toDateString(), '60.00', 'payout-proofs/solo.pdf'
            );
            $this->fail('Transfer must not be recorded without reviewed settlement.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('transfer', $exception->errors());
        }

        $clearance->selfAttest(
            $entryId, $admin,
            'Checked real payment settlement amount, source, date and reference; no second super administrator exists.'
        );
        $balance = app(EventPayoutService::class)->ticketBalance($event->id);
        $this->assertSame(0.0, $balance['verified_cleared_funds']);
        $this->assertSame(100.0, $balance['self_attested_cleared_funds']);

        $paid = $service->recordCompletedTransfer(
            $request, $admin, 'SOLO-TRANSFER-PAID', 'bank',
            now()->toDateString(), '60.00', 'payout-proofs/solo.pdf'
        );
        $this->assertSame(EventPayoutRequest::PAID, $paid->status);
        $this->assertNotNull($paid->paid_at);
    }
}
