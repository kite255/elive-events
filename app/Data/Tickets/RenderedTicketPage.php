<?php

namespace App\Data\Tickets;

final readonly class RenderedTicketPage
{
    public function __construct(
        public int $pageNumber,
        public string $pageName,
        public int $width,
        public int $height,
        public string $svg,
        public string $pngFilename,
    ) {
    }
}
