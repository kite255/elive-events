<?php

namespace Tests\Feature\Finance;

use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use App\Services\EventFinanceClearanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EventFinanceClearanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_administrator_can_record_cleared_funds_and_reversal(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $event = $this->event();
        $service = app(EventFinanceClearanceService::class);
        $service->record($event, $admin, 'settlement', '1200.00', 'BANK-A-111', 'Verified bank statement 111');
        $service->record($event, $admin, 'refund', '200.00', 'BANK-A-112', 'Verified refund in statement');

        $this->assertSame(2, DB::table('event_finance_clearance_entries')->where('event_id', $event->id)->count());
        $this->assertDatabaseHas('event_finance_clearance_entries', ['external_reference' => 'BANK-A-112', 'entry_type' => 'refund']);
    }

    public function test_ordinary_user_cannot_record_settlements(): void
    {
        $user = User::factory()->create(['is_super_admin' => false]);
        $this->expectException(ValidationException::class);
        app(EventFinanceClearanceService::class)->record(
            $this->event(), $user, 'settlement', '1200.00', 'BANK-B-111', 'Verified bank statement 111'
        );
    }

    public function test_duplicate_external_evidence_is_rejected(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $event = $this->event();
        $service = app(EventFinanceClearanceService::class);
        $service->record($event, $admin, 'settlement', '500.00', 'BANK-C-111', 'Verified bank statement 111');

        $this->expectException(ValidationException::class);
        $service->record($event, $admin, 'settlement', '500.00', 'BANK-C-111', 'Verified bank statement 111');
    }

    public function test_second_admin_can_verify_but_recorder_cannot(): void
    {
        $recorder = User::factory()->create(['is_super_admin' => true]);
        $verifier = User::factory()->create(['is_super_admin' => true]);
        $event = $this->event();
        $service = app(EventFinanceClearanceService::class);
        $reference = 'REVIEW-'.uniqid();
        $service->record($event, $recorder, 'settlement', '500.00', $reference, 'Checked statement reference');
        $entryId = (int) DB::table('event_finance_clearance_entries')->where('external_reference', $reference)->value('id');

        try {
            $service->independentlyVerify($entryId, $recorder, 'Attempted to self-verify evidence');
            $this->fail('The original recorder must not verify their own entry.');
        } catch (ValidationException $exception) {
            $this->assertNull(DB::table('event_finance_clearance_entries')->where('id', $entryId)->value('independently_verified_at'));
        }

        $service->independentlyVerify($entryId, $verifier, 'Reviewed bank evidence independently');
        $this->assertDatabaseHas('event_finance_clearance_entries', [
            'id' => $entryId,
            'independently_verified_by' => $verifier->id,
        ]);
    }

    public function test_only_superadmin_may_self_attest_when_exactly_one_exists(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $event = $this->event();
        $service = app(EventFinanceClearanceService::class);
        $service->record($event, $admin, 'settlement', '100.00', 'BANK-SOLO-101', 'Bank statement reviewed for settlement');
        $id = (int) DB::table('event_finance_clearance_entries')->where('external_reference', 'BANK-SOLO-101')->value('id');

        $service->selfAttest($id, $admin, 'Checked external bank statement, settlement date and matching receipt reference; only one authorized administrator.');
        $entry = DB::table('event_finance_clearance_entries')->where('id', $id)->first();

        $this->assertSame($admin->id, (int) $entry->self_attested_by);
        $this->assertNotNull($entry->self_attested_at);
        $this->assertNull($entry->independently_verified_at);
        $this->assertSame('100.00', number_format((float) $entry->amount, 2, '.', ''));
        $balance = app(\App\Services\EventPayoutService::class)->ticketBalance($event->id);
        $this->assertSame(0.0, $balance['verified_cleared_funds']);
        $this->assertSame(100.0, $balance['self_attested_cleared_funds']);
    }

    public function test_self_attestation_fails_when_second_superadmin_exists(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        User::factory()->create(['is_super_admin' => true]);
        $event = $this->event();
        app(EventFinanceClearanceService::class)->record($event, $admin, 'settlement', '80.00', 'BANK-SOLO-102', 'Recorded from bank evidence');
        $id = (int) DB::table('event_finance_clearance_entries')->where('external_reference', 'BANK-SOLO-102')->value('id');

        $this->expectException(ValidationException::class);
        app(EventFinanceClearanceService::class)->selfAttest($id, $admin, str_repeat('Evidence independently unavailable; ', 2));
    }

    public function test_same_amount_duplicate_entries_cannot_be_self_attested(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $event = $this->event();
        $service = app(EventFinanceClearanceService::class);
        $service->record($event, $admin, 'settlement', '550.00', 'BANK-SOLO-103', 'First purported payment receipt');
        $service->record($event, $admin, 'settlement', '550.00', 'BANK-SOLO-104', 'Second purported payment receipt');
        $id = (int) DB::table('event_finance_clearance_entries')->where('external_reference', 'BANK-SOLO-103')->value('id');

        $this->expectException(ValidationException::class);
        $service->selfAttest($id, $admin, 'Reconciled settlement reference against the bank statement and verified receipt date.');
    }

    private function event(): Event
    {
        $organization = Organization::query()->create(['name' => 'Settlement Test Organization '.uniqid()]);

        return Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Settlement Test Event',
            'slug' => 'settlement-test-event-'.uniqid(),
            'status' => Event::STATUS_ACTIVE,
        ]);
    }
}
