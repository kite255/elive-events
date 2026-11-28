<?php

namespace Tests\Feature;

use App\Models\Attendee;
use App\Models\Event;
use App\Models\EventCertificate;
use App\Models\Organization;
use App\Models\User;
use App\Services\EventCertificateAccess;
use App\Services\EventCertificateIssuance;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EventCertificateIssuanceTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(string $status = 'checked_in'): array
    {
        $user = User::factory()->create(['is_super_admin' => true, 'is_gate_staff' => false]);
        $organization = Organization::create(['name' => 'Certificate organization']);
        $event = Event::create(['organization_id' => $organization->id, 'name' => 'Certificate event', 'status' => Event::STATUS_ACTIVE]);
        app(EventCertificateAccess::class)->configure($user, $event->id, true, 'checked_in');
        $attendee = Attendee::create(['event_id' => $event->id, 'full_name' => 'Test Attendee', 'status' => $status]);
        $template = $event->certificateTemplates()->create([
            'name' => 'Participation', 'width' => 1123, 'height' => 794,
            'definition' => ['version' => 1, 'background_color' => '#FFFFFF', 'elements' => array_map(
                fn ($type) => ['type' => $type, 'x' => 20, 'y' => 20, 'width' => 100, 'height' => 100,
                    'font_size' => 20, 'color' => '#161943', 'font_family' => 'brand', 'font_weight' => 400,
                    'font_style' => 'normal', 'text_align' => 'center', 'line_height' => 1.2],
                ['attendee_name', 'certificate_number', 'qr']
            )],
        ]);
        app(\App\Services\EventCertificatePublishing::class)->publish($user, $event->id, $template->id);

        return [$user, $event, $attendee, $template];
    }

    public function test_preparation_creates_pending_snapshot_and_audit_only(): void
    {
        [$user, $event, $attendee, $template] = $this->fixture();
        $certificate = app(EventCertificateIssuance::class)->prepare($user, $event->id, $attendee->id, $template->id);
        $this->assertSame(EventCertificate::STATUS_PENDING, $certificate->status);
        $this->assertNull($certificate->issued_at);
        $this->assertNull($certificate->pdf_path);
        $this->assertNull($certificate->issued_by);
        $this->assertSame(64, strlen($certificate->verification_token));
        $this->assertSame('Test Attendee', $certificate->issuance_snapshot['attendee_name']);
        $attendee->update(['full_name' => 'Changed Name']);
        $this->assertSame('Test Attendee', $certificate->fresh()->issuance_snapshot['attendee_name']);
        $this->assertDatabaseHas('event_certificate_audits', [
            'event_certificate_id' => $certificate->id, 'actor_id' => $user->id, 'action' => 'prepared',
        ]);
    }

    public function test_pending_certificate_renders_pdf_without_becoming_issued(): void
    {
        [$user, $event, $attendee, $template] = $this->fixture();
        $certificate = app(EventCertificateIssuance::class)->prepare($user, $event->id, $attendee->id, $template->id);
        config(['app.url' => 'https://events.example.com']);
        $pdf = app(\App\Services\EventCertificatePdf::class)->render($certificate, now());

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertGreaterThan(1000, strlen($pdf));
        $this->assertSame(EventCertificate::STATUS_PENDING, $certificate->fresh()->status);
        $this->assertNull($certificate->fresh()->pdf_path);
        $this->assertNull($certificate->fresh()->issued_at);
    }

    public function test_duplicate_preparation_is_rejected(): void
    {
        [$user, $event, $attendee, $template] = $this->fixture();
        $service = app(EventCertificateIssuance::class);
        $service->prepare($user, $event->id, $attendee->id, $template->id);
        $this->expectException(ValidationException::class);
        $service->prepare($user, $event->id, $attendee->id, $template->id);
    }

    public function test_unchecked_attendee_is_ineligible(): void
    {
        [$user, $event, $attendee, $template] = $this->fixture('registered');
        $this->expectException(ModelNotFoundException::class);
        app(EventCertificateIssuance::class)->prepare($user, $event->id, $attendee->id, $template->id);
    }

    public function test_cross_event_attendee_is_rejected(): void
    {
        [$user, $event, $attendee, $template] = $this->fixture();
        $other = Event::create(['organization_id' => $event->organization_id, 'name' => 'Other event', 'status' => Event::STATUS_ACTIVE]);
        $outsider = Attendee::create(['event_id' => $other->id, 'full_name' => 'Other Attendee', 'status' => 'checked_in']);
        $this->expectException(ModelNotFoundException::class);
        app(EventCertificateIssuance::class)->prepare($user, $event->id, $outsider->id, $template->id);
    }
}
