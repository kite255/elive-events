<?php

namespace Tests\Feature;

use App\Http\Controllers\PublicRegistrationController;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationDuplicatePreventionTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_identity_requires_matching_name_and_contact_within_event(): void
    {
        $organization = Organization::create(['name' => 'Duplicate registration test']);
        $event = $this->createEvent($organization, 'Registration conference');
        $otherEvent = $this->createEvent($organization, 'Other conference');

        $attendee = Attendee::create([
            'event_id' => $event->id,
            'full_name' => 'Test Participant',
            'phone' => '0650537539',
            'email' => 'participant@example.com',
            'status' => 'registered',
        ]);

        $controller = new class extends PublicRegistrationController {
            public function duplicate(Event $event, string $name, ?string $phone, ?string $email): ?string
            {
                return $this->duplicateRegistrationReason($event, $name, $phone, $email);
            }
        };

        $this->assertNotNull($controller->duplicate($event, ' test participant ', '255650537539', null));
        $this->assertNotNull($controller->duplicate($event, 'TEST PARTICIPANT', null, 'PARTICIPANT@example.com'));
        $this->assertNull($controller->duplicate($event, 'Test Participant', '255710000001', 'different@example.com'));
        $this->assertNull($controller->duplicate($event, 'Different Participant', '255650537539', 'participant@example.com'));
        $this->assertNull($controller->duplicate($event, 'Test Participant', null, null));
        $this->assertNull($controller->duplicate($otherEvent, 'Test Participant', '255650537539', 'participant@example.com'));

        $attendee->update(['status' => 'cancelled']);
        $this->assertNull($controller->duplicate($event, 'Test Participant', '255650537539', 'participant@example.com'));

        $attendee->update(['status' => 'rejected']);
        $this->assertNull($controller->duplicate($event, 'Test Participant', '255650537539', 'participant@example.com'));
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
