<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use App\Services\EventCertificateAccess;
use App\Services\EventCertificatePublishing;
use App\Services\EventCertificateTemplates;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Tests\TestCase;

class EventCertificatePublishingTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(bool $enabled = true): array
    {
        $user = User::factory()->create(['is_super_admin' => true, 'is_gate_staff' => false]);
        $organization = Organization::create(['name' => 'Certificate test organization']);
        $event = Event::create(['organization_id' => $organization->id, 'name' => 'Certificate event', 'status' => Event::STATUS_ACTIVE]);
        app(EventCertificateAccess::class)->configure($user, $event->id, $enabled, 'checked_in');
        $draft = $event->certificateTemplates()->create([
            'name' => 'Participation', 'width' => 1123, 'height' => 794,
            'definition' => ['version' => 1, 'background_color' => '#FFFFFF', 'elements' => [
                $this->element('attendee_name', 20),
                $this->element('certificate_number', 140),
                $this->element('qr', 260),
            ]],
        ]);

        return [$user, $event, $draft];
    }

    private function element(string $type, int $y): array
    {
        return [
            'type' => $type, 'text' => '', 'x' => 20, 'y' => $y,
            'width' => 100, 'height' => 100, 'font_size' => 20, 'color' => '#161943',
            'font_family' => 'brand', 'font_weight' => 400, 'font_style' => 'normal',
            'text_align' => 'center', 'line_height' => 1.2,
        ];
    }

    public function test_valid_draft_can_be_published_without_issuing_certificates(): void
    {
        [$user, $event, $draft] = $this->fixture();
        $published = app(EventCertificatePublishing::class)->publish($user, $event->id, $draft->id);
        $this->assertTrue($published->fresh()->is_active);
        $this->assertDatabaseCount('event_certificates', 0);
    }

    public function test_disabled_event_cannot_publish(): void
    {
        [$user, $event, $draft] = $this->fixture(false);
        try {
            app(EventCertificatePublishing::class)->publish($user, $event->id, $draft->id);
            $this->fail('Disabled event unexpectedly published.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
        $this->assertFalse($draft->fresh()->is_active);
    }

    public function test_required_fields_are_enforced(): void
    {
        [$user, $event, $draft] = $this->fixture();
        $definition = $draft->definition;
        $definition['elements'][2] = $this->element('text', 260);
        $draft->update(['definition' => $definition]);

        $this->expectException(ValidationException::class);
        app(EventCertificatePublishing::class)->publish($user, $event->id, $draft->id);
    }

    public function test_missing_image_is_rejected(): void
    {
        [$user, $event, $draft] = $this->fixture();
        $definition = $draft->definition;
        $definition['elements'][] = $this->element('image', 380) + ['asset_id' => 999999];
        $draft->update(['definition' => $definition]);

        $this->expectException(ValidationException::class);
        app(EventCertificatePublishing::class)->publish($user, $event->id, $draft->id);
    }

    public function test_published_template_cannot_be_edited(): void
    {
        [$user, $event, $draft] = $this->fixture();
        app(EventCertificatePublishing::class)->publish($user, $event->id, $draft->id);

        $this->expectException(ModelNotFoundException::class);
        app(EventCertificateTemplates::class)->save($user, $event->id, $draft->id, [
            'name' => 'Changed', 'width' => 1123, 'height' => 794,
        ]);
    }

    public function test_gate_staff_cannot_publish_even_with_super_admin_flag(): void
    {
        [$user, $event, $draft] = $this->fixture();
        $gate = User::factory()->create(['is_super_admin' => true, 'is_gate_staff' => true]);
        try {
            app(EventCertificatePublishing::class)->publish($gate, $event->id, $draft->id);
            $this->fail('Gate staff unexpectedly published.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertFalse($draft->fresh()->is_active);
    }
}
