<?php

namespace App\Services\Tickets\Rendering;

use App\Data\Tickets\RenderedTicketPage;
use App\Data\Tickets\TicketRenderContext;
use App\Models\TicketTemplatePage;
use App\Services\Tickets\TicketTemplateDefinitionValidator;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

final class TicketSvgRenderer
{
    private const QR_SOURCE_SIZE = 800;

    private const QR_MARGIN = 4;

    private const FONTS = [
        'Creato Display',
        'Arial',
        'Helvetica',
        'Georgia',
        'Times New Roman',
    ];

    public function __construct(
        private readonly TicketAssetResolver $assets,
        private readonly TicketTemplateDefinitionValidator $validator,
    ) {
    }

    public function render(
        TicketRenderContext $context,
        TicketTemplatePage $page
    ): RenderedTicketPage {
        $definition = is_array($page->definition)
            ? $page->definition
            : [];

        $definition = $this->validator->validate(
            $definition,
            $context->width,
            $context->height
        );

        $parts = [
            sprintf(
                '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d">',
                $context->width,
                $context->height,
                $context->width,
                $context->height
            ),
            sprintf(
                '<rect width="%d" height="%d" fill="#FFFFFF"/>',
                $context->width,
                $context->height
            ),
        ];

        $background = $this->assets->dataUri(
            $page->background_image_path
        );

        if ($background) {
            $parts[] = sprintf(
                '<image x="0" y="0" width="%d" height="%d" preserveAspectRatio="xMidYMid meet" href="%s"/>',
                $context->width,
                $context->height,
                $this->xml($background)
            );
        }

        foreach ($definition['elements'] as $element) {
            $rendered = $this->renderElement($element, $context);

            if ($rendered !== '') {
                $parts[] = $rendered;
            }
        }

        $parts[] = '</svg>';

        return new RenderedTicketPage(
            pageNumber: (int) $page->page_number,
            pageName: (string) $page->name,
            width: $context->width,
            height: $context->height,
            svg: implode('', $parts),
            pngFilename: $this->filename(
                $context->ticketNumber,
                (int) $page->page_number
            ),
        );
    }

    private function renderElement(
        array $element,
        TicketRenderContext $context
    ): string {
        return match ($element['type'] ?? null) {
            'text' => $this->renderText($element, $context),
            'qr' => $this->renderQr($element, $context),
            'image', 'logo', 'sponsor_logo' => $this->renderImage($element),
            'shape' => $this->renderShape($element),
            'line' => $this->renderLine($element),
            default => '',
        };
    }

    private function renderText(
        array $element,
        TicketRenderContext $context
    ): string {
        $style = is_array($element['style'] ?? null)
            ? $element['style']
            : [];
        [$x, $y, $width, $height, $rotation] = $this->geometry($element);
        $fontSize = $this->number($style['fontSize'] ?? 24, 24, 1, 500);
        $fontWeight = (int) $this->number($style['fontWeight'] ?? 400, 400, 100, 900);
        $fontFamily = in_array($style['fontFamily'] ?? null, self::FONTS, true)
            ? $style['fontFamily']
            : 'Arial';
        $alignment = in_array($style['textAlign'] ?? null, ['left', 'center', 'right'], true)
            ? $style['textAlign']
            : 'center';
        $anchor = ['left' => 'start', 'center' => 'middle', 'right' => 'end'][$alignment];
        $textX = match ($alignment) {
            'left' => $x,
            'right' => $x + $width,
            default => $x + ($width / 2),
        };
        $textY = $y + ($height / 2) + ($fontSize * 0.35);
        $value = $context->binding((string) ($element['binding'] ?? ''));

        return sprintf(
            '<text x="%s" y="%s" text-anchor="%s" font-family="%s" font-size="%s" font-weight="%d" fill="%s" opacity="%s" transform="%s">%s</text>',
            $this->decimal($textX),
            $this->decimal($textY),
            $anchor,
            $this->xml($fontFamily),
            $this->decimal($fontSize),
            $fontWeight,
            $this->color($style['color'] ?? null, '#161943'),
            $this->decimal($this->number($style['opacity'] ?? 1, 1, 0, 1)),
            $this->rotation($rotation, $x, $y, $width, $height),
            $this->xml($value)
        );
    }

    private function renderQr(
        array $element,
        TicketRenderContext $context
    ): string {
        if (($element['binding'] ?? null) !== 'ticket_qr') {
            return '';
        }

        [$x, $y, $width, $height, $rotation] = $this->geometry($element);
        $size = min($width, $height);
        $qrX = $x + (($width - $size) / 2);
        $qrY = $y + (($height - $size) / 2);
        $qr = (string) QrCode::format('svg')
            ->size(self::QR_SOURCE_SIZE)
            ->margin(self::QR_MARGIN)
            ->generate($context->qrCredential);

        if (! preg_match('/<svg\b[^>]*>(.*)<\/svg>\s*$/s', $qr, $matches)) {
            return '';
        }

        $scale = $size / self::QR_SOURCE_SIZE;

        return sprintf(
            '<g transform="%s"><rect x="%s" y="%s" width="%s" height="%s" fill="#FFFFFF"/><g transform="translate(%s %s) scale(%s)" shape-rendering="crispEdges">%s</g></g>',
            $this->rotation($rotation, $x, $y, $width, $height),
            $this->decimal($qrX),
            $this->decimal($qrY),
            $this->decimal($size),
            $this->decimal($size),
            $this->decimal($qrX),
            $this->decimal($qrY),
            $this->decimal($scale),
            $matches[1]
        );
    }

    private function renderImage(array $element): string
    {
        $uri = $this->assets->dataUri(
            is_string($element['asset_path'] ?? null)
                ? $element['asset_path']
                : null
        );

        if (! $uri) {
            return '';
        }

        [$x, $y, $width, $height, $rotation] = $this->geometry($element);

        return sprintf(
            '<image x="%s" y="%s" width="%s" height="%s" preserveAspectRatio="xMidYMid meet" href="%s" transform="%s"/>',
            $this->decimal($x),
            $this->decimal($y),
            $this->decimal($width),
            $this->decimal($height),
            $this->xml($uri),
            $this->rotation($rotation, $x, $y, $width, $height)
        );
    }

    private function renderShape(array $element): string
    {
        [$x, $y, $width, $height, $rotation] = $this->geometry($element);
        $style = is_array($element['style'] ?? null) ? $element['style'] : [];

        return sprintf(
            '<rect x="%s" y="%s" width="%s" height="%s" fill="%s" opacity="%s" transform="%s"/>',
            $this->decimal($x),
            $this->decimal($y),
            $this->decimal($width),
            $this->decimal($height),
            $this->color($style['backgroundColor'] ?? $style['color'] ?? null, '#DDE3EE'),
            $this->decimal($this->number($style['opacity'] ?? 1, 1, 0, 1)),
            $this->rotation($rotation, $x, $y, $width, $height)
        );
    }

    private function renderLine(array $element): string
    {
        [$x, $y, $width, $height, $rotation] = $this->geometry($element);
        $style = is_array($element['style'] ?? null) ? $element['style'] : [];
        $centerY = $y + ($height / 2);

        return sprintf(
            '<line x1="%s" y1="%s" x2="%s" y2="%s" stroke="%s" stroke-width="%s" opacity="%s" transform="%s"/>',
            $this->decimal($x),
            $this->decimal($centerY),
            $this->decimal($x + $width),
            $this->decimal($centerY),
            $this->color($style['color'] ?? null, '#161943'),
            $this->decimal(max(1, $height)),
            $this->decimal($this->number($style['opacity'] ?? 1, 1, 0, 1)),
            $this->rotation($rotation, $x, $y, $width, $height)
        );
    }

    private function geometry(array $element): array
    {
        return [
            $this->number($element['x'] ?? 0, 0, -8000, 8000),
            $this->number($element['y'] ?? 0, 0, -8000, 8000),
            $this->number($element['width'] ?? 1, 1, 1, 8000),
            $this->number($element['height'] ?? 1, 1, 1, 8000),
            $this->number($element['rotation'] ?? 0, 0, -360, 360),
        ];
    }

    private function number(mixed $value, float $default, float $min, float $max): float
    {
        if (! is_numeric($value)) {
            return $default;
        }

        $number = (float) $value;

        return is_finite($number)
            ? max($min, min($max, $number))
            : $default;
    }

    private function color(mixed $value, string $default): string
    {
        return is_string($value)
            && preg_match('/^#(?:[0-9A-Fa-f]{3}|[0-9A-Fa-f]{6}|[0-9A-Fa-f]{8})$/', $value) === 1
                ? $value
                : $default;
    }

    private function rotation(float $rotation, float $x, float $y, float $width, float $height): string
    {
        return sprintf(
            'rotate(%s %s %s)',
            $this->decimal($rotation),
            $this->decimal($x + ($width / 2)),
            $this->decimal($y + ($height / 2))
        );
    }

    private function decimal(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function filename(string $ticketNumber, int $pageNumber): string
    {
        $safe = preg_replace('/[^A-Za-z0-9_-]+/', '-', $ticketNumber) ?: 'ticket';

        return trim($safe, '-') . '-page-' . $pageNumber . '.png';
    }
}
