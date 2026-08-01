<?php

namespace App\Services;

use App\Models\Attendee;
use App\Models\BadgeTemplate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Throwable;

class BadgeGenerationService
{
    public function generateForAttendee(Attendee $attendee): string
    {
        $attendee->loadMissing([
            'event',
            'category',
            'badgeType',
            'qrToken',
        ]);

        $this->updateBadgeState($attendee, [
            'badge_status' => 'generating',
        ]);

        try {
            $template = $this->resolveTemplate($attendee);
            $layout = $this->resolveLayout($template);

            $width = (int) ($template?->width ?? data_get($layout, 'canvas.width', 420));
            $height = (int) ($template?->height ?? data_get($layout, 'canvas.height', 620));

            $backgroundColor = $template?->background_color ?? '#F8FAFC';
            $backgroundImagePath = $template?->background_image_path;
            $headerColor = $template?->header_color ?? '#233F7E';
            $footerColor = $template?->footer_color ?? '#0B1F3A';

            $hasUploadedBackground = filled($backgroundImagePath)
                && Storage::disk('public')->exists($backgroundImagePath);

            $safeName = Str::slug($attendee->full_name ?: 'attendee');
            $path = 'badges/attendee-' . $attendee->id . '-' . $safeName . '.svg';

            $elementsSvg = $this->renderDesignedElements(
                attendee: $attendee,
                layout: $layout,
                width: $width,
            );

            $qrSvg = $this->renderQrCode(
                attendee: $attendee,
                x: (int) data_get($layout, 'qr_code.x', 150),
                y: (int) data_get($layout, 'qr_code.y', 465),
                width: (int) data_get($layout, 'qr_code.size', 120),
                height: (int) data_get($layout, 'qr_code.size', 120),
            );

            $defaultDecorations = $hasUploadedBackground
                ? ''
                : $this->renderDefaultDecorations(
                    attendee: $attendee,
                    layout: $layout,
                    width: $width,
                    height: $height,
                    footerColor: $footerColor,
                );

            $svg = <<<SVG
<svg width="{$width}" height="{$height}" viewBox="0 0 {$width} {$height}" xmlns="http://www.w3.org/2000/svg">
{$this->renderBackground($backgroundImagePath, $backgroundColor, $headerColor, $width, $height)}

{$defaultDecorations}

{$elementsSvg}

{$qrSvg}
</svg>
SVG;

            Storage::disk('public')->put($path, $svg);

            $this->updateBadgeState($attendee, [
                'badge_path' => $path,
                'badge_status' => 'generated',
                'badge_generated_at' => now(),
            ]);

            return $path;
        } catch (Throwable $e) {
            $this->updateBadgeState($attendee, [
                'badge_status' => 'failed',
            ]);

            throw $e;
        }
    }

    protected function renderBackground(
        ?string $backgroundImagePath,
        string $backgroundColor,
        string $headerColor,
        int $width,
        int $height
    ): string {
        if ($backgroundImagePath && Storage::disk('public')->exists($backgroundImagePath)) {
            $imageContent = Storage::disk('public')->get($backgroundImagePath);

            $mimeType = Storage::disk('public')->mimeType($backgroundImagePath)
                ?: $this->guessImageMimeType($backgroundImagePath);

            $encodedImage = base64_encode($imageContent);

            return <<<SVG
    <image href="data:{$mimeType};base64,{$encodedImage}" x="0" y="0" width="{$width}" height="{$height}" preserveAspectRatio="xMidYMid slice"/>
SVG;
        }

        return <<<SVG
    <rect width="{$width}" height="{$height}" rx="28" fill="{$backgroundColor}"/>
    <rect width="{$width}" height="150" rx="28" fill="{$headerColor}"/>
    <rect y="120" width="{$width}" height="60" fill="{$headerColor}"/>
SVG;
    }

    protected function renderDefaultDecorations(
        Attendee $attendee,
        array $layout,
        int $width,
        int $height,
        string $footerColor
    ): string {
        $centerX = $width / 2;
        $footerY = max(0, $height - 50);
        $footerTextY = $height - 18;

        $initials = e(strtoupper(Str::substr($attendee->full_name ?: 'G', 0, 1)));
        $categoryBackground = e(data_get($layout, 'category.background', '#F99A12'));

        return <<<SVG
    <text x="{$centerX}" y="62" text-anchor="middle" font-family="Arial, sans-serif" font-size="28" font-weight="800" fill="#FFFFFF">eLive Events</text>

    <circle cx="{$centerX}" cy="185" r="54" fill="{$categoryBackground}"/>
    <text x="{$centerX}" y="204" text-anchor="middle" font-family="Arial, sans-serif" font-size="46" font-weight="800" fill="#FFFFFF">{$initials}</text>

    <rect x="0" y="{$footerY}" width="{$width}" height="50" fill="{$footerColor}"/>
    <text x="{$centerX}" y="{$footerTextY}" text-anchor="middle" font-family="Arial, sans-serif" font-size="14" font-weight="700" fill="#FFFFFF">Powered by eLive Events</text>
SVG;
    }

    protected function guessImageMimeType(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            default => 'image/png',
        };
    }

    protected function renderDesignedElements(
        Attendee $attendee,
        array $layout,
        int $width
    ): string {
        if (isset($layout['elements']) && is_array($layout['elements'])) {
            return $this->renderFlexibleElements($attendee, $layout['elements'], $width);
        }

        return $this->renderFixedElements($attendee, $layout, $width);
    }

    protected function renderFixedElements(
        Attendee $attendee,
        array $layout,
        int $width
    ): string {
        $fullName = e(Str::limit($attendee->full_name ?? 'Guest', 28));
        $category = e(Str::limit($attendee->category?->name ?? $attendee->badgeType?->name ?? 'Guest', 20));
        $organization = e(Str::limit($attendee->organization_name ?? '', 34));
        $position = e(Str::limit($attendee->position ?? '', 28));
        $badgeNumber = e($attendee->badge_number ?? 'N/A');

        $nameX = (int) data_get($layout, 'name.x', 210);
        $nameY = (int) data_get($layout, 'name.y', 250);
        $nameFontSize = (int) data_get($layout, 'name.font_size', 30);
        $nameColor = e(data_get($layout, 'name.color', '#FFFFFF'));

        $categoryX = (int) data_get($layout, 'category.x', 210);
        $categoryY = (int) data_get($layout, 'category.y', 315);
        $categoryFontSize = (int) data_get($layout, 'category.font_size', 18);
        $categoryColor = e(data_get($layout, 'category.color', '#FFFFFF'));
        $categoryBackground = e(data_get($layout, 'category.background', '#F99A12'));

        $organizationX = (int) data_get($layout, 'organization.x', 210);
        $organizationY = (int) data_get($layout, 'organization.y', 360);
        $organizationFontSize = (int) data_get($layout, 'organization.font_size', 14);
        $organizationColor = e(data_get($layout, 'organization.color', '#DBEAFE'));

        $positionX = (int) data_get($layout, 'position.x', 210);
        $positionY = (int) data_get($layout, 'position.y', 385);
        $positionFontSize = (int) data_get($layout, 'position.font_size', 13);
        $positionColor = e(data_get($layout, 'position.color', '#E0F2FE'));

        $badgeNumberX = (int) data_get($layout, 'badge_number.x', 210);
        $badgeNumberY = (int) data_get($layout, 'badge_number.y', 420);
        $badgeNumberFontSize = (int) data_get($layout, 'badge_number.font_size', 13);
        $badgeNumberColor = e(data_get($layout, 'badge_number.color', '#FFFFFF'));

        $categoryBoxWidth = 230;
        $categoryBoxHeight = 38;
        $categoryBoxX = $categoryX - ($categoryBoxWidth / 2);
        $categoryBoxY = $categoryY - ($categoryBoxHeight / 2);
        $categoryTextY = $categoryY + 6;

        $badgeNumberLabelY = $badgeNumberY - 8;
        $badgeNumberValueY = $badgeNumberY + 8;

        $organizationSvg = filled($organization)
            ? <<<SVG
    <text x="{$organizationX}" y="{$organizationY}" text-anchor="middle" font-family="Arial, sans-serif" font-size="{$organizationFontSize}" font-weight="800" fill="{$organizationColor}" stroke="#000000" stroke-opacity="0.25" stroke-width="0.5" paint-order="stroke">{$organization}</text>
SVG
            : '';

        $positionSvg = filled($position)
            ? <<<SVG
    <text x="{$positionX}" y="{$positionY}" text-anchor="middle" font-family="Arial, sans-serif" font-size="{$positionFontSize}" font-weight="700" fill="{$positionColor}" stroke="#000000" stroke-opacity="0.25" stroke-width="0.4" paint-order="stroke">{$position}</text>
SVG
            : '';

        return <<<SVG
    <text x="{$nameX}" y="{$nameY}" text-anchor="middle" font-family="Arial, sans-serif" font-size="{$nameFontSize}" font-weight="900" fill="{$nameColor}" stroke="#000000" stroke-opacity="0.35" stroke-width="0.7" paint-order="stroke">{$fullName}</text>

    <rect x="{$categoryBoxX}" y="{$categoryBoxY}" width="{$categoryBoxWidth}" height="{$categoryBoxHeight}" rx="19" fill="{$categoryBackground}"/>
    <text x="{$categoryX}" y="{$categoryTextY}" text-anchor="middle" font-family="Arial, sans-serif" font-size="{$categoryFontSize}" font-weight="900" fill="{$categoryColor}">{$category}</text>

{$organizationSvg}

{$positionSvg}

    <text x="{$badgeNumberX}" y="{$badgeNumberLabelY}" text-anchor="middle" font-family="Arial, sans-serif" font-size="10" font-weight="700" fill="{$badgeNumberColor}" opacity="0.95" stroke="#000000" stroke-opacity="0.25" stroke-width="0.3" paint-order="stroke">Badge No.</text>
    <text x="{$badgeNumberX}" y="{$badgeNumberValueY}" text-anchor="middle" font-family="Arial, sans-serif" font-size="{$badgeNumberFontSize}" font-weight="900" fill="{$badgeNumberColor}" stroke="#000000" stroke-opacity="0.25" stroke-width="0.3" paint-order="stroke">{$badgeNumber}</text>
SVG;
    }

    protected function renderFlexibleElements(
        Attendee $attendee,
        array $elements,
        int $width
    ): string {
        $svg = '';

        foreach ($elements as $element) {
            if (! (bool) data_get($element, 'visible', true)) {
                continue;
            }

            $type = data_get($element, 'type');

            if ($type === 'qr_code') {
                continue;
            }

            $value = $this->resolveElementValue($type, $attendee);

            if (blank($value)) {
                continue;
            }

            $text = e(Str::limit($value, $this->limitForField($type)));

            $x = (int) data_get($element, 'x', 210);
            $y = (int) data_get($element, 'y', 300);
            $fontSize = (int) data_get($element, 'font_size', 16);
            $fontWeight = e((string) data_get($element, 'font_weight', '700'));
            $color = e(data_get($element, 'color', '#FFFFFF'));
            $align = data_get($element, 'align', 'center');

            $textAnchor = match ($align) {
                'left' => 'start',
                'right' => 'end',
                default => 'middle',
            };

            if ($type === 'category') {
                $background = e(data_get($element, 'background', '#F99A12'));
                $boxWidth = (int) data_get($element, 'width', 230);
                $boxHeight = (int) data_get($element, 'height', 38);
                $boxX = $x - ($boxWidth / 2);
                $boxY = $y - ($boxHeight / 2);
                $textY = $y + 6;

                $svg .= <<<SVG

    <rect x="{$boxX}" y="{$boxY}" width="{$boxWidth}" height="{$boxHeight}" rx="19" fill="{$background}"/>
    <text x="{$x}" y="{$textY}" text-anchor="{$textAnchor}" font-family="Arial, sans-serif" font-size="{$fontSize}" font-weight="{$fontWeight}" fill="{$color}">{$text}</text>
SVG;

                continue;
            }

            $strokeWidth = $fontSize >= 20 ? '0.7' : '0.4';

            $svg .= <<<SVG

    <text x="{$x}" y="{$y}" text-anchor="{$textAnchor}" font-family="Arial, sans-serif" font-size="{$fontSize}" font-weight="{$fontWeight}" fill="{$color}" stroke="#000000" stroke-opacity="0.25" stroke-width="{$strokeWidth}" paint-order="stroke">{$text}</text>
SVG;
        }

        return $svg;
    }

    protected function renderQrCode(Attendee $attendee, int $x, int $y, int $width, int $height): string
    {
        $token = app(QrTokenService::class)->generateForAttendee($attendee);

        $checkInUrl = url('/check-in/' . $token);

        $qrPath = 'qr-codes/attendee-' . $attendee->id . '.svg';

        $qrSvgContent = QrCode::format('svg')
            ->size(300)
            ->margin(1)
            ->generate($checkInUrl);

        Storage::disk('public')->put($qrPath, $qrSvgContent);

        $encodedQr = base64_encode($qrSvgContent);

        $innerPadding = 8;
        $innerX = $x + $innerPadding;
        $innerY = $y + $innerPadding;
        $innerWidth = max(20, $width - ($innerPadding * 2));
        $innerHeight = max(20, $height - ($innerPadding * 2));

        return <<<SVG
    <rect x="{$x}" y="{$y}" width="{$width}" height="{$height}" rx="10" fill="#FFFFFF" stroke="#E2E8F0"/>
    <image href="data:image/svg+xml;base64,{$encodedQr}" x="{$innerX}" y="{$innerY}" width="{$innerWidth}" height="{$innerHeight}" preserveAspectRatio="xMidYMid meet"/>
SVG;
    }

    protected function resolveLayout(?BadgeTemplate $template): array
    {
        $config = $template?->design_config ?? [];

        if (is_string($config)) {
            $config = json_decode($config, true) ?: [];
        }

        if (isset($config['elements']) && is_array($config['elements'])) {
            return [
                'canvas' => [
                    'width' => (int) data_get($config, 'canvas.width', $template?->width ?? 420),
                    'height' => (int) data_get($config, 'canvas.height', $template?->height ?? 620),
                    'background_image_path' => data_get($config, 'canvas.background_image_path', $template?->background_image_path),
                ],
                'elements' => $config['elements'],
                'qr_code' => $this->resolveQrFromFlexibleElements($config['elements']),
            ];
        }

        return [
            'name' => [
                'x' => (int) data_get($config, 'name.x', 210),
                'y' => (int) data_get($config, 'name.y', 250),
                'font_size' => (int) data_get($config, 'name.font_size', 30),
                'color' => data_get($config, 'name.color', '#FFFFFF'),
            ],

            'category' => [
                'x' => (int) data_get($config, 'category.x', 210),
                'y' => (int) data_get($config, 'category.y', 315),
                'font_size' => (int) data_get($config, 'category.font_size', 18),
                'color' => data_get($config, 'category.color', '#FFFFFF'),
                'background' => data_get($config, 'category.background', '#F99A12'),
            ],

            'organization' => [
                'x' => (int) data_get($config, 'organization.x', 210),
                'y' => (int) data_get($config, 'organization.y', 360),
                'font_size' => (int) data_get($config, 'organization.font_size', 14),
                'color' => data_get($config, 'organization.color', '#DBEAFE'),
            ],

            'position' => [
                'x' => (int) data_get($config, 'position.x', 210),
                'y' => (int) data_get($config, 'position.y', 385),
                'font_size' => (int) data_get($config, 'position.font_size', 13),
                'color' => data_get($config, 'position.color', '#E0F2FE'),
            ],

            'badge_number' => [
                'x' => (int) data_get($config, 'badge_number.x', 210),
                'y' => (int) data_get($config, 'badge_number.y', 420),
                'font_size' => (int) data_get($config, 'badge_number.font_size', 13),
                'color' => data_get($config, 'badge_number.color', '#FFFFFF'),
            ],

            'qr_code' => [
                'x' => (int) data_get($config, 'qr_code.x', 150),
                'y' => (int) data_get($config, 'qr_code.y', 465),
                'size' => (int) data_get($config, 'qr_code.size', 120),
            ],
        ];
    }

    protected function resolveQrFromFlexibleElements(array $elements): array
    {
        foreach ($elements as $element) {
            if (data_get($element, 'type') !== 'qr_code') {
                continue;
            }

            return [
                'x' => (int) data_get($element, 'x', 150),
                'y' => (int) data_get($element, 'y', 465),
                'size' => (int) data_get($element, 'size', 120),
            ];
        }

        return [
            'x' => 150,
            'y' => 465,
            'size' => 120,
        ];
    }

    protected function resolveElementValue(?string $fieldKey, Attendee $attendee): string
    {
        return match ($fieldKey) {
            'attendee_name', 'full_name', 'name' => $attendee->full_name ?? '',
            'event_name' => $attendee->event?->name ?? $attendee->event?->title ?? '',
            'event_date' => optional($attendee->event?->starts_at)->format('d M Y') ?? '',
            'event_venue' => $attendee->event?->venue ?? $attendee->event?->venue_name ?? '',
            'category' => $attendee->category?->name ?? $attendee->badgeType?->name ?? '',
            'badge_type' => $attendee->badgeType?->name ?? '',
            'badge_number' => $attendee->badge_number ?? '',
            'organization_name', 'organization' => $attendee->organization_name ?? '',
            'position' => $attendee->position ?? '',
            'phone' => $attendee->phone ?? '',
            'email' => $attendee->email ?? '',
            default => '',
        };
    }

    protected function limitForField(?string $fieldKey): int
    {
        return match ($fieldKey) {
            'attendee_name', 'full_name', 'name' => 28,
            'event_name' => 36,
            'event_date' => 24,
            'event_venue' => 36,
            'category' => 20,
            'badge_type' => 20,
            'badge_number' => 28,
            'organization_name', 'organization' => 34,
            'position' => 30,
            'phone' => 20,
            'email' => 32,
            default => 30,
        };
    }

    protected function resolveTemplate(Attendee $attendee): ?BadgeTemplate
    {
        $baseQuery = fn () => BadgeTemplate::query()
            ->with('elements')
            ->where('is_active', true);

        if ($attendee->badge_type_id) {
            $template = $baseQuery()
                ->where('event_id', $attendee->event_id)
                ->where('badge_type_id', $attendee->badge_type_id)
                ->latest()
                ->first();

            if ($template) {
                return $template;
            }
        }

        if ($attendee->category_id) {
            $template = $baseQuery()
                ->where('event_id', $attendee->event_id)
                ->where('category_id', $attendee->category_id)
                ->latest()
                ->first();

            if ($template) {
                return $template;
            }
        }

        $template = $baseQuery()
            ->where('event_id', $attendee->event_id)
            ->where('is_default', true)
            ->whereNull('category_id')
            ->whereNull('badge_type_id')
            ->latest()
            ->first();

        if ($template) {
            return $template;
        }

        $template = $baseQuery()
            ->whereNull('event_id')
            ->where('is_default', true)
            ->latest()
            ->first();

        if ($template) {
            return $template;
        }

        return $baseQuery()
            ->latest()
            ->first();
    }

    protected function updateBadgeState(Attendee $attendee, array $data): void
    {
        $allowed = [];

        foreach ($data as $column => $value) {
            if (Schema::hasColumn('attendees', $column)) {
                $allowed[$column] = $value;
            }
        }

        if ($allowed !== []) {
            $attendee->forceFill($allowed)->save();
        }
    }
}