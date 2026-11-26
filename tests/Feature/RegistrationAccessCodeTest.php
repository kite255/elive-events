<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationAccessCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_and_wrong_codes_are_rejected_without_flashing_secret(): void
    {
        $event = $this->createEvent(['registration_access_code_hash' => Hash::make('TestCode2026')]);
        $url = route('public.registration.store', ['event' => $event->slug]);
        $this->post($url, ['full_name' => 'Missing code'])->assertSessionHasErrors('registration_access_code');
        $this->post($url, ['full_name' => 'Wrong code', 'registration_access_code' => 'WrongCode2026'])->assertSessionHasErrors('registration_access_code');
        $this->assertNull(session()->getOldInput('registration_access_code'));
        $this->assertSame(0, $event->attendees()->count());
    }

    public function test_correct_code_allows_free_registration(): void
    {
        $event = $this->createEvent(['registration_access_code_hash' => Hash::make('TestCode2026')]);
        $this->post(route('public.registration.store', ['event' => $event->slug]), [
            'full_name' => 'Authorized participant',
            'registration_access_code' => 'TestCode2026',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('attendees', ['event_id' => $event->id, 'full_name' => 'Authorized participant']);
    }

    public function test_rotated_code_rejects_previous_code_and_removed_code_allows_registration(): void
    {
        $event = $this->createEvent(['registration_access_code_hash' => Hash::make('TestCode2026')]);
        $url = route('public.registration.store', ['event' => $event->slug]);
        $event->update(['registration_access_code_hash' => Hash::make('NewCode2026')]);
        $this->post($url, ['full_name' => 'Old code', 'registration_access_code' => 'TestCode2026'])->assertSessionHasErrors('registration_access_code');
        $this->assertSame(0, $event->attendees()->count());
        $event->update(['registration_access_code_hash' => null]);
        $this->post($url, ['full_name' => 'Unrestricted participant'])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(1, $event->attendees()->count());
    }

    public function test_draft_event_cannot_accept_public_registration(): void
    {
        $event = $this->createEvent(['status' => Event::STATUS_DRAFT]);
        $this->post(route('public.registration.store', ['event' => $event->slug]), ['full_name' => 'Draft participant']);
        $this->assertSame(0, $event->attendees()->count());
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
