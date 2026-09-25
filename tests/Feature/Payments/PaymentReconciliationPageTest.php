<?php

namespace Tests\Feature\Payments;

use App\Filament\Pages\PaymentReconciliation;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\TicketOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class PaymentReconciliationPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_page_and_view_exist(): void
    {
        $this->assertTrue(class_exists(PaymentReconciliation::class));
        $this->assertTrue(View::exists('filament.pages.payment-reconciliation'));
    }

    public function test_super_admin_can_view_local_reconciliation_issues_for_selected_event(): void
    {
        $organization = Organization::query()->create([
            'name' => 'Reconciliation Page Organization',
            'slug' => 'reconciliation-page-org-' . uniqid(),
        ]);
        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Reconciliation Page Event',
            'slug' => 'reconciliation-page-event-' . uniqid(),
            'status' => Event::STATUS_ACTIVE,
        ]);
        $order = TicketOrder::query()->create([
            'event_id' => $event->id,
            'order_number' => 'REC-PAGE-' . strtoupper(uniqid()),
            'buyer_name' => 'Page Buyer',
            'quantity' => 1,
            'subtotal' => 10000,
            'discount_amount' => 0,
            'total' => 10000,
            'currency' => 'TZS',
            'status' => TicketOrder::STATUS_PROCESSING,
            'expires_at' => now()->addMinutes(10),
        ]);
        Payment::query()->create([
            'organization_id' => $organization->id,
            'event_id' => $event->id,
            'ticket_order_id' => $order->id,
            'reference' => 'PAY-PAGE-' . strtoupper(uniqid()),
            'provider_tracking_id' => 'TRACK-PAGE-' . strtoupper(uniqid()),
            'amount' => 10000,
            'currency' => 'TZS',
            'status' => Payment::STATUS_PROCESSING,
            'initiated_at' => now(),
        ]);
        $admin = User::factory()->create(['is_super_admin' => true]);
        $this->actingAs($admin);

        $page = app(PaymentReconciliation::class);
        $page->selectedEventId = $event->id;

        $rows = $page->reconciliationRows();
        $summary = $page->summary();

        $this->assertCount(1, $rows);
        $this->assertSame(1, $summary['needs_attention']);
        $this->assertSame(1, $summary['pending_verification']);
    }

    public function test_view_only_exposes_verified_recovery_actions(): void
    {
        $view = file_get_contents(resource_path('views/filament/pages/payment-reconciliation.blade.php'));

        $this->assertStringContainsString('Re-sync with Pesapal', $view);
        $this->assertStringContainsString('Retry Fulfillment', $view);
        $this->assertStringContainsString('View Payment', $view);
        $this->assertStringContainsString('View Order', $view);
        $this->assertStringNotContainsString('Mark Paid', $view);
        $this->assertStringNotContainsString('Force Success', $view);
    }
}
