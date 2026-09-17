<?php

namespace Tests\Unit;

use App\Services\WhatsAppService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppTicketAccessTemplateTest extends TestCase
{
    public function test_ticket_access_template_uses_dynamic_url_button_without_image_header(): void
    {
        config([
            'services.whatsapp.access_token' => 'test-token',
            'services.whatsapp.phone_number_id' => '123456789',
            'services.whatsapp.graph_version' => 'v24.0',
        ]);

        Http::fake([
            '*' => Http::response([
                'messages' => [
                    [
                        'id' => 'wamid.ticket-access',
                    ],
                ],
            ]),
        ]);

        $result = app(WhatsAppService::class)
            ->sendTemplate(
                phone: '0712345678',
                templateName: 'concert_tickets_delivery_en',
                languageCode: 'en',
                bodyParameters: [
                    'Ticket Buyer',
                    'Ticket Delivery Concert',
                    '3',
                    'ELV-ORD-001',
                ],
                imageUrl: null,
                urlButtons: [
                    [
                        'index' => 0,
                        'value' => 'secure-order-token',
                    ],
                ],
            );

        $this->assertSame(
            'wamid.ticket-access',
            $result['provider_message_id']
        );

        Http::assertSent(
            function (Request $request): bool {
                $components = collect(
                    data_get(
                        $request->data(),
                        'template.components',
                        []
                    )
                );

                $button = $components->firstWhere(
                    'type',
                    'button'
                );

                return data_get(
                    $request->data(),
                    'template.name'
                ) === 'concert_tickets_delivery_en'
                    && data_get(
                        $request->data(),
                        'template.language.code'
                    ) === 'en'
                    && $components->contains(
                        fn (array $component): bool =>
                            ($component['type'] ?? null)
                                === 'body'
                    )
                    && ! $components->contains(
                        fn (array $component): bool =>
                            ($component['type'] ?? null)
                                === 'header'
                    )
                    && data_get($button, 'sub_type')
                        === 'url'
                    && data_get($button, 'index')
                        === '0'
                    && data_get(
                        $button,
                        'parameters.0.text'
                    ) === 'secure-order-token';
            }
        );
    }
}
