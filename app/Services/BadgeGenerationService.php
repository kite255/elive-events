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
    /*
    |--------------------------------------------------------------------------
    | Generate Badge
    |--------------------------------------------------------------------------
    */

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

            if (! $template) {
                throw new \RuntimeException(
                    'No active badge template was found for this attendee.'
                );
            }

            $layout = $this->resolveLayout($template);

            /*
            |--------------------------------------------------------------------------
            | Canvas
            |--------------------------------------------------------------------------
            */

            $width = (int) data_get(
                $layout,
                'canvas.width',
                $template->width ?: 1638
            );

            $height = (int) data_get(
                $layout,
                'canvas.height',
                $template->height ?: 2048
            );

            $backgroundColor = $template->background_color ?: '#FFFFFF';

            $backgroundImagePath = $template->backgroundImagePath();

            $safeName = Str::slug(
                $attendee->full_name ?: 'attendee'
            );

            /*
            |--------------------------------------------------------------------------
            | Generated Badge Path
            |--------------------------------------------------------------------------
            */

            $path = sprintf(
                'events/%s/badges/attendee-%s-%s.svg',
                $attendee->event_id,
                $attendee->id,
                $safeName
            );

            /*
            |--------------------------------------------------------------------------
            | Dynamic Elements
            |--------------------------------------------------------------------------
            */

            $elementsSvg = $this->renderDesignedElements(
                attendee: $attendee,
                layout: $layout,
                width: $width,
            );

            /*
            |--------------------------------------------------------------------------
            | QR
            |--------------------------------------------------------------------------
            */

            $qrConfig = data_get(
                $layout,
                'qr_code',
                []
            );

            $qrSvg = '';

            if ((bool) data_get(
                $qrConfig,
                'visible',
                true
            )) {
                $qrSvg = $this->renderQrCode(
                    attendee: $attendee,

                    centerX: (int) data_get(
                        $qrConfig,
                        'x',
                        (int) ($width / 2)
                    ),

                    y: (int) data_get(
                        $qrConfig,
                        'y',
                        1365
                    ),

                    size: (int) data_get(
                        $qrConfig,
                        'size',
                        470
                    ),

                    padding: (int) data_get(
                        $qrConfig,
                        'padding',
                        20
                    ),
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Background
            |--------------------------------------------------------------------------
            */

            $backgroundSvg = $this->renderBackground(
                backgroundImagePath: $backgroundImagePath,
                backgroundColor: $backgroundColor,
                width: $width,
                height: $height,
            );

            /*
            |--------------------------------------------------------------------------
            | Final SVG
            |--------------------------------------------------------------------------
            */

            $svg = <<<SVG
<svg
    width="{$width}"
    height="{$height}"
    viewBox="0 0 {$width} {$height}"
    xmlns="http://www.w3.org/2000/svg"
>
{$backgroundSvg}

{$elementsSvg}

{$qrSvg}
</svg>
SVG;

            Storage::disk('public')->put(
                $path,
                $svg
            );

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

    /*
    |--------------------------------------------------------------------------
    | Background
    |--------------------------------------------------------------------------
    */

    protected function renderBackground(
        ?string $backgroundImagePath,
        string $backgroundColor,
        int $width,
        int $height
    ): string {
        if (
            filled($backgroundImagePath)
            && Storage::disk('public')->exists($backgroundImagePath)
        ) {
            $imageContent = Storage::disk('public')
                ->get($backgroundImagePath);

            $mimeType = Storage::disk('public')
                ->mimeType($backgroundImagePath)
                ?: $this->guessImageMimeType(
                    $backgroundImagePath
                );

            $encodedImage = base64_encode(
                $imageContent
            );

            /*
            |--------------------------------------------------------------------------
            | Full Artwork Background
            |--------------------------------------------------------------------------
            |
            | The uploaded Camp Meeting artwork is already the finished
            | background, therefore we do not draw headers, logos or footers.
            |
            */

            return <<<SVG
    <image
        href="data:{$mimeType};base64,{$encodedImage}"
        x="0"
        y="0"
        width="{$width}"
        height="{$height}"
        preserveAspectRatio="none"
    />
SVG;
        }

        /*
        |--------------------------------------------------------------------------
        | Fallback
        |--------------------------------------------------------------------------
        */

        return <<<SVG
    <rect
        x="0"
        y="0"
        width="{$width}"
        height="{$height}"
        fill="{$backgroundColor}"
    />
SVG;
    }

    protected function guessImageMimeType(string $path): string
    {
        return match (
            strtolower(
                pathinfo(
                    $path,
                    PATHINFO_EXTENSION
                )
            )
        ) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            default => 'image/png',
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Designed Elements
    |--------------------------------------------------------------------------
    */

    protected function renderDesignedElements(
        Attendee $attendee,
        array $layout,
        int $width
    ): string {
        $enabledElements = data_get(
            $layout,
            'enabled_elements',
            [
                'category',
                'name',
                'qr_code',
            ]
        );

        $svg = '';

        /*
        |--------------------------------------------------------------------------
        | Category
        |--------------------------------------------------------------------------
        |
        | Example:
        |
        | MEDIA CREW
        |
        */

        if (
            in_array(
                'category',
                $enabledElements,
                true
            )
            && (bool) data_get(
                $layout,
                'category.visible',
                true
            )
        ) {
            $category = $attendee->category?->name
                ?? $attendee->badgeType?->name
                ?? 'Guest';

            $svg .= $this->renderTextElement(
                value: $category,
                config: data_get(
                    $layout,
                    'category',
                    []
                ),

                defaultX: (int) ($width / 2),

                defaultY: 1050,

                defaultFontSize: 130,

                defaultMinFontSize: 70,

                defaultWidth: 1350,

                defaultWeight: '400',

                defaultColor: '#FFFFFF',

                defaultFontFamily: 'Bebas Neue',
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Attendee Full Name
        |--------------------------------------------------------------------------
        */

        if (
            in_array(
                'name',
                $enabledElements,
                true
            )
            && (bool) data_get(
                $layout,
                'name.visible',
                true
            )
        ) {
            $name = $attendee->full_name
                ?: 'Guest';

            $svg .= $this->renderTextElement(
                value: $name,
                config: data_get(
                    $layout,
                    'name',
                    []
                ),

                defaultX: (int) ($width / 2),

                defaultY: 1235,

                defaultFontSize: 82,

                defaultMinFontSize: 45,

                defaultWidth: 1250,

                defaultWeight: '400',

                defaultColor: '#FFFFFF',

                defaultFontFamily: 'Bebas Neue',
            );
        }

        return $svg;
    }

    /*
    |--------------------------------------------------------------------------
    | Render Text Element
    |--------------------------------------------------------------------------
    */

    protected function renderTextElement(
        string $value,
        array $config,
        int $defaultX,
        int $defaultY,
        int $defaultFontSize,
        int $defaultMinFontSize,
        int $defaultWidth,
        string $defaultWeight,
        string $defaultColor,
        string $defaultFontFamily = 'Bebas Neue',
    ): string {
        /*
        |--------------------------------------------------------------------------
        | Text
        |--------------------------------------------------------------------------
        */

        $uppercase = (bool) data_get(
            $config,
            'uppercase',
            true
        );

        $value = trim($value);

        if ($uppercase) {
            $value = Str::upper(
                $value
            );
        }

        if ($value === '') {
            return '';
        }

        /*
        |--------------------------------------------------------------------------
        | Position
        |--------------------------------------------------------------------------
        */

        $x = (int) data_get(
            $config,
            'x',
            $defaultX
        );

        $y = (int) data_get(
            $config,
            'y',
            $defaultY
        );

        $maxWidth = (int) data_get(
            $config,
            'width',
            $defaultWidth
        );

        /*
        |--------------------------------------------------------------------------
        | Font Size
        |--------------------------------------------------------------------------
        */

        $fontSize = (int) data_get(
            $config,
            'font_size',
            $defaultFontSize
        );

        $minFontSize = (int) data_get(
            $config,
            'min_font_size',
            $defaultMinFontSize
        );

        /*
        |--------------------------------------------------------------------------
        | Font Family
        |--------------------------------------------------------------------------
        |
        | Bebas Neue closely matches the tall narrow typography used in the
        | supplied Camp Meeting badge.
        |
        */

        $fontFamily = trim(
            (string) data_get(
                $config,
                'font_family',
                $defaultFontFamily
            )
        );

        if ($fontFamily === '') {
            $fontFamily = $defaultFontFamily;
        }

        /*
        |--------------------------------------------------------------------------
        | Auto Fit
        |--------------------------------------------------------------------------
        */

        $fontSize = $this->fitFontSize(
            text: $value,
            desiredFontSize: $fontSize,
            minimumFontSize: $minFontSize,
            maxWidth: $maxWidth,
            fontFamily: $fontFamily,
        );

        /*
        |--------------------------------------------------------------------------
        | Weight
        |--------------------------------------------------------------------------
        */

        $fontWeight = e(
            (string) data_get(
                $config,
                'font_weight',
                $defaultWeight
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Color
        |--------------------------------------------------------------------------
        */

        $color = e(
            (string) data_get(
                $config,
                'color',
                $defaultColor
            )
        );

        /*
        |--------------------------------------------------------------------------
        | Alignment
        |--------------------------------------------------------------------------
        */

        $align = data_get(
            $config,
            'align',
            'center'
        );

        $textAnchor = match ($align) {
            'left' => 'start',
            'right' => 'end',
            default => 'middle',
        };

        $safeText = e(
            $value
        );

        /*
        |--------------------------------------------------------------------------
        | SVG Font Stack
        |--------------------------------------------------------------------------
        |
        | Bebas Neue is the preferred font.
        |
        | Arial Narrow is the first fallback.
        |
        */

        $safeFontFamily = e(
            $fontFamily
        );

        $svgFontStack =
            "'{$safeFontFamily}', 'Arial Narrow', 'Liberation Sans Narrow', Arial, sans-serif";

        /*
        |--------------------------------------------------------------------------
        | Very Light Shadow
        |--------------------------------------------------------------------------
        |
        | The original design is clean, therefore we keep the shadow subtle.
        |
        */

        $shadowY = $y + 2;

        return <<<SVG

    <text
        x="{$x}"
        y="{$shadowY}"
        text-anchor="{$textAnchor}"
        font-family="{$svgFontStack}"
        font-size="{$fontSize}"
        font-weight="{$fontWeight}"
        fill="#000000"
        opacity="0.12"
    >{$safeText}</text>

    <text
        x="{$x}"
        y="{$y}"
        text-anchor="{$textAnchor}"
        font-family="{$svgFontStack}"
        font-size="{$fontSize}"
        font-weight="{$fontWeight}"
        fill="{$color}"
    >{$safeText}</text>
SVG;
    }

    /*
    |--------------------------------------------------------------------------
    | Automatic Font Fitting
    |--------------------------------------------------------------------------
    */

    protected function fitFontSize(
        string $text,
        int $desiredFontSize,
        int $minimumFontSize,
        int $maxWidth,
        string $fontFamily = 'Bebas Neue'
    ): int {
        $fontSize = max(
            $minimumFontSize,
            $desiredFontSize
        );

        /*
        |--------------------------------------------------------------------------
        | Width Factor
        |--------------------------------------------------------------------------
        |
        | Bebas Neue is a condensed typeface.
        |
        | Arial tends to average around 0.60 - 0.65.
        |
        | Bebas Neue is considerably narrower, therefore approximately 0.50
        | works better for our badge text fitting.
        |
        */

        $widthFactor = match (
            strtolower($fontFamily)
        ) {
            'bebas neue' => 0.50,
            'arial narrow' => 0.52,
            default => 0.60,
        };

        while (
            $fontSize > $minimumFontSize
        ) {
            $estimatedWidth =
                mb_strlen($text)
                * $fontSize
                * $widthFactor;

            if (
                $estimatedWidth <= $maxWidth
            ) {
                break;
            }

            $fontSize -= 2;
        }

        return max(
            $minimumFontSize,
            $fontSize
        );
    }

    /*
    |--------------------------------------------------------------------------
    | QR Code
    |--------------------------------------------------------------------------
    */

    protected function renderQrCode(
        Attendee $attendee,
        int $centerX,
        int $y,
        int $size,
        int $padding = 20
    ): string {
        /*
        |--------------------------------------------------------------------------
        | Secure Token
        |--------------------------------------------------------------------------
        */

        $token = app(
            QrTokenService::class
        )->generateForAttendee(
            $attendee
        );

        $checkInUrl = url(
            '/check-in/' . $token
        );

        /*
        |--------------------------------------------------------------------------
        | Save Reusable QR
        |--------------------------------------------------------------------------
        */

        $qrPath = sprintf(
            'events/%s/qr-codes/attendee-%s.svg',
            $attendee->event_id,
            $attendee->id
        );

        $qrSvgContent = QrCode::format('svg')
            ->size(500)
            ->margin(0)
            ->generate(
                $checkInUrl
            );

        Storage::disk('public')->put(
            $qrPath,
            $qrSvgContent
        );

        /*
        |--------------------------------------------------------------------------
        | QR Center Position
        |--------------------------------------------------------------------------
        */

        $x = (int) round(
            $centerX
            - ($size / 2)
        );

        $encodedQr = base64_encode(
            $qrSvgContent
        );

        /*
        |--------------------------------------------------------------------------
        | QR Quiet Zone
        |--------------------------------------------------------------------------
        */

        $padding = max(
            0,
            min(
                $padding,
                (int) ($size / 4)
            )
        );

        $innerX = $x + $padding;

        $innerY = $y + $padding;

        $innerSize = max(
            20,
            $size - ($padding * 2)
        );

        return <<<SVG

    <rect
        x="{$x}"
        y="{$y}"
        width="{$size}"
        height="{$size}"
        rx="4"
        fill="#FFFFFF"
    />

    <image
        href="data:image/svg+xml;base64,{$encodedQr}"
        x="{$innerX}"
        y="{$innerY}"
        width="{$innerSize}"
        height="{$innerSize}"
        preserveAspectRatio="xMidYMid meet"
    />
SVG;
    }

    /*
    |--------------------------------------------------------------------------
    | Resolve Layout
    |--------------------------------------------------------------------------
    */

    protected function resolveLayout(
        BadgeTemplate $template
    ): array {
        $layout = $template
            ->getDesignConfigWithDefaults();

        /*
        |--------------------------------------------------------------------------
        | Canvas
        |--------------------------------------------------------------------------
        */

        if (! data_get(
            $layout,
            'canvas.width'
        )) {
            data_set(
                $layout,
                'canvas.width',
                $template->width ?: 1638
            );
        }

        if (! data_get(
            $layout,
            'canvas.height'
        )) {
            data_set(
                $layout,
                'canvas.height',
                $template->height ?: 2048
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Background
        |--------------------------------------------------------------------------
        */

        if (! data_get(
            $layout,
            'canvas.background_image_path'
        )) {
            data_set(
                $layout,
                'canvas.background_image_path',
                $template->background_image_path
            );
        }

        /*
        |--------------------------------------------------------------------------
        | QR Fallback
        |--------------------------------------------------------------------------
        */

        $qrFromElements =
            $this->resolveQrFromFlexibleElements(
                data_get(
                    $layout,
                    'elements',
                    []
                )
            );

        if (! data_get(
            $layout,
            'qr_code.x'
        )) {
            data_set(
                $layout,
                'qr_code.x',
                data_get(
                    $qrFromElements,
                    'x',
                    819
                )
            );
        }

        if (! data_get(
            $layout,
            'qr_code.y'
        )) {
            data_set(
                $layout,
                'qr_code.y',
                data_get(
                    $qrFromElements,
                    'y',
                    1365
                )
            );
        }

        if (! data_get(
            $layout,
            'qr_code.size'
        )) {
            data_set(
                $layout,
                'qr_code.size',
                data_get(
                    $qrFromElements,
                    'size',
                    470
                )
            );
        }

        if (! data_get(
            $layout,
            'qr_code.padding'
        )) {
            data_set(
                $layout,
                'qr_code.padding',
                data_get(
                    $qrFromElements,
                    'padding',
                    20
                )
            );
        }

        return $layout;
    }

    /*
    |--------------------------------------------------------------------------
    | Flexible QR Compatibility
    |--------------------------------------------------------------------------
    */

    protected function resolveQrFromFlexibleElements(
        array $elements
    ): array {
        foreach (
            $elements as $element
        ) {
            if (
                data_get(
                    $element,
                    'type'
                ) !== 'qr_code'
            ) {
                continue;
            }

            return [
                'x' => (int) data_get(
                    $element,
                    'x',
                    819
                ),

                'y' => (int) data_get(
                    $element,
                    'y',
                    1365
                ),

                'size' => (int) data_get(
                    $element,
                    'size',
                    470
                ),

                'padding' => (int) data_get(
                    $element,
                    'padding',
                    20
                ),

                'visible' => (bool) data_get(
                    $element,
                    'visible',
                    true
                ),
            ];
        }

        return [
            'x' => 819,
            'y' => 1365,
            'size' => 470,
            'padding' => 20,
            'visible' => true,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Resolve Template
    |--------------------------------------------------------------------------
    */

    protected function resolveTemplate(
        Attendee $attendee
    ): ?BadgeTemplate {
        $baseQuery = fn () =>
            BadgeTemplate::query()
                ->where(
                    'is_active',
                    true
                );

        /*
        |--------------------------------------------------------------------------
        | 1. Badge Type Specific
        |--------------------------------------------------------------------------
        */

        if ($attendee->badge_type_id) {
            $template = $baseQuery()
                ->where(
                    'event_id',
                    $attendee->event_id
                )
                ->where(
                    'badge_type_id',
                    $attendee->badge_type_id
                )
                ->latest()
                ->first();

            if ($template) {
                return $template;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Category Specific
        |--------------------------------------------------------------------------
        */

        if ($attendee->category_id) {
            $template = $baseQuery()
                ->where(
                    'event_id',
                    $attendee->event_id
                )
                ->where(
                    'category_id',
                    $attendee->category_id
                )
                ->latest()
                ->first();

            if ($template) {
                return $template;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | 3. Event Default
        |--------------------------------------------------------------------------
        */

        $template = $baseQuery()
            ->where(
                'event_id',
                $attendee->event_id
            )
            ->where(
                'is_default',
                true
            )
            ->whereNull(
                'category_id'
            )
            ->whereNull(
                'badge_type_id'
            )
            ->latest()
            ->first();

        if ($template) {
            return $template;
        }

        /*
        |--------------------------------------------------------------------------
        | 4. Any Active Event Template
        |--------------------------------------------------------------------------
        */

        $template = $baseQuery()
            ->where(
                'event_id',
                $attendee->event_id
            )
            ->latest()
            ->first();

        if ($template) {
            return $template;
        }

        /*
        |--------------------------------------------------------------------------
        | 5. Global Default
        |--------------------------------------------------------------------------
        */

        $template = $baseQuery()
            ->whereNull(
                'event_id'
            )
            ->where(
                'is_default',
                true
            )
            ->latest()
            ->first();

        if ($template) {
            return $template;
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Update Badge State
    |--------------------------------------------------------------------------
    */

    protected function updateBadgeState(
        Attendee $attendee,
        array $data
    ): void {
        $allowed = [];

        foreach (
            $data as $column => $value
        ) {
            if (
                Schema::hasColumn(
                    'attendees',
                    $column
                )
            ) {
                $allowed[$column] =
                    $value;
            }
        }

        if ($allowed !== []) {
            $attendee
                ->forceFill(
                    $allowed
                )
                ->save();
        }
    }
}