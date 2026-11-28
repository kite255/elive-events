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
        $event = Event::create(['organization_id' => $organization->id, 'name' => 'Certificate event '.\Illuminate\Support\Str::uuid(), 'status' => Event::STATUS_ACTIVE]);
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

    public function test_finalization_stores_pdf_and_enables_public_verification(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        [$user, $event, $attendee, $template] = $this->fixture();
        $certificate = app(EventCertificateIssuance::class)->prepare($user, $event->id, $attendee->id, $template->id);
        $url = route('public.certificates.verify', ['token' => $certificate->verification_token]);
        $this->get($url)->assertNotFound();

        $issued = app(\App\Services\EventCertificateFinalization::class)->finalize($user, $event->id, $certificate->id);
        $this->assertSame(EventCertificate::STATUS_ISSUED, $issued->status);
        $this->assertEquals($user->id, $issued->issued_by);
        $this->assertNotNull($issued->issued_at);
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($issued->pdf_path);
        $this->assertStringStartsWith('%PDF-', \Illuminate\Support\Facades\Storage::disk('local')->get($issued->pdf_path));
        $this->assertDatabaseHas('event_certificate_audits', ['event_certificate_id' => $issued->id, 'action' => 'issued']);
        $this->get($url)->assertOk()->assertSee('Valid certificate')->assertSee('Test Attendee');
    }

    public function test_failed_render_leaves_certificate_pending(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        [$user, $event, $attendee, $template] = $this->fixture();
        $certificate = app(EventCertificateIssuance::class)->prepare($user, $event->id, $attendee->id, $template->id);
        config(['app.url' => 'invalid']);
        try {
            app(\App\Services\EventCertificateFinalization::class)->finalize($user, $event->id, $certificate->id);
            $this->fail('Invalid URL unexpectedly finalized.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
        $this->assertSame(EventCertificate::STATUS_PENDING, $certificate->fresh()->status);
        $this->assertNull($certificate->fresh()->pdf_path);
        $this->assertDatabaseMissing('event_certificate_audits', ['event_certificate_id' => $certificate->id, 'action' => 'issued']);
        $this->assertSame([], \Illuminate\Support\Facades\Storage::disk('local')->allFiles('certificates'));
    }

    public function test_changed_eligibility_blocks_finalization(): void
    {
        [$user, $event, $attendee, $template] = $this->fixture();
        $certificate = app(EventCertificateIssuance::class)->prepare($user, $event->id, $attendee->id, $template->id);
        $attendee->update(['status' => 'cancelled']);
        $this->expectException(ModelNotFoundException::class);
        app(\App\Services\EventCertificateFinalization::class)->finalize($user, $event->id, $certificate->id);
    }

    public function test_bulk_selection_dispatches_one_job_per_eligible_attendee(): void
    {
        \Illuminate\Support\Facades\Bus::fake();
        config(['queue.default' => 'database']);
        [$user, $event, $attendee, $template] = $this->fixture();
        $second = Attendee::create(['event_id' => $event->id, 'full_name' => 'Second attendee', 'status' => 'checked_in']);
        $count = app(\App\Services\EventCertificateBulkIssuance::class)->queue($user, $event->id, $template->id, [$attendee->id, $second->id]);
        $this->assertSame(2, $count);
        \Illuminate\Support\Facades\Bus::assertDispatchedTimes(\App\Jobs\IssueEventCertificate::class, 2);
        \Illuminate\Support\Facades\Bus::assertDispatched(\App\Jobs\IssueEventCertificate::class, fn ($job) =>
            $job->actorId === $user->id && $job->attendeeId === $attendee->id && $job->eventId === $event->id && $job->templateId === $template->id);
        $this->assertDatabaseCount('event_certificates', 0);
    }

    public function test_bulk_selection_rejects_ineligible_attendees_before_dispatch(): void
    {
        \Illuminate\Support\Facades\Bus::fake();
        config(['queue.default' => 'database']);
        [$user, $event, $attendee, $template] = $this->fixture('registered');
        try {
            app(\App\Services\EventCertificateBulkIssuance::class)->queue($user, $event->id, $template->id, [$attendee->id]);
            $this->fail('Ineligible attendee unexpectedly accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('attendees', $exception->errors());
        }
        \Illuminate\Support\Facades\Bus::assertNotDispatched(\App\Jobs\IssueEventCertificate::class);
    }

    public function test_queued_issuance_can_be_replayed_without_duplicate_certificate(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        [$user, $event, $attendee, $template] = $this->fixture();
        $job = new \App\Jobs\IssueEventCertificate($user->id, $event->id, $attendee->id, $template->id, 'checked_in');
        app()->call([$job, 'handle']);
        app()->call([$job, 'handle']);
        $this->assertDatabaseCount('event_certificates', 1);
        $this->assertDatabaseHas('event_certificates', ['attendee_id' => $attendee->id, 'status' => 'issued']);
    }

    public function test_batch_recovery_is_read_only_by_default_and_flags_stale_items_on_apply(): void
    {
        \Illuminate\Support\Facades\Bus::fake();
        config(['queue.default' => 'database']);
        [$user, $event, $attendee, $template] = $this->fixture();
        app(\App\Services\EventCertificateBulkIssuance::class)->queue($user, $event->id, $template->id, [$attendee->id]);
        $item = \Illuminate\Support\Facades\DB::table('event_certificate_batch_items')->first();
        \Illuminate\Support\Facades\DB::table('event_certificate_batch_items')->where('id', $item->id)->update(['updated_at' => now()->subHours(3)]);
        $recovery = app(\App\Services\EventCertificateBatchRecovery::class);
        $this->assertSame(1, $recovery->inspect($event->id)['stale']);
        $this->assertDatabaseHas('event_certificate_batch_items', ['id' => $item->id, 'status' => 'queued']);
        $this->assertSame(1, $recovery->inspect($event->id, true)['marked_failed']);
        $this->assertDatabaseHas('event_certificate_batch_items', ['id' => $item->id, 'status' => 'failed', 'failure_code' => 'stale_review_required']);
        \Illuminate\Support\Facades\Bus::assertDispatchedTimes(\App\Jobs\IssueEventCertificate::class, 1);
    }

    public function test_batch_recovery_reconciles_already_issued_certificate(): void
    {
        \Illuminate\Support\Facades\Bus::fake();
        \Illuminate\Support\Facades\Storage::fake('local');
        config(['queue.default' => 'database']);
        [$user, $event, $attendee, $template] = $this->fixture();
        app(\App\Services\EventCertificateBulkIssuance::class)->queue($user, $event->id, $template->id, [$attendee->id]);
        $item = \Illuminate\Support\Facades\DB::table('event_certificate_batch_items')->first();
        $certificate = app(EventCertificateIssuance::class)->prepare($user, $event->id, $attendee->id, $template->id);
        app(\App\Services\EventCertificateFinalization::class)->finalize($user, $event->id, $certificate->id);
        \Illuminate\Support\Facades\DB::table('event_certificate_batch_items')->where('id', $item->id)->update(['status' => 'processing', 'updated_at' => now()->subHours(3)]);
        $result = app(\App\Services\EventCertificateBatchRecovery::class)->inspect($event->id, true);
        $this->assertSame(1, $result['reconciled']);
        $this->assertDatabaseHas('event_certificate_batch_items', ['id' => $item->id, 'status' => 'issued']);
        $this->assertDatabaseCount('event_certificates', 1);
    }

    public function test_batch_retry_only_dispatches_failed_recipients(): void
    {
        \Illuminate\Support\Facades\Bus::fake();
        config(['queue.default' => 'database']);
        [$user, $event, $attendee, $template] = $this->fixture();
        $second = Attendee::create(['event_id' => $event->id, 'full_name' => 'Second attendee', 'status' => 'checked_in']);
        app(\App\Services\EventCertificateBulkIssuance::class)->queue($user, $event->id, $template->id, [$attendee->id, $second->id]);
        $batch = \Illuminate\Support\Facades\DB::table('event_certificate_batches')->first();
        \Illuminate\Support\Facades\DB::table('event_certificate_batch_items')->where('attendee_id', $attendee->id)->update(['status' => 'failed']);
        $this->assertSame(1, app(\App\Services\EventCertificateBatchRetry::class)->retry($user, $event->id, $batch->id));
        $this->assertDatabaseHas('event_certificate_batch_items', ['attendee_id' => $attendee->id, 'status' => 'queued']);
        \Illuminate\Support\Facades\Bus::assertDispatchedTimes(\App\Jobs\IssueEventCertificate::class, 3);
    }

    public function test_revocation_preserves_pdf_and_invalidates_public_verification(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        [$user, $event, $attendee, $template] = $this->fixture();
        $certificate = app(EventCertificateIssuance::class)->prepare($user, $event->id, $attendee->id, $template->id);
        $issued = app(\App\Services\EventCertificateFinalization::class)->finalize($user, $event->id, $certificate->id);
        $url = route('public.certificates.verify', ['token' => $issued->verification_token]);
        $original = \Illuminate\Support\Facades\Storage::disk('local')->get($issued->pdf_path);
        $revoked = app(\App\Services\EventCertificateRevocation::class)->revoke($user, $event->id, $issued->id, 'Issued to the wrong participant.');
        $this->assertSame('revoked', $revoked->status);
        $this->assertNotNull($revoked->revoked_at);
        $this->assertSame($issued->verification_token, $revoked->verification_token);
        $this->assertSame($issued->certificate_number, $revoked->certificate_number);
        $this->assertSame($original, \Illuminate\Support\Facades\Storage::disk('local')->get($revoked->pdf_path));
        $this->assertDatabaseHas('event_certificate_audits', ['event_certificate_id' => $issued->id, 'actor_id' => $user->id, 'action' => 'revoked']);
        $this->get($url)->assertOk()->assertSee('Revoked certificate')->assertDontSee('Issued to the wrong participant.');
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(\App\Services\EventCertificateRevocation::class)->revoke($user, $event->id, $issued->id, 'Another revocation reason.');
    }

    public function test_pending_certificate_cannot_be_revoked(): void
    {
        [$user, $event, $attendee, $template] = $this->fixture();
        $certificate = app(EventCertificateIssuance::class)->prepare($user, $event->id, $attendee->id, $template->id);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(\App\Services\EventCertificateRevocation::class)->revoke($user, $event->id, $certificate->id, 'Invalid pending certificate.');
    }

    public function test_gate_staff_cannot_revoke_certificates(): void
    {
        [$user, $event, $attendee, $template] = $this->fixture();
        $certificate = app(EventCertificateIssuance::class)->prepare($user, $event->id, $attendee->id, $template->id);
        $gate = User::factory()->create(['is_super_admin' => true, 'is_gate_staff' => true]);
        try {
            app(\App\Services\EventCertificateRevocation::class)->revoke($gate, $event->id, $certificate->id, 'Unauthorized revocation attempt.');
            $this->fail('Gate staff unexpectedly authorized.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_revocation_requires_meaningful_reason(): void
    {
        [$user, $event, $attendee, $template] = $this->fixture();
        $certificate = app(EventCertificateIssuance::class)->prepare($user, $event->id, $attendee->id, $template->id);
        $this->expectException(ValidationException::class);
        app(\App\Services\EventCertificateRevocation::class)->revoke($user, $event->id, $certificate->id, '  ');
    }

    public function test_delivery_reservation_encrypts_destination_and_prevents_duplicate_channel(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        [$user, $event, $attendee, $template] = $this->fixture();
        $attendee->update(['email' => 'recipient@example.com']);
        $certificate = app(EventCertificateIssuance::class)->prepare($user, $event->id, $attendee->id, $template->id);
        $issued = app(\App\Services\EventCertificateFinalization::class)->finalize($user, $event->id, $certificate->id);
        $service = app(\App\Services\EventCertificateDelivery::class);
        $id = $service->reserve($user, $event->id, $issued->id, 'email');
        $record = \Illuminate\Support\Facades\DB::table('event_certificate_deliveries')->where('id', $id)->first();
        $this->assertSame('queued', $record->status);
        $this->assertNotSame('recipient@example.com', $record->recipient_encrypted);
        $this->assertSame('recipient@example.com', \Illuminate\Support\Facades\Crypt::decryptString($record->recipient_encrypted));
        $this->expectException(ValidationException::class);
        $service->reserve($user, $event->id, $issued->id, 'email');
    }

    public function test_pending_certificate_cannot_be_reserved_for_delivery(): void
    {
        [$user, $event, $attendee, $template] = $this->fixture();
        $certificate = app(EventCertificateIssuance::class)->prepare($user, $event->id, $attendee->id, $template->id);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(\App\Services\EventCertificateDelivery::class)->reserve($user, $event->id, $certificate->id, 'email');
    }

    public function test_signed_certificate_download_expires_and_revocation_blocks_access(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        [$user, $event, $attendee, $template] = $this->fixture();
        $certificate = app(EventCertificateIssuance::class)->prepare($user, $event->id, $attendee->id, $template->id);
        $issued = app(\App\Services\EventCertificateFinalization::class)->finalize($user, $event->id, $certificate->id);
        $url = app(\App\Services\EventCertificateDownloadLink::class)->generate($issued);
        $this->get(route('public.certificates.download', ['certificate' => $issued->id]))->assertForbidden();
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->travel(31)->days();
        $this->get($url)->assertForbidden();
        $this->travelBack();
        app(\App\Services\EventCertificateRevocation::class)->revoke($user, $event->id, $issued->id, 'Certificate no longer valid.');
        $this->get($url)->assertNotFound();
    }

    public function test_short_download_token_is_hashed_expires_and_rejects_revocation(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        [$user, $event, $attendee, $template] = $this->fixture();
        $certificate = app(EventCertificateIssuance::class)->prepare($user, $event->id, $attendee->id, $template->id);
        $issued = app(\App\Services\EventCertificateFinalization::class)->finalize($user, $event->id, $certificate->id);
        $url = app(\App\Services\EventCertificateDownloadLink::class)->short($issued);
        $token = basename(parse_url($url, PHP_URL_PATH));
        $this->assertSame(24, strlen($token));
        $this->assertDatabaseHas('event_certificate_download_tokens', ['event_certificate_id' => $issued->id, 'token_hash' => hash('sha256', $token)]);
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->travel(31)->days();
        $this->get($url)->assertNotFound();
        $this->travelBack();
        app(\App\Services\EventCertificateRevocation::class)->revoke($user, $event->id, $issued->id, 'Certificate no longer valid.');
        $this->get($url)->assertNotFound();
    }

    public function test_sms_delivery_submits_once_and_records_provider_reference(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        [$user, $event, $attendee, $template] = $this->fixture();
        $attendee->update(['phone' => '255712345678']);
        $certificate = app(EventCertificateIssuance::class)->prepare($user, $event->id, $attendee->id, $template->id);
        $issued = app(\App\Services\EventCertificateFinalization::class)->finalize($user, $event->id, $certificate->id);
        $id = app(\App\Services\EventCertificateDelivery::class)->reserve($user, $event->id, $issued->id, 'sms');
        $this->mock(\App\Services\SmsService::class, function ($mock) {
            $mock->shouldReceive('normalizeRecipient')->once()->andReturn('255712345678');
            $mock->shouldReceive('send')->once()->with('255712345678', \Mockery::on(fn ($message) => str_contains($message, '/c/')), false)
                ->andReturn(['success' => true, 'provider_message_id' => 'test-reference']);
        });
        $job = new \App\Jobs\DeliverEventCertificate($id);
        $job->handle();
        $job->handle();
        $this->assertDatabaseHas('event_certificate_deliveries', ['id' => $id, 'status' => 'submitted', 'provider_reference' => 'test-reference']);
    }

    public function test_ambiguous_sms_submission_is_not_retried(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        [$user, $event, $attendee, $template] = $this->fixture();
        $attendee->update(['phone' => '255712345678']);
        $certificate = app(EventCertificateIssuance::class)->prepare($user, $event->id, $attendee->id, $template->id);
        $issued = app(\App\Services\EventCertificateFinalization::class)->finalize($user, $event->id, $certificate->id);
        $id = app(\App\Services\EventCertificateDelivery::class)->reserve($user, $event->id, $issued->id, 'sms');
        $this->mock(\App\Services\SmsService::class, function ($mock) {
            $mock->shouldReceive('normalizeRecipient')->once()->andReturn('255712345678');
            $mock->shouldReceive('send')->once()->andThrow(new \RuntimeException('Provider timeout'));
        });
        $job = new \App\Jobs\DeliverEventCertificate($id);
        $job->handle();
        $job->handle();
        $this->assertDatabaseHas('event_certificate_deliveries', ['id' => $id, 'status' => 'uncertain', 'failure_code' => 'submission_unconfirmed']);
    }

    public function test_changed_email_prevents_delivery_before_provider_submission(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        [$user, $event, $attendee, $template] = $this->fixture();
        $attendee->update(['email' => 'original@example.com']);
        $certificate = app(EventCertificateIssuance::class)->prepare($user, $event->id, $attendee->id, $template->id);
        $issued = app(\App\Services\EventCertificateFinalization::class)->finalize($user, $event->id, $certificate->id);
        $id = app(\App\Services\EventCertificateDelivery::class)->reserve($user, $event->id, $issued->id, 'email');
        $attendee->update(['email' => 'changed@example.com']);
        \Illuminate\Support\Facades\Mail::shouldReceive('send')->never();
        (new \App\Jobs\DeliverEventCertificate($id))->handle();
        $this->assertDatabaseHas('event_certificate_deliveries', ['id' => $id, 'status' => 'failed', 'failure_code' => 'preflight_failed']);
    }

    public function test_delivery_retry_dispatches_only_confirmed_preflight_failure(): void
    {
        \Illuminate\Support\Facades\Bus::fake();
        \Illuminate\Support\Facades\Storage::fake('local');
        config(['queue.default' => 'database']);
        [$user, $event, $attendee, $template] = $this->fixture();
        $attendee->update(['email' => 'recipient@example.com']);
        $certificate = app(EventCertificateIssuance::class)->prepare($user, $event->id, $attendee->id, $template->id);
        $issued = app(\App\Services\EventCertificateFinalization::class)->finalize($user, $event->id, $certificate->id);
        $id = app(\App\Services\EventCertificateDelivery::class)->reserve($user, $event->id, $issued->id, 'email');
        \Illuminate\Support\Facades\DB::table('event_certificate_deliveries')->where('id', $id)
            ->update(['status' => 'failed', 'failure_code' => 'preflight_failed']);
        app(\App\Services\EventCertificateDeliveryRetry::class)->retry($user, $event->id, $issued->id, 'email');
        $this->assertDatabaseHas('event_certificate_deliveries', ['id' => $id, 'status' => 'queued', 'failure_code' => null]);
        \Illuminate\Support\Facades\Bus::assertDispatchedTimes(\App\Jobs\DeliverEventCertificate::class, 1);
        try {
            app(\App\Services\EventCertificateDeliveryRetry::class)->retry($user, $event->id, $issued->id, 'email');
            $this->fail('Duplicate retry unexpectedly accepted.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
        \Illuminate\Support\Facades\Bus::assertDispatchedTimes(\App\Jobs\DeliverEventCertificate::class, 1);
    }

    public function test_delivery_retry_rejects_uncertain_submission(): void
    {
        \Illuminate\Support\Facades\Bus::fake();
        \Illuminate\Support\Facades\Storage::fake('local');
        config(['queue.default' => 'database']);
        [$user, $event, $attendee, $template] = $this->fixture();
        $attendee->update(['email' => 'recipient@example.com']);
        $certificate = app(EventCertificateIssuance::class)->prepare($user, $event->id, $attendee->id, $template->id);
        $issued = app(\App\Services\EventCertificateFinalization::class)->finalize($user, $event->id, $certificate->id);
        $id = app(\App\Services\EventCertificateDelivery::class)->reserve($user, $event->id, $issued->id, 'email');
        \Illuminate\Support\Facades\DB::table('event_certificate_deliveries')->where('id', $id)
            ->update(['status' => 'uncertain', 'failure_code' => 'submission_unconfirmed']);
        try {
            app(\App\Services\EventCertificateDeliveryRetry::class)->retry($user, $event->id, $issued->id, 'email');
            $this->fail('Uncertain submission unexpectedly retried.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }
        \Illuminate\Support\Facades\Bus::assertNotDispatched(\App\Jobs\DeliverEventCertificate::class);
        $this->assertDatabaseHas('event_certificate_deliveries', ['id' => $id, 'status' => 'uncertain']);
    }

    public function test_certificate_report_filters_and_summary_are_event_scoped(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        [$user, $event, $attendee, $template] = $this->fixture();
        [, $otherEvent, $otherAttendee, $otherTemplate] = $this->fixture();
        $attendee->update(['email' => 'recipient@example.com']);
        $certificate = app(EventCertificateIssuance::class)->prepare($user, $event->id, $attendee->id, $template->id);
        $issued = app(\App\Services\EventCertificateFinalization::class)->finalize($user, $event->id, $certificate->id);
        app(\App\Services\EventCertificateDelivery::class)->reserve($user, $event->id, $issued->id, 'email');
        app(EventCertificateIssuance::class)->prepare($user, $otherEvent->id, $otherAttendee->id, $otherTemplate->id);
        Attendee::create(['event_id' => $event->id, 'full_name' => 'Outstanding attendee', 'status' => 'checked_in']);

        $this->actingAs($user);
        $page = new \App\Filament\Resources\Events\Pages\EventCertificateReport;
        $page->mount($event->id);
        $summary = $page->summary();
        $this->assertEquals(1, $summary['certificates']['issued']);
        $this->assertArrayNotHasKey('pending', $summary['certificates']);
        $this->assertEquals(1, $summary['outstanding']);
        $this->assertEquals(1, $summary['delivery']['email']['queued']);
        $this->assertSame([$issued->id], $page->records()->getCollection()->pluck('id')->all());
        $page->templateFilter = (string) $otherTemplate->id;
        $this->assertSame(0, $page->records()->total());
        $page->templateFilter = (string) $template->id;
        $page->deliveryFilter = 'sms';
        $this->assertSame(0, $page->records()->total());
        $page->deliveryFilter = 'email';
        $page->issuedFrom = now()->addDay()->toDateString();
        $this->assertSame(0, $page->records()->total());
        $page->issuedFrom = now()->toDateString();
        $this->assertSame(1, $page->records()->total());
        $page->issuedFrom = '2026-02-30';
        $this->assertSame(0, $page->records()->total());
    }

    public function test_certificate_audit_history_is_event_scoped_and_filterable(): void
    {
        [$user, $event, $attendee, $template] = $this->fixture();
        [, $otherEvent, $otherAttendee, $otherTemplate] = $this->fixture();
        $certificate = app(EventCertificateIssuance::class)->prepare($user, $event->id, $attendee->id, $template->id);
        $other = app(EventCertificateIssuance::class)->prepare($user, $otherEvent->id, $otherAttendee->id, $otherTemplate->id);
        $this->actingAs($user);
        $page = new \App\Filament\Resources\Events\Pages\EventCertificateAuditHistory;
        $page->mount($event->id);
        $this->assertEquals([$certificate->id], $page->records()->getCollection()->pluck('event_certificate_id')->all());
        $page->actionFilter = 'revoked';
        $this->assertSame(0, $page->records()->total());
        $page->actionFilter = 'prepared';
        $page->search = $other->certificate_number;
        $this->assertSame(0, $page->records()->total());
        $page->search = $certificate->certificate_number;
        $this->assertSame(1, $page->records()->total());
    }

    public function test_delivery_recovery_does_not_dispatch_or_modify_processing_records(): void
    {
        \Illuminate\Support\Facades\Bus::fake();
        \Illuminate\Support\Facades\Storage::fake('local');
        [$user, $event, $attendee, $template] = $this->fixture();
        $attendee->update(['email' => 'recipient@example.com', 'phone' => '255712345678']);
        $certificate = app(EventCertificateIssuance::class)->prepare($user, $event->id, $attendee->id, $template->id);
        $issued = app(\App\Services\EventCertificateFinalization::class)->finalize($user, $event->id, $certificate->id);
        $service = app(\App\Services\EventCertificateDelivery::class);
        $email = $service->reserve($user, $event->id, $issued->id, 'email');
        $sms = $service->reserve($user, $event->id, $issued->id, 'sms');
        \Illuminate\Support\Facades\DB::table('event_certificate_deliveries')->where('id', $email)->update(['updated_at' => now()->subHours(3)]);
        \Illuminate\Support\Facades\DB::table('event_certificate_deliveries')->where('id', $sms)->update(['status' => 'processing', 'updated_at' => now()->subHours(3)]);
        $recovery = app(\App\Services\EventCertificateDeliveryRecovery::class);
        $this->assertSame(['stale_queued' => 1, 'stale_processing' => 1, 'marked_uncertain' => 0], $recovery->inspect($event->id));
        $this->assertDatabaseHas('event_certificate_deliveries', ['id' => $email, 'status' => 'queued']);
        $this->assertSame(1, $recovery->inspect($event->id, true)['marked_uncertain']);
        $this->assertDatabaseHas('event_certificate_deliveries', ['id' => $email, 'status' => 'uncertain']);
        $this->assertDatabaseHas('event_certificate_deliveries', ['id' => $sms, 'status' => 'processing']);
        \Illuminate\Support\Facades\Bus::assertNotDispatched(\App\Jobs\DeliverEventCertificate::class);
    }

    public function test_bulk_issuance_rejects_overlapping_unfinished_batches(): void
    {
        \Illuminate\Support\Facades\Bus::fake();
        config(['queue.default' => 'database']);
        [$user, $event, $attendee, $template] = $this->fixture();
        $service = app(\App\Services\EventCertificateBulkIssuance::class);
        $this->assertSame(1, $service->queue($user, $event->id, $template->id, [$attendee->id]));
        try {
            $service->queue($user, $event->id, $template->id, [$attendee->id]);
            $this->fail('Duplicate batch unexpectedly accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('attendees', $exception->errors());
        }
        $this->assertDatabaseCount('event_certificate_batches', 1);
        \Illuminate\Support\Facades\Bus::assertDispatchedTimes(\App\Jobs\IssueEventCertificate::class, 1);
    }

    public function test_certificate_sms_reservation_normalizes_local_number(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        [$user, $event, $attendee, $template] = $this->fixture();
        $attendee->update(['phone' => '0712 345 678']);
        $certificate = app(EventCertificateIssuance::class)->prepare($user, $event->id, $attendee->id, $template->id);
        $issued = app(\App\Services\EventCertificateFinalization::class)->finalize($user, $event->id, $certificate->id);
        $id = app(\App\Services\EventCertificateDelivery::class)->reserve($user, $event->id, $issued->id, 'sms');
        $row = \Illuminate\Support\Facades\DB::table('event_certificate_deliveries')->where('id', $id)->first();
        $this->assertSame('255712345678', \Illuminate\Support\Facades\Crypt::decryptString($row->recipient_encrypted));
    }

    public function test_certificate_issuance_uses_configured_queue(): void
    {
        config(['queue.certificate_queue' => 'certificates']);
        $job = new \App\Jobs\IssueEventCertificate(1, 2, 3, 4, 'checked_in');
        $this->assertSame('certificates', $job->queue);
        config(['queue.certificate_queue' => 'default']);
        $this->assertSame('default', (new \App\Jobs\IssueEventCertificate(1, 2, 3, 4, 'checked_in'))->queue);
    }

    public function test_expired_download_token_cleanup_preserves_active_links_and_certificates(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        [$user, $event, $attendee, $template] = $this->fixture();
        $certificate = app(EventCertificateIssuance::class)->prepare($user, $event->id, $attendee->id, $template->id);
        $issued = app(\App\Services\EventCertificateFinalization::class)->finalize($user, $event->id, $certificate->id);
        $links = app(\App\Services\EventCertificateDownloadLink::class);
        $expired = $links->short($issued);
        $active = $links->short($issued);
        $expiredHash = hash('sha256', basename(parse_url($expired, PHP_URL_PATH)));
        $activeHash = hash('sha256', basename(parse_url($active, PHP_URL_PATH)));
        \Illuminate\Support\Facades\DB::table('event_certificate_download_tokens')->where('token_hash', $expiredHash)
            ->update(['expires_at' => now()->subMinute()]);
        $this->artisan('certificates:prune-download-tokens')->assertExitCode(0);
        $this->assertDatabaseHas('event_certificate_download_tokens', ['token_hash' => $expiredHash]);
        $this->artisan('certificates:prune-download-tokens', ['--apply' => true])->assertExitCode(0);
        $this->assertDatabaseMissing('event_certificate_download_tokens', ['token_hash' => $expiredHash]);
        $this->assertDatabaseHas('event_certificate_download_tokens', ['token_hash' => $activeHash]);
        $this->assertDatabaseHas('event_certificates', ['id' => $issued->id, 'status' => 'issued']);
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($issued->pdf_path);
        $this->get($active)->assertOk();
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
