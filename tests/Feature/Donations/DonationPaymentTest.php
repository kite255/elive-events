<?php

namespace Tests\Feature\Donations;

use App\Models\Donation;
use App\Models\DonationCampaign;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Services\Donations\DonationPaymentService;
use App\Services\Payments\PaymentFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class DonationPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_donation_creates_linked_pending_payment(): void
    {
        [$organization, $campaign, $donation] = $this->makePlatformDonation();

        $gateway = PaymentGateway::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Pesapal',
            'code' => 'pesapal',
            'is_enabled' => true,
            'is_default' => true,
            'environment' => 'sandbox',
            'configuration' => [],
        ]);

        $payment = app(DonationPaymentService::class)->create($donation);

        $this->assertSame($donation->id, $payment->donation_id);
        $this->assertSame($organization->id, $payment->organization_id);
        $this->assertNull($payment->event_id);
        $this->assertSame($gateway->id, $payment->payment_gateway_id);
        $this->assertSame('25000.00', $payment->amount);
        $this->assertSame('TZS', $payment->currency);
        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
        $this->assertTrue($payment->donation->is($donation));
        $this->assertTrue($donation->payments->contains($payment));
    }

    public function test_completed_donation_payment_fulfills_donation_once(): void
    {
        [$organization, $campaign, $donation] = $this->makePlatformDonation();

        $payment = Payment::query()->create([
            'organization_id' => $organization->id,
            'donation_id' => $donation->id,
            'reference' => 'ELV-DON-PAY-001',
            'amount' => 25000,
            'currency' => 'TZS',
            'status' => Payment::STATUS_COMPLETED,
            'paid_at' => now(),
        ]);

        $service = app(PaymentFulfillmentService::class);

        $service->fulfill($payment);
        $firstCompletedAt = $donation->fresh()->completed_at;

        $service->fulfill($payment->fresh());

        $this->assertSame(Donation::STATUS_COMPLETED, $donation->fresh()->status);
        $this->assertNotNull($firstCompletedAt);
        $this->assertTrue($firstCompletedAt->equalTo($donation->fresh()->completed_at));
        $this->assertNotNull($payment->fresh()->fulfilled_at);
    }

    public function test_fulfillment_rejects_amount_mismatch(): void
    {
        [$organization, $campaign, $donation] = $this->makePlatformDonation();

        $payment = Payment::query()->create([
            'organization_id' => $organization->id,
            'donation_id' => $donation->id,
            'reference' => 'ELV-DON-PAY-002',
            'amount' => 20000,
            'currency' => 'TZS',
            'status' => Payment::STATUS_COMPLETED,
            'paid_at' => now(),
        ]);

        $this->expectException(RuntimeException::class);

        app(PaymentFulfillmentService::class)->fulfill($payment);
    }

    private function makePlatformDonation(): array
    {
        $organization = Organization::query()->create([
            'name' => 'Donation Payment Organization',
            'slug' => 'donation-payment-organization-' . uniqid(),
        ]);

        $campaign = DonationCampaign::query()->create([
            'organization_id' => $organization->id,
            'title' => 'Online Donation Campaign',
            'status' => DonationCampaign::STATUS_ACTIVE,
            'payment_mode' => DonationCampaign::PAYMENT_MODE_PLATFORM,
            'currency' => 'TZS',
            'is_public' => true,
        ]);

        $donation = Donation::query()->create([
            'donation_campaign_id' => $campaign->id,
            'reference' => 'ELV-DON-' . strtoupper(substr(md5(uniqid()), 0, 6)),
            'donor_name' => 'Online Donor',
            'donor_phone' => '255700000010',
            'donor_email' => 'donor@example.com',
            'amount' => 25000,
            'currency' => 'TZS',
            'payment_type' => 'platform',
            'status' => Donation::STATUS_AWAITING_PAYMENT,
        ]);

        return [$organization, $campaign, $donation];
    }
}
