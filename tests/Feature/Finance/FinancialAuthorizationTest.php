<?php

namespace Tests\Feature\Finance;

use App\Filament\Pages\OrganizerFinanceOverview;
use App\Filament\Resources\Payouts\PayoutResource;
use App\Models\Event;
use App\Models\EventPayoutRequest;
use App\Models\Organization;
use App\Models\User;
use App\Services\EventFinanceClearanceService;
use App\Services\EventPayoutReviewService;
use App\Services\EventPayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class FinancialAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $org = Organization::create(['name' => 'Finance authorization '.uniqid()]);
        $event = Event::create([
            'organization_id' => $org->id,
            'name' => 'Finance authorization event',
            'slug' => 'finance-auth-'.uniqid(),
            'status' => Event::STATUS_ACTIVE,
        ]);
        $owner = User::factory()->create(['is_super_admin' => false]);
        $owner->organizations()->attach($org->id, [
            'role' => User::ORGANIZATION_ROLE_OWNER,
            'status' => User::ORGANIZATION_STATUS_ACTIVE,
            'is_owner' => true,
            'joined_at' => now(),
        ]);
        $payout = EventPayoutRequest::create([
            'event_id' => $event->id,
            'requested_by' => $owner->id,
            'reference' => 'AUTH-'.uniqid(),
            'amount' => '60.00',
            'currency' => 'TZS',
            'destination_type' => 'bank',
            'destination_details' => 'Test only',
        ]);
        return [$event, $owner, $payout];
    }

    private function assertDenied(callable $operation, string $field): void
    {
        try {
            $operation();
            $this->fail('Unauthorized financial operation succeeded.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    public function test_gate_staff_with_super_admin_flag_cannot_view_payouts_or_organizer_finance(): void
    {
        [$event, $owner, $payout] = $this->fixture();
        $gate = User::factory()->create(['is_super_admin' => true, 'is_gate_staff' => true]);
        $this->actingAs($gate);

        $this->assertFalse(PayoutResource::canViewAny());
        $this->assertFalse(PayoutResource::canView($payout));
        $this->assertSame(0, PayoutResource::getEloquentQuery()->count());
        $this->assertFalse(OrganizerFinanceOverview::canAccess());
        $page = new OrganizerFinanceOverview();
        $page->selectedEventId = $event->id;
        $this->assertSame([], $page->eventOptions());
        $this->assertNull($page->payoutSummary());
        $this->assertSame(0.0, $page->financeMetrics()['gross_sales']);
    }

    public function test_organizer_cannot_access_another_organizations_payout_or_finance(): void
    {
        [$event, $owner, $payout] = $this->fixture();
        [$otherEvent, $otherOwner, $otherPayout] = $this->fixture();
        $this->actingAs($owner);

        $this->assertTrue(PayoutResource::canView($payout));
        $this->assertFalse(PayoutResource::canView($otherPayout));
        $this->assertSame(1, PayoutResource::getEloquentQuery()->count());
        $page = new OrganizerFinanceOverview();
        $this->assertArrayHasKey($event->id, $page->eventOptions());
        $this->assertArrayNotHasKey($otherEvent->id, $page->eventOptions());
        $page->selectedEventId = $otherEvent->id;
        $this->assertNull($page->payoutSummary());
    }

    public function test_gate_and_suspended_organizer_cannot_request_payouts(): void
    {
        [$event, $owner, $payout] = $this->fixture();
        config(['finance.payout_requests_enabled' => true]);
        $gate = User::factory()->create(['is_super_admin' => true, 'is_gate_staff' => true]);
        $service = app(EventPayoutService::class);
        $this->assertDenied(fn () => $service->request($event, $gate, 10, 'bank', 'Test only'), 'event');

        $owner->organizations()->updateExistingPivot($event->organization_id, [
            'status' => User::ORGANIZATION_STATUS_SUSPENDED,
        ]);
        $this->assertDenied(fn () => $service->request($event, $owner, 10, 'bank', 'Test only'), 'event');
        $this->assertDatabaseCount('event_payout_requests', 1);
        $this->assertDatabaseCount('event_payout_audits', 0);
    }

    public function test_gate_and_regular_user_cannot_review_or_record_transfers(): void
    {
        [$event, $owner, $payout] = $this->fixture();
        $gate = User::factory()->create(['is_super_admin' => true, 'is_gate_staff' => true]);
        $service = app(EventPayoutReviewService::class);
        config(['finance.payout_requests_enabled' => true]);
        Storage::fake('local');
        Storage::disk('local')->put('payout-proofs/test.pdf', '%PDF-1.4');

        foreach ([$gate, $owner] as $actor) {
            $this->assertDenied(fn () => $service->review($payout, $actor, true), 'review');
            $this->assertDenied(fn () => $service->review($payout, $actor, false, 'Unauthorized rejection attempt'), 'review');
        }
        $this->assertSame(EventPayoutRequest::REQUESTED, $payout->fresh()->status);
        $payout->forceFill(['status' => EventPayoutRequest::APPROVED])->save();
        foreach ([$gate, $owner] as $actor) {
            $this->assertDenied(fn () => $service->recordCompletedTransfer(
                $payout, $actor, 'AUTH-TRANSFER', 'bank', now()->toDateString(), '60.00', 'payout-proofs/test.pdf'
            ), 'transfer');
        }
        $this->assertSame(EventPayoutRequest::APPROVED, $payout->fresh()->status);
        $this->assertNull($payout->fresh()->paid_at);
        $this->assertDatabaseCount('event_payout_audits', 0);
    }

    public function test_gate_cannot_change_settlement_evidence(): void
    {
        [$event] = $this->fixture();
        $admin = User::factory()->create(['is_super_admin' => true]);
        $gate = User::factory()->create(['is_super_admin' => true, 'is_gate_staff' => true]);
        $service = app(EventFinanceClearanceService::class);
        $service->record($event, $admin, 'settlement', '100.00', 'AUTH-SETTLE', 'Original bank settlement evidence');
        $entry = DB::table('event_finance_clearance_entries')->where('event_id', $event->id)->first();
        $note = 'Checked bank settlement reference and amount against original statement evidence.';
        foreach ([
            fn () => $service->record($event, $gate, 'settlement', '50.00', 'AUTH-OTHER', $note),
            fn () => $service->independentlyVerify($entry->id, $gate, $note),
            fn () => $service->selfAttest($entry->id, $gate, $note),
            fn () => $service->voidEntry($entry->id, $gate, $note),
        ] as $operation) {
            try {
                $operation();
                $this->fail('Gate staff changed settlement evidence.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
        $this->assertDatabaseCount('event_finance_clearance_entries', 1);
        $this->assertEquals($entry, DB::table('event_finance_clearance_entries')->where('id', $entry->id)->first());
    }

    public function test_gate_super_admin_cannot_download_private_transfer_proof(): void
    {
        [$event, $owner, $payout] = $this->fixture();
        Storage::fake('local');
        Storage::disk('local')->put('payout-proofs/test.pdf', '%PDF-1.4');
        $payout->forceFill(['status' => EventPayoutRequest::PAID, 'transfer_proof_path' => 'payout-proofs/test.pdf'])->save();
        $gate = User::factory()->create(['is_super_admin' => true, 'is_gate_staff' => true]);
        $this->actingAs($gate)->get(route('admin.payouts.transfer-proof', $payout))->assertForbidden();
        $this->assertDatabaseMissing('event_payout_audits', [
            'event_payout_request_id' => $payout->id,
            'action' => 'proof_viewed',
        ]);
    }
}
