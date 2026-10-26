<?php

namespace Tests\Feature\Finance;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class OrganizerFinanceViewTest extends TestCase
{
    public function test_organizer_finance_view_exists(): void
    {
        $path = resource_path(
            'views/filament/pages/organizer-finance-overview.blade.php'
        );

        $this->assertTrue(
            File::exists($path),
            'Organizer finance Blade view must exist.'
        );
    }

    public function test_organizer_finance_view_only_shows_safe_finance_labels(): void
    {
        $path = resource_path(
            'views/filament/pages/organizer-finance-overview.blade.php'
        );

        $contents = File::get($path);

        $this->assertStringContainsString(
            'Ticket Gross Sales',
            $contents
        );

        $this->assertStringContainsString(
            'Ticket Charges',
            $contents
        );

        $this->assertStringContainsString(
            'Ticket Net Payable',
            $contents
        );

        $this->assertStringContainsString(
            'Paid Ticket Orders',
            $contents
        );

        $this->assertStringContainsString('View Ticket Finance', $contents);
        $this->assertStringContainsString('View Merchandise Finance', $contents);
        $this->assertStringContainsString('Merchandise Gross Receipts', $contents);
        $this->assertStringNotContainsString('Financial Position', $contents);

        $this->assertStringNotContainsString(
            'eLive Commission',
            $contents
        );

        $this->assertStringNotContainsString(
            'Gateway Fee',
            $contents
        );

        $this->assertStringNotContainsString(
            'Gateway Fee Bearer',
            $contents
        );

        $this->assertStringNotContainsString(
            'platform_commission',
            $contents
        );

        $this->assertStringNotContainsString(
            'gateway_fee',
            $contents
        );
    }
}
