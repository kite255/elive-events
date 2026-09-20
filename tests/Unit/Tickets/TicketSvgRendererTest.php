<?php

namespace Tests\Unit\Tickets;

use App\Data\Tickets\TicketRenderContext;
use App\Models\TicketTemplatePage;
use App\Services\Tickets\Rendering\TicketSvgRenderer;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class TicketSvgRendererTest extends TestCase
{
    public function test_it_renders_safe_ordered_svg_elements_and_secure_qr(): void
    {
        $credential = 'secure-qr-credential-value';
        $context = new TicketRenderContext(
            ticketNumber: 'ELV-001',
            holderName: 'Lucas & <Guest>',
            ticketType: 'VIP',
            eventName: 'Renderer Concert',
            eventDate: '17/09/2026',
            eventTime: '18:30',
            venue: 'Mlimani City',
            orderNumber: 'ORDER-001',
            ticketStatus: 'issued',
            qrCredential: $credential,
            templateSource: 'event_ticket_type',
            template: null,
            width: 1080,
            height: 1350,
            pages: new Collection(),
        );

        $page = new TicketTemplatePage();
        $page->forceFill([
            'page_number' => 1,
            'name' => 'Front',
            'definition' => [
                'version' => 1,
                'elements' => [
                    [
                        'id' => 'txt_holder',
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
                        'id' => 'qr_ticket',
                        'type' => 'qr',
                        'binding' => 'ticket_qr',
                        'x' => 100,
                        'y' => 250,
                        'width' => 300,
                        'height' => 350,
                        'rotation' => 0,
                    ],
                ],
            ],
        ]);

        $rendered = app(TicketSvgRenderer::class)
            ->render($context, $page);

        $this->assertStringContainsString('viewBox="0 0 1080 1350"', $rendered->svg);
        $this->assertStringContainsString('Lucas &amp; &lt;Guest&gt;', $rendered->svg);
        $this->assertStringContainsString('<path fill-rule="evenodd"', $rendered->svg);
        $this->assertStringNotContainsString('data:image/', $rendered->svg);
        $this->assertStringNotContainsString($credential, $rendered->svg);
        $this->assertSame('ELV-001-page-1.png', $rendered->pngFilename);
        $this->assertLessThan(
            strpos($rendered->svg, '<path fill-rule="evenodd"'),
            strpos($rendered->svg, 'Lucas &amp; &lt;Guest&gt;')
        );
    }
}
