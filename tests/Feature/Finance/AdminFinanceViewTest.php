<?php

namespace Tests\Feature\Finance;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AdminFinanceViewTest extends TestCase
{
    public function test_admin_finance_view_exists(): void
    {
        $path = resource_path(
            'views/filament/pages/admin-finance-overview.blade.php'
        );

        $this->assertTrue(
            File::exists($path),
            'Admin finance Blade view must exist.'
        );
    }

    public function test_admin_finance_view_shows_full_internal_breakdown(): void
    {
        $path = resource_path(
            'views/filament/pages/admin-finance-overview.blade.php'
        );

        $contents = File::get($path);

        $this->assertStringContainsString(
            'Gross Sales',
            $contents
        );

        $this->assertStringContainsString(
            'eLive Commission',
            $contents
        );

        $this->assertStringContainsString(
            'Gateway Fee',
            $contents
        );

        $this->assertStringContainsString(
            'Total Charges',
            $contents
        );

        $this->assertStringContainsString(
            'Organizer Net Payable',
            $contents
        );

        $this->assertStringContainsString(
            'Paid Orders',
            $contents
        );
    }
}
