<?php

namespace Tests\Feature\Donations;

use App\Jobs\SendDonationConfirmationJob;
use App\Models\CommunicationLog;
use App\Models\Donation;
use App\Models\DonationCampaign;
use App\Models\Organization;
use App\Services\Donations\DonationConfirmationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DonationConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_available_channels_follow_campaign_settings_and_donor_contact(): void
    {
        [$campaign, $donation] = $this->makeDonation([
            'notification_settings' => [
                'email' => true,
                'sms' => true,
                'whatsapp' => true,
            ],
        ], [
            'donor_email' => 'donor@example.com',
            'donor_phone' => '255700000001',
            'status' => Donation::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        $channels = app(DonationConfirmationService::class)
            ->availableChannels($donation);

        $this->assertSame(
            [
                CommunicationLog::CHANNEL_EMAIL,
                CommunicationLog::CHANNEL_SMS,
                CommunicationLog::CHANNEL_WHATSAPP,
            ],
            $channels
        );
    }

    public function test_incomplete_donation_does_not_queue_confirmation(): void
    {
        Queue::fake();

        [$campaign, $donation] = $this->makeDonation([
            'notification_settings' => [
                'email' => true,
                'sms' => true,
            ],
        ], [
            'status' => Donation::STATUS_AWAITING_PAYMENT,
        ]);

        $logs = app(DonationConfirmationService::class)
            ->queue($donation, [
                CommunicationLog::CHANNEL_EMAIL,
                CommunicationLog::CHANNEL_SMS,
            ]);

        $this->assertCount(0, $logs);
        $this->assertDatabaseCount('communication_logs', 0);
        Queue::assertNothingPushed();
    }

    public function test_completed_donation_queues_configured_confirmation_channels_once(): void
    {
        Queue::fake();

        [$campaign, $donation] = $this->makeDonation([
            'notification_settings' => [
                'email' => true,
                'sms' => true,
                'whatsapp' => false,
            ],
        ], [
            'donor_email' => 'donor@example.com',
            'donor_phone' => '255700000002',
            'status' => Donation::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        $service = app(DonationConfirmationService::class);

        $first = $service->queue($donation, [
            CommunicationLog::CHANNEL_EMAIL,
            CommunicationLog::CHANNEL_SMS,
            CommunicationLog::CHANNEL_WHATSAPP,
        ]);

        $second = $service->queue($donation->fresh(), [
            CommunicationLog::CHANNEL_EMAIL,
            CommunicationLog::CHANNEL_SMS,
        ]);

        $this->assertCount(2, $first);
        $this->assertCount(0, $second);

        $this->assertDatabaseHas('communication_logs', [
            'donation_id' => $donation->id,
            'purpose' => CommunicationLog::PURPOSE_DONATION_CONFIRMATION,
            'channel' => CommunicationLog::CHANNEL_EMAIL,
            'recipient' => 'donor@example.com',
            'status' => CommunicationLog::STATUS_QUEUED,
        ]);

        $this->assertDatabaseHas('communication_logs', [
            'donation_id' => $donation->id,
            'purpose' => CommunicationLog::PURPOSE_DONATION_CONFIRMATION,
            'channel' => CommunicationLog::CHANNEL_SMS,
            'recipient' => '255700000002',
            'status' => CommunicationLog::STATUS_QUEUED,
        ]);

        $this->assertDatabaseMissing('communication_logs', [
            'donation_id' => $donation->id,
            'purpose' => CommunicationLog::PURPOSE_DONATION_CONFIRMATION,
            'channel' => CommunicationLog::CHANNEL_WHATSAPP,
        ]);

        Queue::assertPushed(
            SendDonationConfirmationJob::class,
            2
        );
    }

    public function test_channel_without_required_contact_is_skipped(): void
    {
        Queue::fake();

        [$campaign, $donation] = $this->makeDonation([
            'notification_settings' => [
                'email' => true,
                'sms' => true,
                'whatsapp' => true,
            ],
        ], [
            'donor_email' => null,
            'donor_phone' => null,
            'status' => Donation::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        $logs = app(DonationConfirmationService::class)
            ->queue($donation, [
                CommunicationLog::CHANNEL_EMAIL,
                CommunicationLog::CHANNEL_SMS,
                CommunicationLog::CHANNEL_WHATSAPP,
            ]);

        $this->assertCount(0, $logs);
        $this->assertDatabaseCount('communication_logs', 0);
        Queue::assertNothingPushed();
    }

    private function makeDonation(
        array $campaignOverrides = [],
        array $donationOverrides = []
    ): array {
        $organization = Organization::query()->create([
            'name' => 'Donation Confirmation Organization',
            'slug' => 'donation-confirmation-organization-' . uniqid(),
        ]);

        $campaign = DonationCampaign::query()->create(array_merge([
            'organization_id' => $organization->id,
            'title' => 'Donation Confirmation Campaign',
            'status' => DonationCampaign::STATUS_ACTIVE,
            'payment_mode' => DonationCampaign::PAYMENT_MODE_PLATFORM,
            'currency' => 'TZS',
            'is_public' => true,
            'notification_settings' => [
                'email' => true,
                'sms' => false,
                'whatsapp' => false,
            ],
        ], $campaignOverrides));

        $donation = Donation::query()->create(array_merge([
            'donation_campaign_id' => $campaign->id,
            'reference' => 'ELV-DON-' . strtoupper(substr(md5(uniqid()), 0, 6)),
            'donor_name' => 'Confirmation Donor',
            'donor_email' => 'default@example.com',
            'donor_phone' => '255700000003',
            'amount' => 20000,
            'currency' => 'TZS',
            'payment_type' => 'platform',
            'status' => Donation::STATUS_COMPLETED,
            'completed_at' => now(),
        ], $donationOverrides));

        return [$campaign, $donation];
    }
}
