<?php

namespace Tests\Feature\Events;

use App\Services\EventPresetService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EventFormVisibilityProfileTest extends TestCase
{
    #[Test]
    public function concert_only_exposes_ticket_focused_modules_by_default(): void
    {
        $profile = EventPresetService::featureProfile('concert');

        $this->assertTrue($profile['ticketing']);
        $this->assertFalse($profile['registration']);
        $this->assertFalse($profile['sessions']);
        $this->assertFalse($profile['professional_fields']);
        $this->assertFalse($profile['badges']);
    }

    #[Test]
    public function conference_exposes_registration_oriented_modules(): void
    {
        $profile = EventPresetService::featureProfile('conference');

        $this->assertFalse($profile['ticketing']);
        $this->assertTrue($profile['registration']);
        $this->assertTrue($profile['sessions']);
        $this->assertTrue($profile['professional_fields']);
        $this->assertTrue($profile['badges']);
    }

    #[Test]
    public function exhibition_exposes_both_registration_and_ticketing(): void
    {
        $profile = EventPresetService::featureProfile('exhibition');

        $this->assertTrue($profile['ticketing']);
        $this->assertTrue($profile['registration']);
        $this->assertTrue($profile['sessions']);
        $this->assertTrue($profile['professional_fields']);
        $this->assertTrue($profile['badges']);
    }

    #[Test]
    public function wedding_hides_professional_and_session_modules(): void
    {
        $profile = EventPresetService::featureProfile('wedding');

        $this->assertFalse($profile['ticketing']);
        $this->assertTrue($profile['registration']);
        $this->assertFalse($profile['sessions']);
        $this->assertFalse($profile['professional_fields']);
        $this->assertTrue($profile['guest_rsvp']);
    }
}