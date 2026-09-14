<?php

namespace Tests\Feature\Events;

use App\Services\EventPresetService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventPresetFeatureProfileTest extends TestCase
{
    #[Test]
    public function concert_uses_ticketing_but_not_registration_or_sessions_by_default(): void
    {
        $profile = EventPresetService::featureProfile('concert');

        $this->assertTrue($profile['ticketing']);
        $this->assertFalse($profile['registration']);
        $this->assertFalse($profile['sessions']);
        $this->assertFalse($profile['professional_fields']);
        $this->assertFalse($profile['badges']);
        $this->assertFalse($profile['guest_rsvp']);
    }

    #[Test]
    public function conference_uses_registration_sessions_professional_fields_and_badges(): void
    {
        $profile = EventPresetService::featureProfile('conference');

        $this->assertFalse($profile['ticketing']);
        $this->assertTrue($profile['registration']);
        $this->assertTrue($profile['sessions']);
        $this->assertTrue($profile['professional_fields']);
        $this->assertTrue($profile['badges']);
        $this->assertFalse($profile['guest_rsvp']);
    }

    #[Test]
    public function wedding_uses_guest_registration_and_rsvp_without_professional_fields(): void
    {
        $profile = EventPresetService::featureProfile('wedding');

        $this->assertFalse($profile['ticketing']);
        $this->assertTrue($profile['registration']);
        $this->assertFalse($profile['sessions']);
        $this->assertFalse($profile['professional_fields']);
        $this->assertFalse($profile['badges']);
        $this->assertTrue($profile['guest_rsvp']);
    }

    #[Test]
    public function training_uses_registration_sessions_professional_fields_and_badges(): void
    {
        $profile = EventPresetService::featureProfile('training');

        $this->assertFalse($profile['ticketing']);
        $this->assertTrue($profile['registration']);
        $this->assertTrue($profile['sessions']);
        $this->assertTrue($profile['professional_fields']);
        $this->assertTrue($profile['badges']);
        $this->assertFalse($profile['guest_rsvp']);
    }

    #[Test]
    public function exhibition_can_combine_registration_sessions_and_ticketing(): void
    {
        $profile = EventPresetService::featureProfile('exhibition');

        $this->assertTrue($profile['ticketing']);
        $this->assertTrue($profile['registration']);
        $this->assertTrue($profile['sessions']);
        $this->assertTrue($profile['professional_fields']);
        $this->assertTrue($profile['badges']);
        $this->assertFalse($profile['guest_rsvp']);
    }
}