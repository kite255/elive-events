<?php

namespace Tests\Feature\Tickets;

use App\Models\Event;
use App\Models\Organization;
use App\Models\TicketOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;
use ZipArchive;

class EventReportDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_downloads_only_the_selected_events_reports(): void
    {
        $event = $this->createEvent('selected');
        $otherEvent = $this->createEvent('other');

        foreach ([$event, $otherEvent] as $orderEvent) {
            TicketOrder::query()->create([
                'event_id' => $orderEvent->id,
                'order_number' => 'ORDER-'.$orderEvent->slug,
                'buyer_name' => 'Buyer '.$orderEvent->slug,
                'buyer_phone' => '255700000001',
                'buyer_email' => 'private@example.com',
                'quantity' => 2,
                'subtotal' => 30000,
                'discount_amount' => 0,
                'total' => 30000,
                'currency' => 'TZS',
                'status' => TicketOrder::STATUS_PAID,
                'paid_at' => now(),
            ]);
        }

        $user = User::factory()->create(['is_super_admin' => true]);

        $response = $this->actingAs($user)->get('/admin/events/'.$event->id.'/reports/download');
        $response->assertOk()->assertDownload('selected-event-reports.zip');

        /** @var BinaryFileResponse $download */
        $download = $response->baseResponse;
        $path = $download->getFile()->getPathname();
        $zip = new ZipArchive;
        $opened = $zip->open($path);

        try {
            $this->assertTrue($opened);
            $this->assertNotFalse($zip->getFromName('event-summary.csv'));
            $this->assertNotFalse($zip->getFromName('sales-by-ticket-type.csv'));
            $this->assertNotFalse($zip->getFromName('tickets-and-check-ins.csv'));
            $this->assertNotFalse($zip->getFromName('payouts.csv'));

            $orders = $zip->getFromName('buyer-orders.csv');
            $this->assertIsString($orders);
            $this->assertStringContainsString('Buyer selected', $orders);
            $this->assertStringNotContainsString('Buyer other', $orders);
            $this->assertStringNotContainsString('private@example.com', $orders);
        } finally {
            if ($opened === true) {
                $zip->close();
            }
            unlink($path);
        }
    }

    public function test_guest_and_non_administrator_cannot_download_reports(): void
    {
        $event = $this->createEvent('protected');
        $url = '/admin/events/'.$event->id.'/reports/download';

        $this->getJson($url)->assertUnauthorized();
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
    }

    private function createEvent(string $slug): Event
    {
        $organization = Organization::query()->create([
            'name' => 'Organization '.$slug,
            'slug' => 'organization-'.$slug,
        ]);

        return Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Event '.$slug,
            'slug' => $slug,
            'status' => Event::STATUS_ACTIVE,
        ]);
    }
}
