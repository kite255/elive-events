<?php

namespace Tests\Unit\Tickets;

use App\Services\Tickets\Rendering\TicketAssetResolver;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TicketAssetResolverTest extends TestCase
{
    public function test_it_returns_a_data_uri_for_a_valid_local_png(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('tickets/logo.png', $this->png());

        $uri = app(TicketAssetResolver::class)
            ->dataUri('tickets/logo.png');

        $this->assertNotNull($uri);
        $this->assertStringStartsWith('data:image/png;base64,', $uri);
    }

    public function test_it_rejects_missing_remote_traversal_and_unsupported_assets(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('tickets/file.txt', 'not an image');

        $resolver = app(TicketAssetResolver::class);

        foreach ([
            null,
            '',
            'missing.png',
            '../secret.png',
            '/absolute.png',
            'https://example.com/image.png',
            'data:image/png;base64,abc',
            'tickets/file.txt',
        ] as $path) {
            $this->assertNull($resolver->dataUri($path));
        }
    }

    private function png(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=',
            true
        );
    }
}
