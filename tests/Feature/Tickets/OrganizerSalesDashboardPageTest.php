<?php

namespace Tests\Feature\Tickets;

use App\Filament\Pages\OrganizerSalesDashboard;
use App\Models\Event;
use App\Models\Organization;
use App\Models\TicketOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class OrganizerSalesDashboardPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_organizer_sales_dashboard_filament_page_exists(): void
    {
        $this->assertTrue(
            class_exists(OrganizerSalesDashboard::class),
            'Expected the OrganizerSalesDashboard Filament page to exist.'
        );
    }

    public function test_organizer_sales_dashboard_view_exists(): void
    {
        $this->assertTrue(
            View::exists('filament.pages.organizer-sales-dashboard'),
            'Expected the Organizer Sales Dashboard Filament view to exist.'
        );
    }

    public function test_page_can_return_sales_metrics_for_selected_event(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Dashboard Organization',
            'slug' => 'dashboard-org-' . uniqid(),
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Dashboard Event',
            'slug' => 'dashboard-event-' . uniqid(),
            'status' => Event::STATUS_ACTIVE,
        ]);

        TicketOrder::query()->create([
            'event_id' => $event->id,
            'order_number' => 'ORD-' . strtoupper(uniqid()),
            'buyer_name' => 'Dashboard Buyer',
            'buyer_phone' => '255700000001',
            'buyer_email' => 'buyer@example.com',
            'quantity' => 2,
            'subtotal' => 30000,
            'discount_amount' => 0,
            'total' => 30000,
            'currency' => 'TZS',
            'status' => TicketOrder::STATUS_PAID,
            'paid_at' => now(),
            'expires_at' => now()->addMinutes(20),
        ]);

        $page = app(OrganizerSalesDashboard::class);

        $page->selectedEventId = $event->id;

        $metrics = $page->salesMetrics();

        $this->assertSame(30000.0, $metrics['gross_sales']);
        $this->assertSame(1, $metrics['paid_orders']);
        $this->assertSame(2, $metrics['tickets_sold']);
        $this->assertSame('TZS', $metrics['currency']);
    }

    public function test_dashboard_view_contains_event_selector_and_core_kpis(): void
    {
        $view = file_get_contents(
            resource_path(
                'views/filament/pages/organizer-sales-dashboard.blade.php'
            )
        );

        $this->assertStringContainsString(
            'wire:model.live="selectedEventId"',
            $view
        );

        $this->assertStringContainsString('Gross Sales', $view);
        $this->assertStringContainsString('Paid Orders', $view);
        $this->assertStringContainsString('Pending Orders', $view);
        $this->assertStringContainsString('Expired Orders', $view);
        $this->assertStringContainsString('Tickets Sold', $view);
    }

    public function test_dashboard_view_contains_sales_by_ticket_type_and_recent_orders_sections(): void
    {
        $view = file_get_contents(
            resource_path(
                'views/filament/pages/organizer-sales-dashboard.blade.php'
            )
        );

        $this->assertStringContainsString(
            'Sales by Ticket Type',
            $view
        );

        $this->assertStringContainsString(
            'Recent Orders',
            $view
        );
    }


    public function test_dashboard_view_uses_detailed_sales_metrics(): void
    {
        $view = file_get_contents(
            resource_path(
                'views/filament/pages/organizer-sales-dashboard.blade.php'
            )
        );

        $this->assertStringContainsString(
            "\$metrics['sales_by_ticket_type']",
            $view
        );

        $this->assertStringContainsString(
            "\$metrics['recent_orders']",
            $view
        );
    }

}
