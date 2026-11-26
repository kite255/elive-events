<?php

namespace Tests\Unit\Tickets;

use App\Services\Tickets\Rendering\TicketAssetResolver;
use App\Models\EventTicketTemplate;
use App\Models\TicketTemplatePage;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TicketAssetResolverTest extends TestCase
{
    public function test_it_returns_a_data_uri_for_a_valid_local_png(): void
    {
        Storage::fake('public');
        [$template, $page] = $this->context();
        $path = 'ticket-template-elements/event/16/logo.png';
        Storage::disk('public')->put($path, $this->png());

        $uri = app(TicketAssetResolver::class)
            ->dataUri($path, $template, $page);

        $this->assertNotNull($uri);
        $this->assertStringStartsWith('data:image/png;base64,', $uri);
    }

    public function test_it_rejects_missing_remote_traversal_and_unsupported_assets(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('tickets/file.txt', 'not an image');

        [$template, $page] = $this->context();
        $resolver = app(TicketAssetResolver::class);
        Storage::disk('public')->put('ticket-template-elements/event/17/logo.png', $this->png());
        Storage::disk('public')->put('tickets/logo.png', $this->png());
        Storage::disk('public')->put('ticket-template-elements/event/16/invalid.png', 'not an image');

        foreach ([
            null,
            '',
            'missing.png',
            '../secret.png',
            '/absolute.png',
            'https://example.com/image.png',
            'data:image/png;base64,abc',
            'tickets/file.txt',
            'tickets/logo.png',
            'ticket-template-elements/event/17/logo.png',
            'ticket-template-elements/event/16/invalid.png',
            'ticket-template-elements/event/16/missing.png',
        ] as $path) {
            $this->assertNull($resolver->dataUri($path, $template, $page));
        }
    }

    public function test_it_requires_matching_template_and_page_context(): void
    {
        Storage::fake('public');
        [$template, $page] = $this->context();
        $path = 'ticket-template-elements/event/16/logo.png';
        Storage::disk('public')->put($path, $this->png());
        $resolver = app(TicketAssetResolver::class);
        $this->assertNull($resolver->dataUri($path));
        $page->forceFill(['event_ticket_template_id' => 17]);
        $this->assertNull($resolver->dataUri($path, $template, $page));
    }

    private function context(): array
    {
        $template = new EventTicketTemplate();
        $template->forceFill(['id' => 16]);
        $page = new TicketTemplatePage();
        $page->forceFill(['event_ticket_template_id' => 16]);

        return [$template, $page];
    }

    private function png(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true
        );
    }
}
