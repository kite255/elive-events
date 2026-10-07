<?php

namespace Tests\Feature\Payments;

use App\Models\Donation;
use App\Models\DonationCampaign;
use App\Models\Organization;
use App\Models\Payment;
use App\Services\Payments\PaymentReconciliationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DonationPaymentReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_completed_unfulfilled_donation_payment_is_reported_for_reconciliation(): void
    {
        [$organization, $campaign, $donation] = $this->makeDonation();

        $payment = Payment::query()->create([
            'organization_id' => $organization->id,
            'event_id' => null,
            'donation_id' => $donation->id,
            'reference' => 'ELV-DON-REC-001',
            'provider_tracking_id' => 'TRACK-DON-001',
            'amount' => 25000,
            'currency' => 'TZS',
            'status' => Payment::STATUS_COMPLETED,
            'paid_at' => now(),
            'fulfilled_at' => null,
        ]);

        $rows = app(PaymentReconciliationService::class)
            ->issuesForDonationCampaign($campaign);

        $this->assertCount(1, $rows);

        $row = $rows->first();

        $this->assertSame($payment->id, $row['payment_id']);
        $this->assertSame($donation->id, $row['donation_id']);
        $this->assertSame($donation->reference, $row['donation_reference']);
        $this->assertTrue($row['can_retry_fulfillment']);
        $this->assertTrue(
            collect($row['issues'])->contains(
                fn (array $issue): bool => $issue['code'] === 'completed_unfulfilled'
            )
        );
    }

    public function test_pending_online_donation_with_tracking_id_can_be_resynced(): void
    {
        [$organization, $campaign, $donation] = $this->makeDonation();

        $payment = Payment::query()->create([
            'organization_id' => $organization->id,
            'event_id' => null,
            'donation_id' => $donation->id,
            'reference' => 'ELV-DON-REC-002',
            'provider_tracking_id' => 'TRACK-DON-002',
            'amount' => 25000,
            'currency' => 'TZS',
            'status' => Payment::STATUS_PROCESSING,
        ]);

        $rows = app(PaymentReconciliationService::class)
            ->issuesForDonationCampaign($campaign);

        $row = $rows->firstWhere('payment_id', $payment->id);

        $this->assertNotNull($row);
        $this->assertTrue($row['can_resync']);
        $this->assertFalse($row['can_retry_fulfillment']);
        $this->assertTrue(
            collect($row['issues'])->contains(
                fn (array $issue): bool => $issue['code'] === 'pending_provider_verification'
            )
        );
    }

    public function test_reconciliation_is_scoped_to_requested_campaign(): void
    {
        [$organization, $campaign, $donation] = $this->makeDonation();

        $otherCampaign = DonationCampaign::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Other Reconciliation Campaign',
            'status' => DonationCampaign::STATUS_ACTIVE,
            'payment_mode' => DonationCampaign::PAYMENT_MODE_PLATFORM,
            'currency' => 'TZS',
            'is_public' => true,
        ]);

        $otherDonation = Donation::query()->create([
            'donation_campaign_id' => $otherCampaign->id,
            'reference' => 'ELV-DON-OTHER',
            'amount' => 40000,
            'currency' => 'TZS',
            'payment_type' => 'platform',
            'status' => Donation::STATUS_AWAITING_PAYMENT,
        ]);

        $ownPayment = Payment::query()->create([
            'organization_id' => $organization->id,
            'donation_id' => $donation->id,
            'reference' => 'ELV-DON-REC-003',
            'provider_tracking_id' => 'TRACK-DON-003',
            'amount' => 25000,
            'currency' => 'TZS',
            'status' => Payment::STATUS_PROCESSING,
        ]);

        Payment::query()->create([
            'organization_id' => $organization->id,
            'donation_id' => $otherDonation->id,
            'reference' => 'ELV-DON-REC-004',
            'provider_tracking_id' => 'TRACK-DON-004',
            'amount' => 40000,
            'currency' => 'TZS',
            'status' => Payment::STATUS_PROCESSING,
        ]);

        $rows = app(PaymentReconciliationService::class)
            ->issuesForDonationCampaign($campaign);

        $this->assertCount(1, $rows);
        $this->assertSame($ownPayment->id, $rows->first()['payment_id']);
    }

    private function makeDonation(): array
    {
        $organization = Organization::query()->create([
            'name' => 'Donation Reconciliation Organization',
            'slug' => 'donation-reconciliation-organization-' . uniqid(),
        ]);

        $campaign = DonationCampaign::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Donation Reconciliation Campaign',
            'status' => DonationCampaign::STATUS_ACTIVE,
            'payment_mode' => DonationCampaign::PAYMENT_MODE_PLATFORM,
            'currency' => 'TZS',
            'is_public' => true,
        ]);

        $donation = Donation::query()->create([
            'donation_campaign_id' => $campaign->id,
            'reference' => 'ELV-DON-' . strtoupper(substr(md5(uniqid()), 0, 6)),
            'donor_name' => 'Reconciliation Donor',
            'amount' => 25000,
            'currency' => 'TZS',
            'payment_type' => 'platform',
            'status' => Donation::STATUS_AWAITING_PAYMENT,
        ]);

        return [$organization, $campaign, $donation];
    }
}
