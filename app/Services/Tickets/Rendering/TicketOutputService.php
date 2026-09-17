<?php

namespace App\Services\Tickets\Rendering;

use App\Data\Tickets\RenderedTicketPage;
use App\Models\Ticket;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Imagick;

final class TicketOutputService
{
    public function __construct(
        private readonly TicketRenderContextFactory $contexts,
        private readonly TicketSvgRenderer $svgRenderer,
    ) {
    }

    /**
     * @return array<int, RenderedTicketPage>
     */
    public function renderPages(Ticket $ticket): array
    {
        $context = $this->contexts->make($ticket);

        if ($context->templateSource === 'standard' || $context->pages->isEmpty()) {
            return [];
        }

        return $context->pages
            ->map(fn ($page): RenderedTicketPage =>
                $this->svgRenderer->render($context, $page))
            ->values()
            ->all();
    }

    /**
     * @return array{bytes: string, filename: string}
     */
    public function png(Ticket $ticket, int $pageNumber): array
    {
        $page = collect($this->renderPages($ticket))
            ->first(fn (RenderedTicketPage $page): bool =>
                $page->pageNumber === $pageNumber);

        if (! $page) {
            throw (new ModelNotFoundException())
                ->setModel(RenderedTicketPage::class);
        }

        return [
            'bytes' => $this->rasterize($page),
            'filename' => $page->pngFilename,
        ];
    }

    /**
     * @return array{bytes: string, filename: string}
     */
    public function pdf(Ticket $ticket): array
    {
        $renderedPages = $this->renderPages($ticket);

        if ($renderedPages === []) {
            throw (new ModelNotFoundException())
                ->setModel(RenderedTicketPage::class);
        }

        $width = $renderedPages[0]->width;
        $height = $renderedPages[0]->height;

        foreach ($renderedPages as $page) {
            if ($page->width !== $width || $page->height !== $height) {
                throw new \RuntimeException('Ticket template pages must share one size.');
            }
        }

        $pages = array_map(fn (RenderedTicketPage $page): array => [
            'dataUri' => 'data:image/png;base64,' . base64_encode($this->rasterize($page)),
            'width' => $page->width,
            'height' => $page->height,
        ], $renderedPages);

        $paper = [
            0,
            0,
            $width * 72 / 96,
            $height * 72 / 96,
        ];

        $bytes = Pdf::loadView('public.tickets.pdf', [
            'pages' => $pages,
        ])->setPaper($paper)->output();

        $safe = preg_replace('/[^A-Za-z0-9_-]+/', '-', $ticket->ticket_number)
            ?: 'ticket';

        return [
            'bytes' => $bytes,
            'filename' => trim($safe, '-') . '-ticket.pdf',
        ];
    }

    private function rasterize(RenderedTicketPage $page): string
    {
        $image = new Imagick();

        try {
            $image->setBackgroundColor('white');
            $image->readImageBlob($page->svg);
            $image->setImageFormat('png');
            $image->setImageBackgroundColor('white');

            $flattened = $image->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
            $flattened->setImageFormat('png');
            $bytes = $flattened->getImageBlob();
            $flattened->clear();
            $flattened->destroy();

            return $bytes;
        } finally {
            $image->clear();
            $image->destroy();
        }
    }
}
