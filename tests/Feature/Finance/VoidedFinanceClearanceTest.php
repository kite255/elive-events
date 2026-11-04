<?php

namespace Tests\Feature\Finance;

use App\Models\Event;
use App\Models\EventPayoutRequest;
use App\Models\Organization;
use App\Models\User;
use App\Services\EventFinanceClearanceService;
use App\Services\EventPayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class VoidedFinanceClearanceTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $organization = Organization::create(['name' => 'Void settlement test']);
        $event = Event::create([
            'organization_id' => $organization->id,
            'name' => 'Void settlement test event',
            'slug' => 'void-settlement-'.uniqid(),
            'status' => Event::STATUS_ACTIVE,
        ]);

        return [$admin, $event, app(EventFinanceClearanceService::class)];
    }

    public function test_void_keeps_record_and_excludes_it_from_duplicate_and_review_checks(): void
    {
        [$admin, $event, $service] = $this->fixture();
        $service->record($event, $admin, 'settlement', '550.00', 'BANK-FIRST-'.uniqid(), 'Original genuine receipt');
        $service->record($event, $admin, 'settlement', '550.00', 'BANK-DUPLICATE-'.uniqid(), 'Second entry entered by mistake');
        $entries = DB::table('event_finance_clearance_entries')->where('event_id', $event->id)->orderBy('id')->get();
        $firstId = $entries[0]->id;
        $duplicateId = $entries[1]->id;

        $service->voidEntry($duplicateId, $admin, 'Duplicate settlement accidentally recorded; checked the bank statement and retained original entry.');

        $this->assertDatabaseHas('event_finance_clearance_entries', [
            'id' => $duplicateId,
            'voided_by' => $admin->id,
            'amount' => 550,
        ]);
        $this->assertNotNull(DB::table('event_finance_clearance_entries')->where('id', $duplicateId)->value('voided_at'));
        $this->assertSame(2, DB::table('event_finance_clearance_entries')->where('event_id', $event->id)->count());

        $service->selfAttest($firstId, $admin, 'Checked the original bank settlement date and reference against actual receipt; no second super administrator exists.');
        $balance = app(EventPayoutService::class)->ticketBalance($event->id);
        $this->assertSame(550.0, $balance['self_attested_cleared_funds']);
        $this->assertSame(550.0, $balance['reviewed_cleared_funds']);
        $this->assertNull(DB::table('event_finance_clearance_entries')->where('id', $duplicateId)->value('self_attested_at'));
    }

    public function test_void_cannot_be_repeated(): void
    {
        [$admin, $event, $service] = $this->fixture();
        $service->record($event, $admin, 'settlement', '50.00', 'VOID-REPEAT-'.uniqid(), 'Incorrect payment entered');
        $id = (int) DB::table('event_finance_clearance_entries')->where('event_id', $event->id)->value('id');
        $service->voidEntry($id, $admin, 'Confirmed mistaken record from bank transaction reconciliation.');

        $this->expectException(ValidationException::class);
        $service->voidEntry($id, $admin, 'Attempting to void this entry a second time.');
    }

    public function test_reviewed_settlement_cannot_be_voided(): void
    {
        [$admin, $event, $service] = $this->fixture();
        $service->record($event, $admin, 'settlement', '80.00', 'VOID-REVIEW-'.uniqid(), 'Original genuine receipt');
        $id = (int) DB::table('event_finance_clearance_entries')->where('event_id', $event->id)->value('id');
        $service->selfAttest($id, $admin, 'Verified external bank statement including amount, date and receipt reference for this settlement.');

        $this->expectException(ValidationException::class);
        $service->voidEntry($id, $admin, 'Attempted alteration of a previously reviewed settlement entry.');
    }

    public function test_void_cannot_change_settlements_after_any_paid_payout(): void
    {
        [$admin, $event, $service] = $this->fixture();
        $service->record($event, $admin, 'settlement', '80.00', 'VOID-PAID-'.uniqid(), 'Original payment statement');
        $id = (int) DB::table('event_finance_clearance_entries')->where('event_id', $event->id)->value('id');
        EventPayoutRequest::create([
            'event_id' => $event->id,
            'requested_by' => $admin->id,
            'reference' => 'PAYOUT-VOID-'.uniqid(),
            'amount' => '10.00',
            'currency' => 'TZS',
            'destination_type' => 'bank',
            'destination_details' => 'Test destination',
        ])->forceFill(['status' => EventPayoutRequest::PAID, 'paid_at' => now()])->save();

        $this->expectException(ValidationException::class);
        $service->voidEntry($id, $admin, 'Attempted void despite a completed payout requiring reconciliation.');
    }

    public function test_non_superadmin_cannot_void_finance_entry(): void
    {
        [$admin, $event, $service] = $this->fixture();
        $regular = User::factory()->create(['is_super_admin' => false]);
        $service->record($event, $admin, 'settlement', '80.00', 'VOID-NONADMIN-'.uniqid(), 'Original statement entry');
        $id = (int) DB::table('event_finance_clearance_entries')->where('event_id', $event->id)->value('id');

        $this->expectException(ValidationException::class);
        $service->voidEntry($id, $regular, 'Attempted unauthorized void of a financial clearance entry.');
    }
}
