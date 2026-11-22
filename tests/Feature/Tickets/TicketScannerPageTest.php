<?php

namespace Tests\Feature\Tickets;

use App\Filament\Pages\TicketScanner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class TicketScannerPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_scanner_filament_page_exists(): void
    {
        $this->assertTrue(
            class_exists(TicketScanner::class),
            'Expected the Filament TicketScanner page to exist.'
        );
    }

    public function test_ticket_scanner_filament_view_exists(): void
    {
        $this->assertTrue(
            View::exists('filament.pages.ticket-scanner'),
            'Expected the Ticket Scanner Filament view to exist.'
        );
    }

    public function test_ticket_scanner_view_contains_camera_manual_input_and_result_panel(): void
    {
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['is_super_admin' => true]));

        $this->get('/admin/ticket-scanner')
            ->assertOk()
            ->assertSee('id="ticket-scanner-camera"', false)
            ->assertSee('id="ticket-scanner-manual-input"', false)
            ->assertSee('id="ticket-scanner-result"', false);
    }

    public function test_ticket_scanner_uses_html5_qrcode_for_cross_browser_camera_scanning(): void
    {
        $bladePath = resource_path(
            'views/filament/pages/ticket-scanner.blade.php'
        );

        $scannerJsPath = resource_path(
            'js/ticket-scanner.js'
        );

        $vitePath = base_path('vite.config.js');

        $blade = file_get_contents($bladePath);
        $vite = file_get_contents($vitePath);

        $this->assertStringContainsString(
            "@vite('resources/js/ticket-scanner.js')",
            $blade,
            'Expected the Filament scanner page to load the dedicated scanner Vite entry.'
        );

        $this->assertFileExists(
            $scannerJsPath,
            'Expected resources/js/ticket-scanner.js to exist.'
        );

        $scannerJs = file_get_contents($scannerJsPath);

        $this->assertStringContainsString(
            "from 'html5-qrcode'",
            $scannerJs,
            'Expected the scanner JavaScript to import html5-qrcode.'
        );

        $this->assertStringContainsString(
            'Html5Qrcode',
            $scannerJs,
            'Expected the scanner JavaScript to initialize Html5Qrcode.'
        );

        $this->assertStringContainsString(
            "resources/js/ticket-scanner.js",
            $vite,
            'Expected Vite to build the dedicated ticket scanner JavaScript entry.'
        );

        $this->assertStringNotContainsString(
            'BarcodeDetector',
            $scannerJs.file_get_contents(resource_path('views/filament/pages/partials/ticket-scanner-workspace.blade.php')),
            'The scanner page should no longer depend on the unsupported native BarcodeDetector API.'
        );
    }
}
