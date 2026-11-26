<?php

namespace Tests\Feature\Communications;

use App\Jobs\QueueEventCommunicationCampaignJob;
use App\Jobs\RetryCommunicationLogJob;
use App\Jobs\SendAutomaticCommunicationJob;
use App\Models\Attendee;
use App\Models\CommunicationCampaign;
use App\Models\CommunicationLog;
use App\Models\Event;
use App\Models\EventCommunication;
use App\Models\Organization;
use App\Models\User;
use App\Services\CommunicationCampaignService;
use App\Services\CommunicationRetryService;
use App\Services\EventCommunicationCampaignService;
use App\Services\SmsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use RuntimeException;
use Tests\TestCase;

class BackgroundCommunicationSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function event(): Event
    {
        $org = Organization::create(['name' => 'Background test '.uniqid()]);
        return Event::create([
            'organization_id' => $org->id,
            'name' => 'Background test event',
            'slug' => 'background-test-'.uniqid(),
            'status' => Event::STATUS_ACTIVE,
        ]);
    }

    private function campaign(): CommunicationCampaign
    {
        Bus::fake();
        $event = $this->event();
        Attendee::create([
            'event_id' => $event->id,
            'full_name' => 'Test recipient',
            'phone' => '0712345678',
            'email' => 'recipient@example.com',
            'status' => 'registered',
        ]);
        return app(CommunicationCampaignService::class)->queueSmsCampaign($event, 'Notice', 'Hello');
    }

    private function communication(Event $event): EventCommunication
    {
        return EventCommunication::create([
            'event_id' => $event->id,
            'type' => 'announcement',
            'title' => 'Scheduled notice',
            'body' => 'Event update',
            'status' => EventCommunication::STATUS_PUBLISHED,
            'is_public' => true,
            'published_at' => now(),
        ]);
    }

    public function test_cancelled_campaign_cannot_send(): void
    {
        $campaign = $this->campaign();
        $log = $campaign->logs()->sole();
        $campaign->markAsCancelled();
        $this->mock(SmsService::class)->shouldNotReceive('send');

        (new SendAutomaticCommunicationJob($log->id))->handle();

        $this->assertTrue($log->fresh()->isFailed());
        $this->assertTrue($campaign->fresh()->isCancelled());
    }

    public function test_changed_attendee_contact_cannot_receive_old_message(): void
    {
        $campaign = $this->campaign();
        $log = $campaign->logs()->sole();
        $log->attendee->update(['phone' => '0754123456']);
        $this->mock(SmsService::class)->shouldNotReceive('send');

        (new SendAutomaticCommunicationJob($log->id))->handle();

        $this->assertTrue($log->fresh()->isFailed());
    }

    public function test_cross_event_log_cannot_send(): void
    {
        $campaign = $this->campaign();
        $log = $campaign->logs()->sole();
        $log->update(['event_id' => $this->event()->id]);
        $this->mock(SmsService::class)->shouldNotReceive('send');

        (new SendAutomaticCommunicationJob($log->id))->handle();

        $this->assertTrue($log->fresh()->isFailed());
        $this->assertSame(0, $campaign->fresh()->sent_count);
    }

    public function test_second_execution_does_not_resend_claimed_or_sent_log(): void
    {
        $campaign = $this->campaign();
        $log = $campaign->logs()->sole();
        $log->markSending();
        $this->mock(SmsService::class)->shouldNotReceive('send');
        (new SendAutomaticCommunicationJob($log->id))->handle();
        $this->assertTrue($log->fresh()->isSending());

        $log->markSent('provider-test');
        (new SendAutomaticCommunicationJob($log->id))->handle();
        $this->assertTrue($log->fresh()->isSent());
        $this->assertSame('provider-test', $log->fresh()->provider_message_id);
    }

    public function test_scheduled_job_rejects_creator_who_became_gate_staff(): void
    {
        Bus::fake();
        $communication = $this->communication($this->event());
        $actor = User::factory()->create(['is_super_admin' => true]);
        $job = new QueueEventCommunicationCampaignJob($communication->id, 'sms', createdBy: $actor->id);
        $this->assertTrue($actor->fresh()->canManageEventCommunication($communication->event));
        $actor->forceFill(['is_gate_staff' => true])->save();
        $this->assertTrue($actor->fresh()->is_gate_staff);
        $this->assertFalse($actor->fresh()->canManageEventCommunication($communication->event));

        $job->handle(app(EventCommunicationCampaignService::class));

        $this->assertDatabaseCount('communication_campaigns', 0);
        Bus::assertNotDispatched(SendAutomaticCommunicationJob::class);
    }

    public function test_unrelated_user_cannot_schedule_communication(): void
    {
        Bus::fake();
        $communication = $this->communication($this->event());
        $actor = User::factory()->create(['is_super_admin' => false]);
        $this->actingAs($actor);
        try {
            app(EventCommunicationCampaignService::class)->schedule($communication, 'sms', 'all', null, now()->addHour(), $actor->id);
            $this->fail('Unauthorized schedule was accepted.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('not allowed', $exception->getMessage());
        }
        Bus::assertNotDispatched(QueueEventCommunicationCampaignJob::class);
    }

    public function test_retry_requires_permission_and_accepts_only_one_request(): void
    {
        $campaign = $this->campaign();
        $log = $campaign->logs()->sole();
        $log->markFailed('Test rejection');
        $service = app(CommunicationRetryService::class);
        $unrelated = User::factory()->create(['is_super_admin' => false]);
        $admin = User::factory()->create(['is_super_admin' => true]);
        $gate = User::factory()->create(['is_super_admin' => true, 'is_gate_staff' => true]);

        $this->assertFalse($service->queue($unrelated, $log->id));
        $this->assertFalse($service->queue($gate, $log->id));
        $this->assertTrue($log->fresh()->isFailed());
        $this->assertTrue($service->queue($admin, $log->id));
        $this->assertFalse($service->queue($admin, $log->id));
        $this->assertTrue($log->fresh()->isQueued());
        Bus::assertDispatchedTimes(RetryCommunicationLogJob::class, 1);
    }

    public function test_retry_does_not_reset_recorded_submission_evidence(): void
    {
        $campaign = $this->campaign();
        $log = $campaign->logs()->sole();
        $log->markSent('provider-test');
        $log->markFailed('Later provider error');
        $admin = User::factory()->create(['is_super_admin' => true]);

        $this->assertFalse(app(CommunicationRetryService::class)->queue($admin, $log->id));
        $this->assertSame('provider-test', $log->fresh()->provider_message_id);
        Bus::assertNotDispatched(RetryCommunicationLogJob::class);
    }

    public function test_interrupted_audit_is_read_only_and_filters_by_event(): void
    {
        $campaign = $this->campaign();
        $log = $campaign->logs()->sole();
        $log->markSending();
        CommunicationLog::query()->whereKey($log->id)->update(['updated_at' => now()->subMinutes(30)]);
        $before = $log->fresh()->getAttributes();

        $this->artisan('communications:audit-interrupted', ['--event' => $campaign->event_id])
            ->expectsOutputToContain('Unknown outcome')
            ->expectsOutputToContain('Read-only audit')
            ->assertSuccessful();

        $this->assertSame($before, $log->fresh()->getAttributes());
        $this->artisan('communications:audit-interrupted', ['--event' => $this->event()->id])
            ->doesntExpectOutputToContain('Unknown outcome')
            ->assertSuccessful();
        Bus::assertNotDispatched(RetryCommunicationLogJob::class);
    }
}
