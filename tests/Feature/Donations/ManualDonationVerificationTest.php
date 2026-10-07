<?php

namespace Tests\Feature\Donations;

use App\Models\Donation;
use App\Models\DonationCampaign;
use App\Models\DonationManualSubmission;
use App\Models\DonationPaymentMethod;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use App\Services\Donations\ManualDonationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ManualDonationVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tracked_direct_donation_can_submit_reference_and_private_proof(): void
    {
        Storage::fake('local');

        [$organization, $event, $campaign, $method, $donation] = $this->makeTrackedDonation();

        $proof = UploadedFile::fake()->image('proof.jpg');

        $service = app(ManualDonationService::class);

        $submission = $service->submit(
            $donation,
            $method,
            'MPESA-ABC-123',
            $proof
        );

        $this->assertSame(
            Donation::STATUS_AWAITING_VERIFICATION,
            $donation->fresh()->status
        );

        $this->assertSame('MPESA-ABC-123', $submission->transaction_reference);
        $this->assertNotNull($submission->proof_path);
        $this->assertStringStartsWith('donations/proofs/', $submission->proof_path);

        Storage::disk('local')->assertExists($submission->proof_path);
        Storage::disk('public')->assertMissing($submission->proof_path);
    }

    public function test_authorized_organization_admin_can_approve_manual_donation_once(): void
    {
        [$organization, $event, $campaign, $method, $donation] = $this->makeTrackedDonation();

        $admin = User::factory()->create();
        $organization->attachUser($admin, User::ORGANIZATION_ROLE_ADMIN);

        $submission = DonationManualSubmission::query()->create([
            'donation_id' => $donation->id,
            'donation_payment_method_id' => $method->id,
            'transaction_reference' => 'REF-APPROVE-001',
            'submitted_at' => now(),
        ]);

        $service = app(ManualDonationService::class);

        $service->approve($donation, $admin);
        $service->approve($donation->fresh(), $admin);

        $freshDonation = $donation->fresh();
        $freshSubmission = $submission->fresh();

        $this->assertSame(Donation::STATUS_COMPLETED, $freshDonation->status);
        $this->assertNotNull($freshDonation->completed_at);
        $this->assertSame($admin->id, $freshSubmission->verified_by);
        $this->assertNotNull($freshSubmission->verified_at);
        $this->assertNull($freshSubmission->rejection_reason);
    }

    public function test_authorized_admin_can_reject_manual_donation(): void
    {
        [$organization, $event, $campaign, $method, $donation] = $this->makeTrackedDonation();

        $admin = User::factory()->create();
        $organization->attachUser($admin, User::ORGANIZATION_ROLE_ADMIN);

        $submission = DonationManualSubmission::query()->create([
            'donation_id' => $donation->id,
            'donation_payment_method_id' => $method->id,
            'transaction_reference' => 'REF-REJECT-001',
            'submitted_at' => now(),
        ]);

        $service = app(ManualDonationService::class);

        $service->reject($donation, $admin, 'Reference could not be verified.');

        $this->assertSame(
            Donation::STATUS_REJECTED,
            $donation->fresh()->status
        );

        $this->assertSame(
            'Reference could not be verified.',
            $submission->fresh()->rejection_reason
        );

        $this->assertSame(
            $admin->id,
            $submission->fresh()->verified_by
        );
    }

    public function test_unrelated_event_manager_cannot_approve_or_reject_manual_donation(): void
    {
        [$organization, $event, $campaign, $method, $donation] = $this->makeTrackedDonation();

        $otherEvent = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Other Managed Event',
            'slug' => 'other-managed-event-' . uniqid(),
            'status' => Event::STATUS_ACTIVE,
        ]);

        $manager = User::factory()->create();
        $organization->attachUser($manager, User::ORGANIZATION_ROLE_MEMBER);
        $otherEvent->assignUser($manager, User::ORGANIZATION_ROLE_EVENT_MANAGER);

        DonationManualSubmission::query()->create([
            'donation_id' => $donation->id,
            'donation_payment_method_id' => $method->id,
            'transaction_reference' => 'REF-DENY-001',
            'submitted_at' => now(),
        ]);

        $service = app(ManualDonationService::class);

        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);

        $service->approve($donation, $manager);
    }

    public function test_manual_submission_rejects_payment_method_from_another_campaign(): void
    {
        Storage::fake('local');

        [$organization, $event, $campaign, $method, $donation] = $this->makeTrackedDonation();

        $otherCampaign = DonationCampaign::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Other Campaign',
            'status' => DonationCampaign::STATUS_ACTIVE,
            'payment_mode' => DonationCampaign::PAYMENT_MODE_CLIENT_DIRECT,
            'direct_payment_behavior' => DonationCampaign::DIRECT_BEHAVIOR_TRACKED,
            'currency' => 'TZS',
            'is_public' => true,
        ]);

        $otherMethod = DonationPaymentMethod::query()->create([
            'donation_campaign_id' => $otherCampaign->id,
            'type' => 'bank',
            'provider_name' => 'CRDB',
            'account_name' => 'Other Campaign',
            'account_number_or_phone' => '0152000000000',
            'enabled' => true,
            'sort_order' => 1,
        ]);

        $service = app(ManualDonationService::class);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $service->submit(
            $donation,
            $otherMethod,
            'WRONG-CAMPAIGN',
            UploadedFile::fake()->image('proof.jpg')
        );
    }

    private function makeTrackedDonation(): array
    {
        $organization = Organization::query()->create([
            'name' => 'Manual Donation Organization',
            'slug' => 'manual-donation-organization-' . uniqid(),
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Manual Donation Event',
            'slug' => 'manual-donation-event-' . uniqid(),
            'status' => Event::STATUS_ACTIVE,
        ]);

        $campaign = DonationCampaign::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'title' => 'Manual Donation Campaign',
            'status' => DonationCampaign::STATUS_ACTIVE,
            'payment_mode' => DonationCampaign::PAYMENT_MODE_CLIENT_DIRECT,
            'direct_payment_behavior' => DonationCampaign::DIRECT_BEHAVIOR_TRACKED,
            'currency' => 'TZS',
            'is_public' => true,
        ]);

        $method = DonationPaymentMethod::query()->create([
            'donation_campaign_id' => $campaign->id,
            'type' => 'mobile_money',
            'provider_name' => 'M-Pesa',
            'account_name' => 'Manual Donation Campaign',
            'account_number_or_phone' => '255700000001',
            'enabled' => true,
            'sort_order' => 1,
        ]);

        $donation = Donation::query()->create([
            'donation_campaign_id' => $campaign->id,
            'reference' => 'ELV-DON-MAN-' . uniqid(),
            'donor_name' => 'Manual Donor',
            'donor_phone' => '255700000002',
            'amount' => 15000,
            'currency' => 'TZS',
            'payment_type' => 'client_direct',
            'status' => Donation::STATUS_PENDING,
        ]);

        return [$organization, $event, $campaign, $method, $donation];
    }
}
