<?php

namespace Tests\Feature\Communications;

use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use App\Services\CampaignArtworkService;
use App\Services\CommunicationCampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class CampaignArtworkSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function png(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aWZkAAAAASUVORK5CYII=');
    }

    private function poster(int $eventId): string
    {
        $bytes = $this->png();
        $path = 'event-campaign-posters/'.$eventId.'/'.hash('sha256', $bytes).'.png';
        Storage::disk('public')->put($path, $bytes);

        return $path;
    }

    public function test_valid_event_poster_is_accepted_but_cross_event_reuse_is_denied(): void
    {
        Storage::fake('public');
        $path = $this->poster(1);
        $service = app(CampaignArtworkService::class);

        $this->assertTrue($service->validPoster(1, $path));
        $this->assertNotNull($service->posterUrl(1, $path));
        $this->assertFalse($service->validPoster(2, $path));
        $this->assertNull($service->posterUrl(2, $path));
    }

    public function test_unsafe_paths_and_legacy_shared_paths_are_rejected_without_deleting_files(): void
    {
        Storage::fake('public');
        $path = $this->poster(1);
        $legacy = 'event-campaign-posters/legacy.png';
        Storage::disk('public')->put($legacy, $this->png());
        $service = app(CampaignArtworkService::class);

        foreach ([$legacy, 'event-campaign-posters/2/../1/'.basename($path), '/'.$path,
            'https://example.com/'.$path, str_replace('/', '\\', $path), $path.'?x=1',
            'event-campaign-posters/1/'.str_repeat('a', 64).'.svg', null] as $invalid) {
            $this->assertFalse($service->validPoster(1, $invalid));
            $this->assertNull($service->posterUrl(1, $invalid));
        }
        Storage::disk('public')->assertExists($legacy);
        Storage::disk('public')->assertExists($path);
    }

    public function test_disguised_non_image_and_oversized_poster_are_rejected(): void
    {
        Storage::fake('public');
        $service = app(CampaignArtworkService::class);
        $text = 'not an image';
        $path = 'event-campaign-posters/1/'.hash('sha256', $text).'.png';
        Storage::disk('public')->put($path, $text);
        $this->assertFalse($service->validPoster(1, $path));

        $bytes = $this->png().str_repeat("\0", 10 * 1024 * 1024);
        $large = 'event-campaign-posters/1/'.hash('sha256', $bytes).'.png';
        Storage::disk('public')->put($large, $bytes);
        $this->assertFalse($service->validPoster(1, $large));
    }

    public function test_action_urls_require_https_and_no_embedded_credentials(): void
    {
        $service = app(CampaignArtworkService::class);
        $this->assertTrue($service->validActionUrl('https://open.spotify.com/album/example'));
        foreach (['http://example.com', 'javascript:alert(1)', '//example.com',
            'https://user:password@example.com', "https://example.com/\n", null] as $invalid) {
            $this->assertFalse($service->validActionUrl($invalid));
        }
    }

    public function test_queue_rejects_unrelated_artwork_and_unsafe_action_before_creating_campaign(): void
    {
        Storage::fake('public');
        Bus::fake();
        $org = Organization::create(['name' => 'Artwork test organization']);
        $event = Event::create([
            'organization_id' => $org->id,
            'name' => 'Artwork test event',
            'slug' => 'artwork-test-event',
            'status' => Event::STATUS_ACTIVE,
        ]);
        $this->actingAs(User::factory()->create(['is_super_admin' => true]));
        $service = app(CommunicationCampaignService::class);
        foreach ([
            fn () => $service->queueEmailCampaign($event, 'Notice', 'Subject', 'Hello', emailPosterPath: $this->poster($event->id + 1)),
            fn () => $service->queueEmailCampaign($event, 'Notice', 'Subject', 'Hello', emailActionUrl: 'http://example.com'),
        ] as $operation) {
            try {
                $operation();
                $this->fail('Invalid artwork was accepted.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('campaign', $exception->getMessage());
            }
        }
        $this->assertDatabaseCount('communication_campaigns', 0);
        $this->assertDatabaseCount('communication_logs', 0);
        Bus::assertNothingDispatched();
    }
}
