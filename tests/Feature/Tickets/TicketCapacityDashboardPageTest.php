<?php

namespace Tests\Feature\Tickets;

use App\Filament\Pages\TicketCapacityDashboard;
use App\Models\Event;
use App\Models\Organization;
use App\Models\TicketType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class TicketCapacityDashboardPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_capacity_dashboard_page_and_view_exist(): void
    {
        $this->assertTrue(class_exists(TicketCapacityDashboard::class));
        $this->assertTrue(View::exists('filament.pages.ticket-capacity-dashboard'));
    }

    public function test_super_admin_can_read_selected_event_capacity_metrics(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Capacity Page Organization',
            'slug' => 'capacity-page-org-' . uniqid(),
        ]);
        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Capacity Page Event',
            'slug' => 'capacity-page-event-' . uniqid(),
            'status' => Event::STATUS_ACTIVE,
        ]);
        TicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'Regular',
            'code' => 'REG',
            'price' => 10000,
            'currency' => 'TZS',
            'capacity' => 50,
            'min_per_order' => 1,
            'max_per_order' => 10,
            'is_active' => true,
            'is_public' => true,
        ]);
        $admin = User::factory()->create(['is_super_admin' => true]);
        $this->actingAs($admin);

        $page = app(TicketCapacityDashboard::class);
        $page->selectedEventId = $event->id;
        $metrics = $page->capacityMetrics();

        $this->assertSame(50, $metrics['summary']['capacity']);
        $this->assertSame(50, $metrics['summary']['available']);
        $this->assertSame('Regular', $metrics['ticket_types'][0]['name']);
    }

    public function test_view_contains_required_capacity_metrics(): void
    {
        $view = file_get_contents(resource_path('views/filament/pages/ticket-capacity-dashboard.blade.php'));

        $this->assertStringContainsString('Event Capacity', $view);
        $this->assertStringContainsString('Tickets Sold', $view);
        $this->assertStringContainsString('Active Reservations', $view);
        $this->assertStringContainsString('Available Capacity', $view);
        $this->assertStringContainsString('Checked In', $view);
        $this->assertStringContainsString('Unlimited', $view);
    }
}
