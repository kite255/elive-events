<?php

namespace Tests\Feature\Donations;

use App\Models\AuditLog;
use App\Models\Donation;
use App\Models\DonationCampaign;
use App\Models\DonationManualSubmission;
use App\Models\DonationPaymentMethod;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Donations\ManualDonationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DonationAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_submission_and_approval_are_audited_with_actor_and_event_context(): void
    {
        [$organization, $event, $campaign, $method, $donation] = $this->makeDonation();

        $admin = User::factory()->create();
        $organization->attachUser($admin, User::ORGANIZATION_ROLE_ADMIN);

        $service = app(ManualDonationService::class);

        $submission = $service->submit(
            $donation,
            $method,
            'MPESA-AUDIT-001',
            null
        );

        $service->approve($donation->fresh(), $admin);

        $submitted = AuditLog::query()
            ->where('action', 'donation.manual.submitted')
            ->where('subject_type', Donation::class)
            ->where('subject_id', $donation->id)
            ->first();

        $approved = AuditLog::query()
            ->where('action', 'donation.manual.approved')
            ->where('subject_type', Donation::class)
            ->where('subject_id', $donation->id)
            ->first();

        $this->assertNotNull($submitted);
        $this->assertSame($event->id, $submitted->event_id);
        $this->assertSame($donation->reference, $submitted->reference);
        $this->assertNull($submitted->actor_id);
        $this->assertSame($method->id, $submitted->metadata['payment_method_id']);
        $this->assertSame('MPESA-AUDIT-001', $submitted->metadata['transaction_reference']);

        $this->assertNotNull($approved);
        $this->assertSame($admin->id, $approved->actor_id);
        $this->assertSame($event->id, $approved->event_id);
        $this->assertSame(Donation::STATUS_AWAITING_VERIFICATION, $approved->before_data['status']);
        $this->assertSame(Donation::STATUS_COMPLETED, $approved->after_data['status']);
        $this->assertSame($submission->id, $approved->metadata['manual_submission_id']);
    }

    public function test_manual_rejection_is_audited(): void
    {
        [$organization, $event, $campaign, $method, $donation] = $this->makeDonation();

        $admin = User::factory()->create();
        $organization->attachUser($admin, User::ORGANIZATION_ROLE_ADMIN);

        DonationManualSubmission::query()->create([
            'donation_id' => $donation->id,
            'donation_payment_method_id' => $method->id,
            'transaction_reference' => 'MPESA-AUDIT-002',
            'submitted_at' => now(),
        ]);

        $donation->forceFill([
            'status' => Donation::STATUS_AWAITING_VERIFICATION,
        ])->save();

        app(ManualDonationService::class)->reject(
            $donation,
            $admin,
            'Could not verify transaction.'
        );

        $audit = AuditLog::query()
            ->where('action', 'donation.manual.rejected')
            ->where('subject_id', $donation->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame($admin->id, $audit->actor_id);
        $this->assertSame($event->id, $audit->event_id);
        $this->assertSame(Donation::STATUS_AWAITING_VERIFICATION, $audit->before_data['status']);
        $this->assertSame(Donation::STATUS_REJECTED, $audit->after_data['status']);
        $this->assertSame('Could not verify transaction.', $audit->metadata['reason']);
    }

    public function test_audit_service_removes_donation_and_gateway_secrets_recursively(): void
    {
        [, , , , $donation] = $this->makeDonation();

        $audit = app(AuditLogService::class)->record(
            'donation.security.test',
            $donation,
            null,
            [
                'safe' => 'visible',
                'proof_path' => 'donations/proofs/private.jpg',
                'public_token' => $donation->public_token,
                'private_token' => 'private-token-value',
                'provider_secret' => 'provider-secret-value',
                'gateway_credentials' => [
                    'api_key' => 'secret-api-key',
                    'consumer_secret' => 'secret-consumer',
                    'merchant_id' => 'safe-merchant-id',
                ],
            ]
        );

        $metadata = $audit->metadata;

        $this->assertSame('visible', $metadata['safe']);
        $this->assertArrayNotHasKey('proof_path', $metadata);
        $this->assertArrayNotHasKey('public_token', $metadata);
        $this->assertArrayNotHasKey('private_token', $metadata);
        $this->assertArrayNotHasKey('provider_secret', $metadata);
        $this->assertArrayNotHasKey('api_key', $metadata['gateway_credentials']);
        $this->assertArrayNotHasKey('consumer_secret', $metadata['gateway_credentials']);
        $this->assertSame('safe-merchant-id', $metadata['gateway_credentials']['merchant_id']);
    }

    private function makeDonation(): array
    {
        $organization = Organization::query()->create([
            'name' => 'Donation Audit Organization',
            'slug' => 'donation-audit-organization-' . uniqid(),
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Donation Audit Event',
            'slug' => 'donation-audit-event-' . uniqid(),
            'status' => Event::STATUS_ACTIVE,
        ]);

        $campaign = DonationCampaign::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'title' => 'Donation Audit Campaign',
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
            'account_name' => 'Donation Audit Campaign',
            'account_number_or_phone' => '255700000001',
            'enabled' => true,
            'sort_order' => 1,
        ]);

        $donation = Donation::query()->create([
            'donation_campaign_id' => $campaign->id,
            'reference' => 'ELV-DON-' . strtoupper(substr(md5(uniqid()), 0, 6)),
            'donor_name' => 'Audit Donor',
            'donor_phone' => '255700000002',
            'amount' => 15000,
            'currency' => 'TZS',
            'payment_type' => 'client_direct',
            'status' => Donation::STATUS_PENDING,
        ]);

        return [$organization, $event, $campaign, $method, $donation];
    }
}
