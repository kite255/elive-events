<?php

namespace Tests\Unit\Tickets;

use App\Data\Tickets\RenderedTicketPage;
use App\Services\Tickets\Rendering\TicketOutputService;
use ReflectionClass;
use Tests\TestCase;

class TicketOutputServiceTest extends TestCase
{
    public function test_it_rasterizes_svg_with_an_embedded_image(): void
    {
        $embeddedPng =
            'iVBORw0KGgoAAAANSUhEUgAAABQAAAAUAQMAAAC3R49O'
            .'AAAAIGNIUk0AAHomAACAhAAA+gAAAIDoAAB1MAAA6mAA'
            .'ADqYAAAXcJy6UTwAAAAGUExURf8AAP///0EdNBEAAAAB'
            .'YktHRAH/Ai3eAAAADElEQVQI12NgoC4AAABQAAEiE+h1'
            .'AAAAAElFTkSuQmCC';

        $svg = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg"
                width="100"
                height="100"
                viewBox="0 0 100 100">
                <rect width="100" height="100" fill="#FFFFFF"/>
                <image
                    x="10"
                    y="10"
                    width="80"
                    height="80"
                    href="data:image/png;base64,%s"
                />
            </svg>',
            $embeddedPng
        );

        $page = new RenderedTicketPage(
            pageNumber: 1,
            pageName: 'Test page',
            width: 100,
            height: 100,
            svg: $svg,
            pngFilename: 'test-ticket-page-1.png',
        );

        $reflection = new ReflectionClass(
            TicketOutputService::class
        );

        $service = $reflection->newInstanceWithoutConstructor();
        $rasterize = $reflection->getMethod('rasterize');
        $rasterize->setAccessible(true);

        $png = $rasterize->invoke($service, $page);

        $this->assertStringStartsWith(
            "\x89PNG\r\n\x1a\n",
            $png
        );

        $this->assertGreaterThan(100, strlen($png));
    }
}
