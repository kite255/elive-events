<?php

namespace Tests\Feature\Finance;

use App\Models\Event;
use App\Models\EventPayoutRequest;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PayoutTransferProofAccessTest extends TestCase
{
    use RefreshDatabase;

    private function payoutWithProof(string $path = 'payout-proofs/test.pdf'): EventPayoutRequest
    {
        $org = Organization::create(['name' => 'Private Proof Organization']);
        $event = Event::create([
            'organization_id' => $org->id,
            'name' => 'Private Proof Event',
            'slug' => 'private-proof-'.uniqid(),
            'status' => Event::STATUS_ACTIVE,
        ]);
        $requester = User::factory()->create();
        $payout = EventPayoutRequest::create([
            'event_id' => $event->id,
            'requested_by' => $requester->id,
            'reference' => 'PROOF-'.uniqid(),
            'amount' => '100.00',
            'currency' => 'TZS',
            'destination_type' => 'bank',
            'destination_details' => 'Test destination',
        ]);
        $payout->forceFill(['status' => EventPayoutRequest::PAID, 'transfer_proof_path' => $path])->save();

        return $payout;
    }

    public function test_guest_cannot_download_private_payout_proof(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('payout-proofs/test.pdf', '%PDF-1.4');
        $payout = $this->payoutWithProof();

        $this->get(route('admin.payouts.transfer-proof', $payout))->assertRedirect();
    }

    public function test_organizer_cannot_download_private_payout_proof(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('payout-proofs/test.pdf', '%PDF-1.4');
        $payout = $this->payoutWithProof();
        $this->actingAs($payout->requester)
            ->get(route('admin.payouts.transfer-proof', $payout))
            ->assertForbidden();
    }

    public function test_super_admin_can_download_proof_and_access_is_audited(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('payout-proofs/test.pdf', '%PDF-1.4');
        $payout = $this->payoutWithProof();
        $admin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($admin)
            ->get(route('admin.payouts.transfer-proof', $payout))
            ->assertOk()
            ->assertDownload('payout-proof-'.$payout->id.'.pdf');

        $this->assertDatabaseHas('event_payout_audits', [
            'event_payout_request_id' => $payout->id,
            'actor_id' => $admin->id,
            'action' => 'proof_viewed',
        ]);
    }

    public function test_missing_proof_returns_not_found_without_access_audit(): void
    {
        Storage::fake('local');
        $payout = $this->payoutWithProof();
        $admin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($admin)
            ->get(route('admin.payouts.transfer-proof', $payout))
            ->assertNotFound();

        $this->assertDatabaseMissing('event_payout_audits', [
            'event_payout_request_id' => $payout->id,
            'action' => 'proof_viewed',
        ]);
    }
}
