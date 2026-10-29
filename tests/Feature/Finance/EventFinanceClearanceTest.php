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
