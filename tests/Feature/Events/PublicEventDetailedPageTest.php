<?php

namespace Tests\Feature\Events;

use App\Models\Event;
use App\Models\EventTicketSetting;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\TicketType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicEventDetailedPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_detailed_public_content_is_cast_and_rendered(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Revived Music Ministries',
            'slug' => 'revived-music-ministries',
            'status' => Organization::STATUS_ACTIVE,
            'email' => 'events@example.test',
            'phone' => '+255700000000',
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'The Revived Way Album Launch Concert',
            'slug' => 'the-revived-way',
            'event_type' => 'concert',
            'venue' => 'Masaki Wellspring Hub',
            'venue_address' => '6 Twiga Street, Masaki, Dar es Salaam',
            'public_theme' => 'Revive Us Again, Lord',
            'description' => 'A revival experience where worship becomes a collective prayer.',
            'starts_at' => now()->addDays(10),
            'ends_at' => now()->addDays(10)->addHours(4),
            'capacity' => 350,
            'status' => Event::STATUS_ACTIVE,
            'ministry_years' => 6,
            'group_members_count' => 13,
            'dress_code' => 'Modest, smart and worship-appropriate attire.',
            'seating_policy' => 'VIP and VVIP reserved; general admission is open seating.',
            'public_highlights' => [
                ['label' => 'Theme', 'value' => 'Revival and unity'],
            ],
            'public_faqs' => [
                ['question' => 'Is the concert open to all denominations?', 'answer' => 'Yes, everyone is welcome.'],
            ],
            'public_speakers' => [
                ['name' => 'Revived Music Ministries', 'role' => 'Featured ministry', 'image_path' => null],
            ],
            'final_cta_title' => 'Secure your seat for an evening of revival',
        ]);

        $event->refresh();

        $this->assertSame('Theme', $event->public_highlights[0]['label']);
        $this->assertSame('Is the concert open to all denominations?', $event->public_faqs[0]['question']);

        $this->get(route('public.events.show', ['event' => $event->slug]))
            ->assertOk()
            ->assertSee('Revive Us Again, Lord')
            ->assertSee('Revival begins in')
            ->assertSee('6+')
            ->assertSee('Years of Ministry')
            ->assertSee('13')
            ->assertSee('Group Members')
            ->assertSee('Common Questions')
            ->assertSee('Is the concert open to all denominations?')
            ->assertSee('Secure your seat for an evening of revival');
    }

    public function test_public_ticket_tiers_show_live_remaining_capacity(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Ticket Test Organization',
            'slug' => 'ticket-test-organization',
            'status' => Organization::STATUS_ACTIVE,
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Capacity Concert',
            'slug' => 'capacity-concert',
            'event_type' => 'concert',
            'starts_at' => now()->addWeek(),
            'ends_at' => now()->addWeek()->addHours(3),
            'capacity' => 10,
            'status' => Event::STATUS_ACTIVE,
        ]);

        EventTicketSetting::query()->create([
            'event_id' => $event->id,
            'ticket_sales_enabled' => true,
            'reservation_minutes' => 15,
            'max_tickets_per_order' => 10,
            'allow_guest_checkout' => true,
        ]);

        TicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'VIP',
            'code' => 'VIP',
            'description' => 'Priority seating',
            'price' => 50000,
            'currency' => 'TZS',
            'capacity' => 10,
            'min_per_order' => 1,
            'max_per_order' => 10,
            'is_active' => true,
            'is_public' => true,
            'requires_holder_details' => false,
            'sort_order' => 1,
        ]);

        $this->get(route('public.events.show', ['event' => $event->slug]))
            ->assertOk()
            ->assertSee('Ticket Options')
            ->assertSee('VIP')
            ->assertSee('10 remaining')
            ->assertSee('TZS 50,000');
    }
}
