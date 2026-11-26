<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Organization;
use App\Services\EventPresetService;
use App\Services\EventPublicationReadiness;
use App\Services\EventSetupChecklist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuidedEventCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_new_event_presets_require_explicit_admission_activation(): void
    {
        foreach (array_keys(EventPresetService::eventTypes()) as $type) {
            $defaults = EventPresetService::creationDefaults($type);
            $this->assertFalse($defaults['registration_is_open'], $type);
            $this->assertFalse($defaults['ticketSetting.ticket_sales_enabled'], $type);
        }
    }

    public function test_presets_match_event_purpose(): void
    {
        $conference = EventPresetService::creationDefaults('conference');
        $concert = EventPresetService::creationDefaults('concert');
        $this->assertTrue($conference['sessions_enabled']);
        $this->assertTrue($conference['registration_auto_generate_badge']);
        $this->assertFalse($concert['sessions_enabled']);
        $this->assertFalse($concert['registration_auto_generate_badge']);
    }

    public function test_new_event_model_generates_operational_code_and_draft_status(): void
    {
        $event = $this->event();
        $this->assertSame(Event::STATUS_DRAFT, $event->fresh()->status);
        $this->assertNotEmpty($event->fresh()->event_code);
        $this->assertNotEmpty($event->slug);
    }

    public function test_global_slug_lookup_avoids_existing_slug_across_organizations(): void
    {
        $event = $this->event();
        $this->assertNotSame($event->slug, Event::generateUniqueSlug($event->name));
    }

    public function test_complete_manual_event_has_no_configuration_blockers(): void
    {
        $this->assertSame([], app(EventPublicationReadiness::class)->blockers($this->event()));
    }

    public function test_missing_schedule_blocks_publication(): void
    {
        $event = $this->event(['starts_at' => null, 'ends_at' => null]);
        $this->assertContains('Set valid start and end dates.', app(EventPublicationReadiness::class)->blockers($event));
    }

    public function test_paid_registration_without_positive_fee_is_blocked(): void
    {
        $event = $this->event(['registration_is_open' => true]);
        $event->paymentSetting()->create(['payments_enabled' => true, 'registration_fee' => 0, 'currency' => 'TZS']);
        $this->assertContains('Paid registration requires a positive registration fee.', app(EventPublicationReadiness::class)->blockers($event));
    }

    public function test_ticket_sales_without_public_ticket_types_is_blocked(): void
    {
        $event = $this->event();
        $event->ticketSetting()->create(['ticket_sales_enabled' => true]);
        $this->assertContains('Create at least one active public ticket type before opening sales.', app(EventPublicationReadiness::class)->blockers($event));
    }

    public function test_checklist_only_includes_ticket_types_when_sales_enabled(): void
    {
        $event = $this->event();
        $service = app(EventSetupChecklist::class);
        $this->assertNotContains('Ticket types', array_column($service->items($event), 'label'));
        $event->ticketSetting()->create(['ticket_sales_enabled' => true]);
        $this->assertContains('Ticket types', array_column($service->items($event->fresh()), 'label'));
    }

    public function test_registration_payments_without_registration_block_publication(): void
    {
        $event = $this->event();
        $event->paymentSetting()->create(['payments_enabled' => true, 'registration_fee' => 25000, 'currency' => 'TZS']);
        $this->assertContains('Enable registration or disable registration payments.', app(EventPublicationReadiness::class)->blockers($event));
    }

    public function test_equal_start_and_end_dates_block_publication(): void
    {
        $start = now()->addDay();
        $event = $this->event(['starts_at' => $start, 'ends_at' => $start]);
        $this->assertContains('Set valid start and end dates.', app(EventPublicationReadiness::class)->blockers($event));
    }

    public function test_inverted_ticket_sales_dates_block_publication(): void
    {
        $event = $this->event();
        $event->ticketSetting()->create([
            'ticket_sales_enabled' => true,
            'sales_start_at' => now()->addDays(2),
            'sales_end_at' => now()->addDay(),
        ]);
        $this->assertContains('Ticket sales must end after they start.', app(EventPublicationReadiness::class)->blockers($event));
    }

    public function test_required_hidden_registration_field_blocks_publication(): void
    {
        $event = $this->event([
            'registration_is_open' => true,
            'registration_require_phone' => true,
            'registration_show_phone' => false,
        ]);
        $this->assertContains('A required registration field is hidden: phone.', app(EventPublicationReadiness::class)->blockers($event));
    }

    public function test_private_category_does_not_satisfy_required_public_category(): void
    {
        $event = $this->event([
            'registration_is_open' => true,
            'registration_show_category' => true,
            'registration_require_category' => true,
        ]);
        $event->attendeeCategories()->create(['name' => 'Private category', 'is_active' => true, 'is_public' => false]);
        $this->assertContains('Create attendee categories before requiring category selection.', app(EventPublicationReadiness::class)->blockers($event));
    }

    private function event(array $overrides = []): Event
    {
        $organization = Organization::create(['name' => 'Guided creation test']);
        return Event::create(array_merge([
            'organization_id' => $organization->id,
            'name' => 'Guided creation conference',
            'event_type' => 'conference',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
            'registration_is_open' => false,
        ], $overrides));
    }
}
