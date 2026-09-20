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
            'Gross Sales',
            $contents
        );

        $this->assertStringContainsString(
            'Total Charges',
            $contents
        );

        $this->assertStringContainsString(
            'Net Payable',
            $contents
        );

        $this->assertStringContainsString(
            'Paid Orders',
            $contents
        );

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
