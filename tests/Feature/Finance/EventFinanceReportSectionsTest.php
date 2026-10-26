<?php

namespace Tests\Feature\Finance;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class EventFinanceReportSectionsTest extends TestCase
{
    public function test_admin_event_finance_has_four_distinct_sections_and_safety_explanations(): void
    {
        $view = File::get(resource_path('views/filament/pages/event-finance-report.blade.php'));

        foreach (['Ticket Finance', 'Merchandise Finance', 'Settlement & Reconciliation', 'Payout Management'] as $section) {
            $this->assertStringContainsString('<h2 class="text-lg font-semibold text-gray-900 dark:text-white">'.$section.'</h2>', $view);
        }

        $this->assertStringContainsString('independent evidence review', $view);
        $this->assertStringContainsString('not payout-eligible funds', $view);
        $this->assertStringContainsString("['net_ticket_earnings']", $view);
        $this->assertStringContainsString("['completed_merchandise_payment_gross']", $view);
        $this->assertStringContainsString("['indicative_clearance_limited_balance']", $view);
        $this->assertStringContainsString("['reserved_requests']", $view);
    }
}
