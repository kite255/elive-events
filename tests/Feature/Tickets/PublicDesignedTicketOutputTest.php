<?php

namespace Tests\Feature\Tickets;

use App\Models\Event;
use App\Models\EventTicketSetting;
use App\Models\EventTicketTemplate;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketTemplatePage;
use App\Models\TicketType;
use App\Services\Tickets\TicketIssuanceService;
use App\Services\Tickets\TicketOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicDesignedTicketOutputTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_page_displays_the_designed_ticket(): void
    {
        $ticket = $this->createDesignedTicket();

        $response = $this->get(route('public.tickets.show', [
            'token' => $ticket->public_token,
        ]));

        $response
            ->assertOk()
            ->assertSee('Designed ticket')
            ->assertSee('Download PNG')
            ->assertSee('Download PDF')
            ->assertSee('<svg', false)
            ->assertDontSee($ticket->qr_token_hash)
            ->assertDontSee($ticket->qr_token_encrypted);

        $cacheControl = (string) $response->headers->get('Cache-Control');

        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
    }

    public function test_each_page_downloads_as_a_native_png(): void
    {
        $ticket = $this->createDesignedTicket();

        $response = $this->get(route('public.tickets.pages.png', [
            'token' => $ticket->public_token,
            'pageNumber' => 1,
        ]));

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $bytes = $response->streamedContent();

        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $bytes);
        $this->assertSame([1080, 1350], array_slice(getimagesizefromstring($bytes), 0, 2));
    }

    public function test_all_pages_download_as_one_pdf(): void
    {
        $ticket = $this->createDesignedTicket();

        $response = $this->get(route('public.tickets.download.pdf', [
            'token' => $ticket->public_token,
        ]));

        $response
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->assertStringStartsWith(
            '%PDF-',
            $response->streamedContent()
        );
    }

    public function test_downloads_reject_invalid_or_ineligible_tickets(): void
    {
        $ticket = $this->createDesignedTicket();
        $ticket->update(['status' => Ticket::STATUS_CANCELLED]);

        $this->get(route('public.tickets.pages.png', [
            'token' => $ticket->public_token,
            'pageNumber' => 1,
        ]))->assertNotFound();

        $this->get(route('public.tickets.download.pdf', [
            'token' => $ticket->public_token,
        ]))->assertNotFound();
    }

    private function createDesignedTicket(): Ticket
    {
        $organization = Organization::query()->create([
            'name' => 'Designed Ticket Organizer',
            'slug' => 'designed-ticket-organizer-' . uniqid(),
            'timezone' => 'Africa/Dar_es_Salaam',
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Designed Ticket Concert',
            'slug' => 'designed-ticket-concert-' . uniqid(),
            'venue' => 'Mlimani City',
            'status' => Event::STATUS_ACTIVE,
            'starts_at' => now()->addDays(3),
        ]);

        EventTicketSetting::query()->create([
            'event_id' => $event->id,
            'ticket_sales_enabled' => true,
            'reservation_minutes' => 15,
            'max_tickets_per_order' => 10,
            'allow_guest_checkout' => true,
        ]);

        $ticketType = TicketType::query()->create([
            'event_id' => $event->id,
            'name' => 'VIP',
            'code' => 'VIP',
            'price' => 50000,
            'currency' => 'TZS',
            'capacity' => 100,
            'min_per_order' => 1,
            'max_per_order' => 5,
            'is_active' => true,
            'is_public' => true,
        ]);

        $template = EventTicketTemplate::query()->create([
            'event_id' => $event->id,
            'ticket_type_id' => $ticketType->id,
            'name' => 'VIP Designed Ticket',
            'width' => 1080,
            'height' => 1350,
            'is_active' => true,
            'is_default' => false,
        ]);

        TicketTemplatePage::query()->create([
            'event_ticket_template_id' => $template->id,
            'page_number' => 1,
            'name' => 'Front',
            'definition' => [
                'version' => 1,
                'elements' => [
                    [
                        'id' => 'holder_text',
                        'type' => 'text',
                        'binding' => 'holder_name',
                        'x' => 100,
                        'y' => 100,
                        'width' => 500,
                        'height' => 100,
                        'rotation' => 0,
                        'style' => [
                            'fontFamily' => 'Arial',
                            'fontSize' => 48,
                            'fontWeight' => 700,
                            'textAlign' => 'center',
                            'color' => '#161943',
                        ],
                    ],
                    [
                        'id' => 'ticket_qr',
                        'type' => 'qr',
                        'binding' => 'ticket_qr',
                        'x' => 100,
                        'y' => 250,
                        'width' => 300,
                        'height' => 300,
                        'rotation' => 0,
                    ],
                ],
            ],
        ]);

        $order = app(TicketOrderService::class)->createOrder(
            event: $event,
            buyer: [
                'name' => 'Designed Ticket Holder',
                'phone' => '255700000001',
                'email' => 'designed@example.com',
            ],
            items: [[
                'ticket_type_id' => $ticketType->id,
                'quantity' => 1,
            ]],
        );

        $order->update([
            'status' => TicketOrder::STATUS_PAID,
            'paid_at' => now(),
        ]);

        return app(TicketIssuanceService::class)
            ->issueForOrder($order->fresh())
            ->firstOrFail();
    }
}
