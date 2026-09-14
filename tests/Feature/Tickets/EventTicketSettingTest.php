<?php

namespace Tests\Feature\Tickets;

use App\Models\Event;
use App\Models\EventTicketSetting;
use App\Models\Organization;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventTicketSettingTest extends TestCase
{
    use RefreshDatabase;

    private function createEvent(): Event
    {
        $organization = Organization::create([
            'name' => 'Test Organization',
            'slug' => 'test-organization-' . uniqid(),
        ]);

        return Event::create([
            'organization_id' => $organization->id,
            'name' => 'Test Concert',
            'slug' => 'test-concert-' . uniqid(),
        ]);
    }

    public function test_event_can_have_ticket_settings(): void
    {
        $event = $this->createEvent();

        $settings = EventTicketSetting::create([
            'event_id' => $event->id,
            'ticket_sales_enabled' => true,
            'reservation_minutes' => 15,
            'max_tickets_per_order' => 10,
            'allow_guest_checkout' => true,
        ]);

        $this->assertDatabaseHas('event_ticket_settings', [
            'event_id' => $event->id,
            'reservation_minutes' => 15,
            'max_tickets_per_order' => 10,
        ]);

        $this->assertTrue($settings->ticket_sales_enabled);
        $this->assertTrue($settings->allow_guest_checkout);
    }

    public function test_ticket_settings_have_safe_defaults(): void
    {
        $event = $this->createEvent();

        $settings = EventTicketSetting::create([
            'event_id' => $event->id,
        ]);

        $settings->refresh();

        $this->assertTrue($settings->ticket_sales_enabled);
        $this->assertSame(15, $settings->reservation_minutes);
        $this->assertSame(10, $settings->max_tickets_per_order);
        $this->assertTrue($settings->allow_guest_checkout);
    }

    public function test_only_one_ticket_setting_record_is_allowed_per_event(): void
    {
        $event = $this->createEvent();

        EventTicketSetting::create([
            'event_id' => $event->id,
        ]);

        $this->expectException(QueryException::class);

        EventTicketSetting::create([
            'event_id' => $event->id,
        ]);
    }
}