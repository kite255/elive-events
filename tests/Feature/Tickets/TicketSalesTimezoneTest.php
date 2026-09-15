<?php

namespace Tests\Feature\Tickets;

use App\Models\Event;
use App\Models\EventTicketSetting;
use App\Models\Organization;
use App\Models\TicketType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TicketSalesTimezoneTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_event_ticket_sales_window_uses_the_correct_instant(): void
    {
        Carbon::setTestNow(
            Carbon::parse(
                '2026-09-15 09:50:00',
                'Africa/Dar_es_Salaam'
            )
        );

        $organization = Organization::create([
            'name' => 'Timezone Organization',
            'slug' => 'timezone-organization',
        ]);

        $event = Event::create([
            'organization_id' => $organization->id,
            'name' => 'Timezone Concert',
            'slug' => 'timezone-concert',
            'event_type' => 'concert',
            'status' => 'active',
        ]);

        $setting = EventTicketSetting::create([
            'event_id' => $event->id,
            'ticket_sales_enabled' => true,
            'sales_start_at' => Carbon::parse(
                '2026-09-15 09:30:00',
                'Africa/Dar_es_Salaam'
            )->utc(),
            'sales_end_at' => Carbon::parse(
                '2026-09-18 09:30:00',
                'Africa/Dar_es_Salaam'
            )->utc(),
        ]);

        $this->assertTrue(
            $setting->fresh()->salesAreOpen()
        );
    }

    public function test_on_sale_query_binds_current_time_as_utc(): void
    {
        Carbon::setTestNow(
            Carbon::parse(
                '2026-09-15 09:50:00',
                'Africa/Dar_es_Salaam'
            )
        );

        $query = TicketType::query()->onSale();

        $bindings = $query
            ->getConnection()
            ->prepareBindings(
                $query->getBindings()
            );

        $this->assertContains(
            '2026-09-15 06:50:00',
            $bindings
        );
    }
}
