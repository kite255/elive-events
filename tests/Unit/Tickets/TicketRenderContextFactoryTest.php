<?php

namespace Tests\Unit\Tickets;

use App\Data\Tickets\RenderedTicketPage;
use App\Models\Event;
use App\Models\EventTicketSetting;
use App\Models\EventTicketTemplate;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketTemplatePage;
use App\Models\TicketType;
use App\Services\Tickets\Rendering\TicketRenderContextFactory;
use App\Services\Tickets\TicketIssuanceService;
use App\Services\Tickets\TicketOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TicketRenderContextFactoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_allow_listed_bindings_in_the_organization_timezone(): void
    {
        [$ticket, $template] = $this->createIssuedTicketWithTemplate();

        $context = app(TicketRenderContextFactory::class)
            ->make($ticket);

        $this->assertSame('event_ticket_type', $context->templateSource);
        $this->assertTrue($context->template?->is($template));
        $this->assertSame(1080, $context->width);
        $this->assertSame(1350, $context->height);
        $this->assertSame('Renderer Holder', $context->binding('holder_name'));
        $this->assertSame($ticket->ticket_number, $context->binding('ticket_number'));
        $this->assertSame('VIP', $context->binding('ticket_type'));
        $this->assertSame('Renderer Concert', $context->binding('event_name'));
        $this->assertSame('17/09/2026', $context->binding('event_date'));
        $this->assertSame('18:30', $context->binding('event_time'));
        $this->assertSame('Mlimani City', $context->binding('venue'));
        $this->assertSame($ticket->order->order_number, $context->binding('order_number'));
        $this->assertSame('', $context->binding('ticket_qr'));
        $this->assertSame('', $context->binding('unknown_binding'));
        $this->assertSame($ticket->qr_token_encrypted, $context->qrCredential);
    }

    public function test_bindings_exclude_sensitive_and_unapproved_ticket_data(): void
    {
        [$ticket] = $this->createIssuedTicketWithTemplate();

        $bindings = app(TicketRenderContextFactory::class)
            ->make($ticket)
            ->bindings();

        $this->assertSame([
            'holder_name',
            'ticket_number',
            'ticket_type',
            'event_name',
            'event_date',
            'event_time',
            'venue',
            'order_number',
        ], array_keys($bindings));

        $this->assertNotContains($ticket->public_token, $bindings, true);
        $this->assertNotContains($ticket->qr_token_hash, $bindings, true);
        $this->assertNotContains($ticket->qr_token_encrypted, $bindings, true);
        $this->assertArrayNotHasKey('id', $bindings);
        $this->assertArrayNotHasKey('holder_phone', $bindings);
        $this->assertArrayNotHasKey('holder_email', $bindings);
        $this->assertArrayNotHasKey('price', $bindings);
    }

    public function test_it_loads_template_pages_in_page_number_order(): void
    {
        [$ticket] = $this->createIssuedTicketWithTemplate();

        $context = app(TicketRenderContextFactory::class)
            ->make($ticket);

        $this->assertSame(
            [1, 2],
            $context->pages
                ->pluck('page_number')
                ->all()
        );
    }

    public function test_standard_fallback_has_no_template_pages(): void
    {
        [$ticket] = $this->createIssuedTicketWithTemplate(
            createTemplate: false
        );

        $context = app(TicketRenderContextFactory::class)
            ->make($ticket);

        $this->assertSame('standard', $context->templateSource);
        $this->assertNull($context->template);
        $this->assertTrue($context->pages->isEmpty());
    }

    public function test_rendered_ticket_page_keeps_native_page_metadata(): void
    {
        $page = new RenderedTicketPage(
            pageNumber: 2,
            pageName: 'Back',
            width: 1080,
            height: 1350,
            svg: '<svg></svg>',
            pngFilename: 'ELV-001-page-2.png',
        );

        $this->assertSame(2, $page->pageNumber);
        $this->assertSame('Back', $page->pageName);
        $this->assertSame(1080, $page->width);
        $this->assertSame(1350, $page->height);
        $this->assertSame('<svg></svg>', $page->svg);
        $this->assertSame('ELV-001-page-2.png', $page->pngFilename);
    }

    /**
     * @return array{Ticket, EventTicketTemplate|null}
     */
    private function createIssuedTicketWithTemplate(
        bool $createTemplate = true
    ): array {
        $organization = Organization::query()->create([
            'name' => 'Renderer Context Organizer',
            'slug' => 'renderer-context-organizer-' . uniqid(),
            'timezone' => 'Africa/Dar_es_Salaam',
            'date_format' => 'd/m/Y',
            'time_format' => 'H:i',
        ]);

        $event = Event::query()->create([
            'organization_id' => $organization->id,
            'name' => 'Renderer Concert',
            'slug' => 'renderer-concert-' . uniqid(),
            'venue' => 'Mlimani City',
            'status' => Event::STATUS_ACTIVE,
            'starts_at' => Carbon::parse(
                '2026-09-17 18:30:00',
                'Africa/Dar_es_Salaam'
            ),
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

        $template = null;

        if ($createTemplate) {
            $template = EventTicketTemplate::query()->create([
                'event_id' => $event->id,
                'ticket_type_id' => $ticketType->id,
                'name' => 'VIP Renderer Template',
                'width' => 1080,
                'height' => 1350,
                'is_active' => true,
                'is_default' => false,
            ]);

            TicketTemplatePage::query()->create([
                'event_ticket_template_id' => $template->id,
                'page_number' => 2,
                'name' => 'Back',
                'definition' => ['version' => 1, 'elements' => []],
            ]);

            TicketTemplatePage::query()->create([
                'event_ticket_template_id' => $template->id,
                'page_number' => 1,
                'name' => 'Front',
                'definition' => ['version' => 1, 'elements' => []],
            ]);
        }

        $order = app(TicketOrderService::class)->createOrder(
            event: $event,
            buyer: [
                'name' => 'Renderer Holder',
                'phone' => '255700000001',
                'email' => 'renderer@example.com',
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

        $ticket = app(TicketIssuanceService::class)
            ->issueForOrder($order->fresh())
            ->firstOrFail();

        return [$ticket, $template];
    }
}
