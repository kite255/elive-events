<?php

namespace Tests\Feature\Finance;

use App\Filament\Pages\OrganizerFinanceOverview;
use App\Filament\Pages\OrganizerMerchandiseFinance;
use App\Filament\Pages\OrganizerTicketFinance;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class OrganizerFinanceSeparatedPagesTest extends TestCase
{
    public function test_each_finance_page_has_distinct_navigation_and_view(): void
    {
        $this->assertSame('my-finance', OrganizerFinanceOverview::getSlug());
        $this->assertSame('ticket-finance', OrganizerTicketFinance::getSlug());
        $this->assertSame('merchandise-finance', OrganizerMerchandiseFinance::getSlug());

        $ticket = File::get(resource_path('views/filament/pages/organizer-ticket-finance.blade.php'));
        $merchandise = File::get(resource_path('views/filament/pages/organizer-merchandise-finance.blade.php'));
        $summary = File::get(resource_path('views/filament/pages/organizer-finance-overview.blade.php'));

        $this->assertStringContainsString('Ticket Gross Sales', $ticket);
        $this->assertStringNotContainsString('Merchandise Gross Receipts', $ticket);
        $this->assertStringContainsString('Merchandise Gross Receipts', $merchandise);
        $this->assertStringNotContainsString('Ticket Net Payable', $merchandise);
        $this->assertStringContainsString('View Ticket Finance', $summary);
        $this->assertStringContainsString('View Merchandise Finance', $summary);
    }

    public function test_new_pages_reject_guests_and_super_admins(): void
    {
        $this->assertFalse(OrganizerTicketFinance::canAccess());
        $this->assertFalse(OrganizerMerchandiseFinance::canAccess());

        $this->actingAs(User::factory()->create(['is_super_admin' => true]));

        $this->assertFalse(OrganizerTicketFinance::canAccess());
        $this->assertFalse(OrganizerMerchandiseFinance::canAccess());
    }
}
