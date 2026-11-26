<?php

namespace Tests\Feature\Communications;

use App\Filament\Pages\CommunicationCenter;
use App\Filament\Resources\CommunicationCampaigns\CommunicationCampaignResource;
use App\Jobs\SendAutomaticCommunicationJob;
use App\Models\Attendee;
use App\Models\CommunicationCampaign;
use App\Models\CommunicationLog;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use App\Services\CommunicationCampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use RuntimeException;
use Tests\TestCase;

class CampaignAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function event(): Event
    {
        $org = Organization::create(['name' => 'Campaign organization '.uniqid()]);
        return Event::create([
            'organization_id' => $org->id,
            'name' => 'Campaign event',
            'slug' => 'campaign-auth-'.uniqid(),
            'status' => Event::STATUS_ACTIVE,
        ]);
    }

    private function attendee(Event $event): Attendee
    {
        return Attendee::create([
            'event_id' => $event->id,
            'full_name' => 'Campaign recipient',
            'phone' => '0712345678',
            'email' => 'recipient@example.com',
            'status' => 'registered',
        ]);
    }

    private function assertRejected(callable $operation, string $message): void
    {
        try {
            $operation();
            $this->fail('Invalid campaign operation succeeded.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString($message, $exception->getMessage());
        }
    }

    private function assertNoCampaigns(): void
    {
        $this->assertDatabaseCount('communication_campaigns', 0);
        $this->assertDatabaseCount('communication_logs', 0);
        Bus::assertNotDispatched(SendAutomaticCommunicationJob::class);
    }

    public function test_unrelated_user_cannot_preview_or_queue_campaign(): void
    {
        Bus::fake();
        $event = $this->event();
        $this->actingAs(User::factory()->create(['is_super_admin' => false]));
        $service = app(CommunicationCampaignService::class);
        $this->assertRejected(fn () => $service->preview($event), 'not allowed');
        $this->assertRejected(fn () => $service->queueSmsCampaign($event, 'Notice', 'Hello'), 'not allowed');
        $this->assertNoCampaigns();
    }

    public function test_gate_super_admin_cannot_access_or_dispatch_campaigns(): void
    {
        Bus::fake();
        $event = $this->event();
        $gate = User::factory()->create(['is_super_admin' => true, 'is_gate_staff' => true]);
        $this->actingAs($gate);
        $this->assertFalse(CommunicationCenter::canAccess());
        $this->assertFalse(CommunicationCampaignResource::canViewAny());
        $service = app(CommunicationCampaignService::class);
        $this->assertRejected(fn () => $service->preview($event), 'not allowed');
        $this->assertRejected(fn () => $service->queueSmsCampaign($event, 'Notice', 'Hello', createdBy: $gate->id), 'not allowed');
        $this->assertNoCampaigns();
    }

    public function test_authenticated_creator_cannot_impersonate_another_admin(): void
    {
        Bus::fake();
        $event = $this->event();
        $this->actingAs(User::factory()->create(['is_super_admin' => true]));
        $other = User::factory()->create(['is_super_admin' => true]);
        $this->assertRejected(fn () => app(CommunicationCampaignService::class)->queueSmsCampaign(
            $event, 'Notice', 'Hello', createdBy: $other->id
        ), 'not allowed');
        $this->assertNoCampaigns();
    }

    public function test_invalid_audience_and_category_cannot_expand_recipient_selection(): void
    {
        Bus::fake();
        $event = $this->event();
        $this->actingAs(User::factory()->create(['is_super_admin' => true]));
        $service = app(CommunicationCampaignService::class);
        $this->assertRejected(fn () => $service->preview($event, 'unknown'), 'Unsupported campaign audience');
        $this->assertRejected(fn () => $service->queueSmsCampaign($event, 'Notice', 'Hello', 'unknown'), 'Unsupported campaign audience');
        $this->assertRejected(fn () => $service->queueSmsCampaign($event, 'Notice', 'Hello', categoryId: 999999), 'selected category');
        $this->assertNoCampaigns();
    }

    public function test_attendee_campaign_is_event_scoped_and_duplicate_submission_reuses_campaign(): void
    {
        Bus::fake();
        $event = $this->event();
        $other = $this->event();
        $attendee = $this->attendee($event);
        $this->attendee($other);
        $this->actingAs(User::factory()->create(['is_super_admin' => true]));
        $service = app(CommunicationCampaignService::class);
        $this->assertSame(1, $service->preview($event)['valid']);
        $first = $service->queueSmsCampaign($event, 'Notice', 'Hello');
        $second = $service->queueSmsCampaign($event, 'Notice', 'Hello');
        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $first->queued_count);
        $this->assertEquals($attendee->id, $first->logs()->sole()->attendee_id);
        $this->assertDatabaseCount('communication_campaigns', 1);
        $this->assertDatabaseCount('communication_logs', 1);
        Bus::assertDispatchedTimes(SendAutomaticCommunicationJob::class, 1);

        $otherCampaign = $service->queueSmsCampaign($other, 'Notice', 'Hello');
        $this->assertNotEquals($first->id, $otherCampaign->id);
    }

    public function test_completed_attendee_campaign_can_be_repeated_after_cooldown(): void
    {
        Bus::fake();
        config(['messaging.campaign_duplicate_cooldown_seconds' => 60]);
        $event = $this->event();
        $this->attendee($event);
        $this->actingAs(User::factory()->create(['is_super_admin' => true]));
        $service = app(CommunicationCampaignService::class);
        $first = $service->queueSmsCampaign($event, 'Notice', 'Hello');
        $first->forceFill(['status' => CommunicationCampaign::STATUS_COMPLETED, 'sent_count' => 1, 'completed_at' => now()])->save();

        $this->assertSame($first->id, $service->queueSmsCampaign($event, 'Notice', 'Hello')->id);
        $this->travel(61)->seconds();
        $second = $service->queueSmsCampaign($event, 'Notice', 'Hello');
        $this->assertNotEquals($first->id, $second->id);
        $this->assertDatabaseCount('communication_campaigns', 2);
        Bus::assertDispatchedTimes(SendAutomaticCommunicationJob::class, 2);
    }
}
