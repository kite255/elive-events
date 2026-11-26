<?php

namespace Tests\Feature;

use App\Models\Attendee;
use App\Models\Event;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationCapacityTest extends TestCase
{
    use RefreshDatabase;

    public function test_capacity_counts_pending_and_approved_but_not_waitlisted_or_cancelled(): void
    {
        $event = $this->createEvent(['capacity' => 2]);
        foreach (['pending_approval', 'approved', 'waitlisted', 'cancelled', 'rejected'] as $status) {
            Attendee::create([
                'event_id' => $event->id,
                'full_name' => 'Participant '.$status,
                'status' => $status,
            ]);
        }
        $this->assertSame(2, $event->acceptedAttendeesCount());
        $this->assertSame(1, $event->confirmedAttendeesCount());
        $this->assertSame(0, $event->remainingCapacity());
        $this->assertSame('closed', $event->nextRegistrationStatus());

        $event->update(['registration_waitlist_enabled' => true]);
        $this->assertSame('waitlisted', $event->nextRegistrationStatus());
    }

    public function test_public_registration_uses_last_available_place_then_waitlists(): void
    {
        $event = $this->createEvent(['capacity' => 1, 'registration_waitlist_enabled' => true]);
        $url = route('public.registration.store', ['event' => $event->slug]);

        $this->post($url, ['full_name' => 'First participant'])->assertSessionHasNoErrors()->assertRedirect();
        $this->post($url, ['full_name' => 'Second participant'])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('attendees', ['event_id' => $event->id, 'full_name' => 'First participant', 'status' => 'registered']);
        $this->assertDatabaseHas('attendees', ['event_id' => $event->id, 'full_name' => 'Second participant', 'status' => 'waitlisted']);
        $this->assertSame(1, $event->acceptedAttendeesCount());

        $event->update(['registration_waitlist_enabled' => false]);
        $this->post($url, ['full_name' => 'Third participant'])->assertSessionHas('error');
        $this->assertDatabaseMissing('attendees', ['event_id' => $event->id, 'full_name' => 'Third participant']);
    }

    private function createEvent(array $overrides): Event
    {
        $organization = Organization::create(['name' => 'Capacity test organization']);

        return Event::create(array_merge([
            'organization_id' => $organization->id,
            'name' => 'Capacity conference',
            'event_type' => 'conference',
            'venue' => 'Test venue',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
            'status' => Event::STATUS_ACTIVE,
            'registration_is_open' => true,
            'registration_requires_approval' => false,
            'registration_waitlist_enabled' => false,
            'registration_show_phone' => false,
            'registration_show_email' => false,
            'registration_show_organization' => false,
            'registration_show_position' => false,
            'registration_show_category' => false,
            'registration_show_badge_type' => false,
            'registration_auto_generate_badge' => false,
            'registration_sms_enabled' => false,
            'registration_email_enabled' => false,
            'registration_whatsapp_enabled' => false,
        ], $overrides));
    }
}
