<?php

namespace Tests\Feature;

use App\Models\Attendee;
use App\Models\Event;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationConfirmationSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmation_routes_require_an_event_scoped_public_token(): void
    {
        $organization = Organization::create(['name' => 'Registration security test']);
        $event = $this->createEvent($organization, 'Security conference');
        $otherEvent = $this->createEvent($organization, 'Other conference');

        $attendee = Attendee::create([
            'event_id' => $event->id,
            'full_name' => 'Test Participant',
            'status' => 'registered',
            'registration_source' => 'public',
        ]);

        foreach (['public.registration.success', 'public.events.register.success'] as $route) {
            $url = fn (Event $target, string $identifier): string => route($route, [
                'event' => $target->slug,
                'attendee' => $identifier,
            ]);

            $this->get($url($event, (string) $attendee->id))->assertNotFound();
            $this->get($url($event, 'invalid-token'))->assertNotFound();
            $this->get($url($event, str_repeat('Z', 32)))->assertNotFound();
            $this->get($url($otherEvent, $attendee->public_token))->assertNotFound();
            $this->get($url($event, $attendee->public_token))
                ->assertOk()
                ->assertViewHas('attendee', fn (Attendee $actual): bool => $actual->is($attendee));
        }
    }

    private function createEvent(Organization $organization, string $name): Event
    {
        return Event::create([
            'organization_id' => $organization->id,
            'name' => $name,
            'event_type' => 'conference',
            'venue' => 'Test venue',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
            'status' => Event::STATUS_ACTIVE,
            'registration_is_open' => true,
        ]);
    }
}
