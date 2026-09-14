<?php

namespace Tests\Feature\Tickets;

use App\Filament\Pages\AdminTicketLookup;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class AdminTicketLookupPageTest extends TestCase
{
    public function test_admin_ticket_lookup_filament_page_exists(): void
    {
        $this->assertTrue(
            class_exists(AdminTicketLookup::class),
            'Expected the AdminTicketLookup Filament page to exist.'
        );
    }

    public function test_admin_ticket_lookup_view_exists(): void
    {
        $this->assertTrue(
            View::exists('filament.pages.admin-ticket-lookup'),
            'Expected the Admin Ticket Lookup Filament view to exist.'
        );
    }

    public function test_lookup_view_contains_search_field_and_result_sections(): void
    {
        $view = file_get_contents(
            resource_path(
                'views/filament/pages/admin-ticket-lookup.blade.php'
            )
        );

        $this->assertStringContainsString(
            'wire:model.live.debounce',
            $view
        );

        $this->assertStringContainsString(
            'Order Number',
            $view
        );

        $this->assertStringContainsString(
            'Ticket Number',
            $view
        );

        $this->assertStringContainsString(
            'Payment / Order Status',
            $view
        );

        $this->assertStringContainsString(
            'Ticket Status',
            $view
        );

        $this->assertStringContainsString(
            'Check-in Status',
            $view
        );

        $this->assertStringContainsString(
            'View Order',
            $view
        );

        $this->assertStringContainsString(
            'View Ticket',
            $view
        );
    }
}
