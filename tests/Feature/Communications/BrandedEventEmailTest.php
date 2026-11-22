<?php

namespace Tests\Feature\Communications;

use App\Models\Attendee;
use App\Models\Event;
use Tests\TestCase;

class BrandedEventEmailTest extends TestCase
{
    public function test_reminder_does_not_repeat_details_already_in_its_message(): void
    {
        $event = new Event([
            'name' => 'The Revived Way Album Launch Concert',
            'venue' => 'Masaki Wellspring Hub',
            'starts_at' => '2026-11-22 16:00:00',
        ]);

        $html = view('emails.elive', [
            'event' => $event,
            'attendee' => new Attendee(['full_name' => 'Test Guest']),
            'subject' => 'Reminder | The Revived Way Album Launch Concert',
            'emailLabel' => 'Event Update',
            'messageBody' => "Dear Test Guest,\n\nJoin us on Sunday, 22 November 2026, at 4:00 PM.\n"
                ."Masaki Wellspring Hub\n6 Twiga Street, Masaki, Dar es Salaam\n\nSee you soon!\neLive Events",
        ])->render();

        $this->assertStringNotContainsString('Event Update', $html);
        $this->assertStringNotContainsString('Event Reminder', $html);
        $this->assertStringNotContainsString('Hello Test Guest', $html);
        $this->assertStringNotContainsString('Thank you,', $html);
        $this->assertStringNotContainsString('class="detail-column"', $html);
        $this->assertSame(1, substr_count($html, 'Masaki Wellspring Hub'));
        $this->assertStringContainsString('6 Twiga Street, Masaki, Dar es Salaam', $html);
    }

    public function test_shared_event_email_only_shows_available_event_details(): void
    {
        $html = view('emails.elive', [
            'event' => new Event(['name' => 'Example Event', 'starts_at' => '2026-11-22 16:00:00']),
            'attendee' => null,
            'emailLabel' => 'Registration Confirmed',
            'messageBody' => 'Your registration is confirmed.',
        ])->render();

        $this->assertStringContainsString('Registration Confirmed', $html);
        $this->assertStringNotContainsString('Category', $html);
        $this->assertStringNotContainsString('Venue', $html);
        $this->assertSame(1, substr_count($html, '22 Nov 2026'));
        $this->assertSame(1, substr_count($html, '04:00 PM'));
    }
}
