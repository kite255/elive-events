<?php

namespace Tests\Feature\Events;

use App\Services\EventPresetService;
use Tests\TestCase;

class EventPresetServiceCategoryFeaturesTest extends TestCase
{
    public function test_health_event_is_available_as_a_distinct_event_type(): void
    {
        $this->assertSame(
            'Health / Wellness Event',
            EventPresetService::eventTypes()['health_event'] ?? null
        );
    }

    public function test_charity_and_health_events_enable_donations_by_default(): void
    {
        $this->assertTrue(EventPresetService::usesDonations('charity_event'));
        $this->assertTrue(EventPresetService::usesDonations('health_event'));
        $this->assertFalse(EventPresetService::usesDonations('church_event'));
        $this->assertFalse(EventPresetService::usesDonations('concert'));
    }

    public function test_event_categories_have_distinct_feature_profiles(): void
    {
        $church = EventPresetService::featureProfile('church_event');
        $community = EventPresetService::featureProfile('community_event');
        $charity = EventPresetService::featureProfile('charity_event');
        $health = EventPresetService::featureProfile('health_event');
        $concert = EventPresetService::featureProfile('concert');

        $this->assertNotSame($church, $community);
        $this->assertNotSame($community, $charity);
        $this->assertNotSame($charity, $health);

        $this->assertTrue($church['badges']);
        $this->assertFalse($community['badges']);

        $this->assertTrue($charity['donations']);
        $this->assertFalse($charity['ticketing']);

        $this->assertTrue($health['registration']);
        $this->assertTrue($health['sessions']);
        $this->assertTrue($health['donations']);
        $this->assertFalse($health['professional_fields']);

        $this->assertTrue($concert['ticketing']);
        $this->assertFalse($concert['registration']);
    }

    public function test_health_event_preset_is_registration_and_activity_focused(): void
    {
        $preset = EventPresetService::preset('health_event');

        $this->assertTrue($preset['registration_show_phone']);
        $this->assertFalse($preset['registration_show_organization']);
        $this->assertTrue($preset['registration_show_category']);
        $this->assertTrue($preset['sessions_enabled']);
        $this->assertTrue($preset['session_registration_enabled']);
        $this->assertFalse($preset['registration_require_email']);
        $this->assertSame('single_day', $preset['schedule_mode']);
    }
}
