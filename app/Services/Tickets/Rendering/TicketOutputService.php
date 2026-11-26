<?php

namespace App\Services\Tickets\Rendering;

use App\Data\Tickets\RenderedTicketPage;
use App\Models\Ticket;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use RuntimeException;
use Symfony\Component\Process\Process;

final class TicketOutputService
{
    public const MAX_PAGES = 8;
    private const MAX_TOTAL_PIXELS = 32000000;
    private const MAX_TOTAL_BYTES = 33554432;

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

        if ($context->pages->count() > self::MAX_PAGES
            || $context->pages->count() * $context->width * $context->height > self::MAX_TOTAL_PIXELS) {
            throw new RuntimeException('Ticket template exceeds the page or total pixel limit.');
        }

        $pages = [];
        $size = 0;
        foreach ($context->pages as $page) {
            $rendered = $this->svgRenderer->render($context, $page);
            $size += strlen($rendered->svg);
            if ($size > self::MAX_TOTAL_BYTES) {
                throw new RuntimeException('Ticket template exceeds the total SVG output limit.');
            }
            $pages[] = $rendered;
        }

        return $pages;
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

        $pages = [];
        $size = 0;
        foreach ($renderedPages as $page) {
            $png = $this->rasterize($page);
            $size += strlen($png);
            if ($size > self::MAX_TOTAL_BYTES) {
                throw new RuntimeException('Ticket exceeds the total raster output limit.');
            }
            $pages[] = [
                'dataUri' => 'data:image/png;base64,'.base64_encode($png),
                'width' => $page->width,
                'height' => $page->height,
            ];
        }

        $paper = [
            0,
            0,
            $width * 72 / 96,
            $height * 72 / 96,
        ];

        $bytes = Pdf::loadView('public.tickets.pdf', [
            'pages' => $pages,
        ])->setOptions([
            'isRemoteEnabled' => false,
            'isPhpEnabled' => false,
            'isJavascriptEnabled' => false,
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
        $process = new Process([
            '/usr/bin/rsvg-convert',
            '--format=png',
            '--width=' . $page->width,
            '--height=' . $page->height,
        ]);

        $process->setInput($page->svg);
        $process->setTimeout(30);
        $process->mustRun();

        $bytes = $process->getOutput();

        if (
            strlen($bytes) > self::MAX_TOTAL_BYTES
            || $bytes === ''
            || ! str_starts_with(
                $bytes,
                "\x89PNG\r\n\x1a\n"
            )
        ) {
            throw new RuntimeException(
                'Ticket SVG rasterization did not produce a valid PNG.'
            );
        }

        return $bytes;
    }
}
