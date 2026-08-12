<?php

namespace App\Services;

use App\Models\Attendee;
use App\Models\BadgeTemplate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Throwable;

class BadgeGenerationService
{
    /*
    |--------------------------------------------------------------------------
    | Permanent Badge Layout
    |--------------------------------------------------------------------------
    |
    | Optimized for the DCC SDA Camp Meeting badge artwork.
    |
    | Category:
    |   Y      = 1085
    |   Size   = 105
    |   Weight = 600
    |
    | Name:
    |   Y      = 1245
    |   Size   = 58
    |
    | QR:
    |   Y      = 1375
    |   Size   = 360
    |
    */

    protected const CATEGORY_MIN_Y = 1085;
    protected const NAME_MIN_Y = 1245;
    protected const QR_MIN_Y = 1375;

    protected const CATEGORY_DEFAULT_X = 819;
    protected const NAME_DEFAULT_X = 819;
    protected const QR_DEFAULT_X = 819;

    protected const CATEGORY_DEFAULT_FONT_SIZE = 105;
    protected const CATEGORY_MIN_FONT_SIZE = 58;
    protected const CATEGORY_MAX_WIDTH = 1250;
    protected const CATEGORY_FONT_WEIGHT = '600';

    protected const NAME_DEFAULT_FONT_SIZE = 58;
    protected const NAME_MIN_FONT_SIZE = 38;
    protected const NAME_MAX_WIDTH = 1150;
    protected const NAME_FONT_WEIGHT = '400';

    protected const QR_DEFAULT_SIZE = 360;
    protected const QR_DEFAULT_PADDING = 16;

    protected const CATEGORY_NAME_GAP = 160;
    protected const NAME_QR_GAP = 110;

    /*
    |--------------------------------------------------------------------------
    | Generate Badge
    |--------------------------------------------------------------------------
    */

    public function generateForAttendee(
        Attendee $attendee
    ): string {
        $attendee->loadMissing([
            'event',
            'category',
            'badgeType',
            'qrToken',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Mark Generating
        |--------------------------------------------------------------------------
        */

        $this->updateBadgeState(
            $attendee,
            [
                'badge_status' =>
                    'generating',
            ]
        );

        try {
            /*
            |--------------------------------------------------------------------------
            | Resolve Badge Template
            |--------------------------------------------------------------------------
            */

            $template =
                $this->resolveTemplate(
                    $attendee
                );

            if (! $template) {
                throw new \RuntimeException(
                    'No active badge template was found for this attendee.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Resolve Layout
            |--------------------------------------------------------------------------
            */

            $layout =
                $this->resolveLayout(
                    $template
                );

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

            $backgroundColor =
                $template->background_color
                ?: '#FFFFFF';

            $backgroundImagePath =
                $template
                    ->backgroundImagePath();

            /*
            |--------------------------------------------------------------------------
            | Badge Output Path
            |--------------------------------------------------------------------------
            */

            $safeName = Str::slug(
                $attendee->full_name
                    ?: 'attendee'
            );

            $path = sprintf(
                'events/%s/badges/attendee-%s-%s.svg',
                $attendee->event_id,
                $attendee->id,
                $safeName
            );

            /*
            |--------------------------------------------------------------------------
            | Dynamic Text
            |--------------------------------------------------------------------------
            */

            $elementsSvg =
                $this->renderDesignedElements(
                    attendee: $attendee,
                    layout: $layout,
                    width: $width,
                );

            /*
            |--------------------------------------------------------------------------
            | QR Code
            |--------------------------------------------------------------------------
            */

            $qrConfig = data_get(
                $layout,
                'qr_code',
                []
            );

            $qrSvg = '';

            if (
                (bool) data_get(
                    $qrConfig,
                    'visible',
                    true
                )
            ) {
                $qrSvg =
                    $this->renderQrCode(
                        attendee: $attendee,

                        centerX: (int) data_get(
                            $qrConfig,
                            'x',
                            self::QR_DEFAULT_X
                        ),

                        y: (int) data_get(
                            $qrConfig,
                            'y',
                            self::QR_MIN_Y
                        ),

                        size:
                            self::QR_DEFAULT_SIZE,

                        padding:
                            self::QR_DEFAULT_PADDING,
                    );
            }

            /*
            |--------------------------------------------------------------------------
            | Background
            |--------------------------------------------------------------------------
            */

            $backgroundSvg =
                $this->renderBackground(
                    backgroundImagePath:
                        $backgroundImagePath,

                    backgroundColor:
                        $backgroundColor,

                    width:
                        $width,

                    height:
                        $height,
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

            /*
            |--------------------------------------------------------------------------
            | Save Badge
            |--------------------------------------------------------------------------
            */

            Storage::disk('public')->put(
                $path,
                $svg
            );

            /*
            |--------------------------------------------------------------------------
            | Mark Badge Generated
            |--------------------------------------------------------------------------
            */

            $this->updateBadgeState(
                $attendee,
                [
                    'badge_path' =>
                        $path,

                    'badge_status' =>
                        'generated',

                    'badge_generated_at' =>
                        now(),
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | Refresh Attendee
            |--------------------------------------------------------------------------
            |
            | Make sure the communication service receives the newly saved
            | badge_path and badge state.
            |
            */

            $attendee->refresh();

            $attendee->loadMissing([
                'event.organization',
                'category',
                'badgeType',
                'qrToken',
            ]);

            /*
            |--------------------------------------------------------------------------
            | Trigger Badge Ready Communication
            |--------------------------------------------------------------------------
            |
            | Registration WhatsApp confirmation requires the generated badge
            | as the image header.
            |
            | IMPORTANT:
            |
            | Communication failure must NEVER make badge generation fail.
            |
            | The badge has already been generated successfully at this point.
            | Therefore communication is dispatched in a separate protected
            | method.
            |
            */

            $this->triggerBadgeReadyCommunication(
                $attendee
            );

            /*
            |--------------------------------------------------------------------------
            | Return Badge Path
            |--------------------------------------------------------------------------
            */

            return $path;
        } catch (Throwable $exception) {
            /*
            |--------------------------------------------------------------------------
            | Badge Generation Failed
            |--------------------------------------------------------------------------
            */

            $this->updateBadgeState(
                $attendee,
                [
                    'badge_status' =>
                        'failed',
                ]
            );

            throw $exception;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Trigger Badge Ready Communication
    |--------------------------------------------------------------------------
    |
    | This connects:
    |
    | Badge generated
    |      ↓
    | AutomaticCommunicationService
    |      ↓
    | WhatsApp CommunicationLog
    |      ↓
    | SendAutomaticCommunicationJob
    |      ↓
    | WhatsAppService
    |      ↓
    | Meta Cloud API
    |
    | Communication errors are deliberately isolated from badge generation.
    |
    |--------------------------------------------------------------------------
    */

    protected function triggerBadgeReadyCommunication(
        Attendee $attendee
    ): void {
        try {
            app(
                AutomaticCommunicationService::class
            )->handleBadgeReady(
                $attendee
            );

            Log::info(
                'Badge-ready communication processed.',
                [
                    'event_id' =>
                        $attendee->event_id,

                    'attendee_id' =>
                        $attendee->id,

                    'badge_path' =>
                        $attendee->badge_path,
                ]
            );
        } catch (Throwable $exception) {
            /*
            |--------------------------------------------------------------------------
            | Do Not Fail Badge Generation
            |--------------------------------------------------------------------------
            */

            report(
                $exception
            );

            Log::error(
                'Badge was generated but badge-ready communication could not be queued.',
                [
                    'event_id' =>
                        $attendee->event_id,

                    'attendee_id' =>
                        $attendee->id,

                    'badge_path' =>
                        $attendee->badge_path,

                    'error' =>
                        $exception->getMessage(),
                ]
            );
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
            filled(
                $backgroundImagePath
            )
            && Storage::disk('public')
                ->exists(
                    $backgroundImagePath
                )
        ) {
            $imageContent =
                Storage::disk('public')
                    ->get(
                        $backgroundImagePath
                    );

            $mimeType =
                Storage::disk('public')
                    ->mimeType(
                        $backgroundImagePath
                    )
                ?: $this->guessImageMimeType(
                    $backgroundImagePath
                );

            $encodedImage =
                base64_encode(
                    $imageContent
                );

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

    /*
    |--------------------------------------------------------------------------
    | Guess Background Image MIME Type
    |--------------------------------------------------------------------------
    */

    protected function guessImageMimeType(
        string $path
    ): string {
        return match (
            strtolower(
                pathinfo(
                    $path,
                    PATHINFO_EXTENSION
                )
            )
        ) {
            'jpg',
            'jpeg' =>
                'image/jpeg',

            'webp' =>
                'image/webp',

            'gif' =>
                'image/gif',

            'svg' =>
                'image/svg+xml',

            default =>
                'image/png',
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
        | Participant Category
        |--------------------------------------------------------------------------
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
            $category =
                $attendee->category?->name
                ?? $attendee->badgeType?->name
                ?? 'Guest';

            $categoryConfig =
                data_get(
                    $layout,
                    'category',
                    []
                );

            /*
            |--------------------------------------------------------------------------
            | Permanent Category Design
            |--------------------------------------------------------------------------
            */

            $categoryConfig['x'] =
                self::CATEGORY_DEFAULT_X;

            $categoryConfig['y'] =
                self::CATEGORY_MIN_Y;

            $categoryConfig['width'] =
                self::CATEGORY_MAX_WIDTH;

            $categoryConfig['font_size'] =
                self::CATEGORY_DEFAULT_FONT_SIZE;

            $categoryConfig['min_font_size'] =
                self::CATEGORY_MIN_FONT_SIZE;

            $categoryConfig['font_weight'] =
                self::CATEGORY_FONT_WEIGHT;

            $categoryConfig['align'] =
                'center';

            $categoryConfig['uppercase'] =
                true;

            $svg .=
                $this->renderTextElement(
                    value:
                        $category,

                    config:
                        $categoryConfig,

                    defaultX:
                        self::CATEGORY_DEFAULT_X,

                    defaultY:
                        self::CATEGORY_MIN_Y,

                    defaultFontSize:
                        self::CATEGORY_DEFAULT_FONT_SIZE,

                    defaultMinFontSize:
                        self::CATEGORY_MIN_FONT_SIZE,

                    defaultWidth:
                        self::CATEGORY_MAX_WIDTH,

                    defaultWeight:
                        self::CATEGORY_FONT_WEIGHT,

                    defaultColor:
                        '#FFFFFF',

                    defaultFontFamily:
                        'Bebas Neue',
                );
        }

        /*
        |--------------------------------------------------------------------------
        | Attendee Name
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
            $name =
                $attendee->full_name
                    ?: 'Guest';

            $nameConfig =
                data_get(
                    $layout,
                    'name',
                    []
                );

            /*
            |--------------------------------------------------------------------------
            | Permanent Attendee Name Design
            |--------------------------------------------------------------------------
            */

            $nameConfig['x'] =
                self::NAME_DEFAULT_X;

            $nameConfig['y'] =
                self::NAME_MIN_Y;

            $nameConfig['width'] =
                self::NAME_MAX_WIDTH;

            $nameConfig['font_size'] =
                self::NAME_DEFAULT_FONT_SIZE;

            $nameConfig['min_font_size'] =
                self::NAME_MIN_FONT_SIZE;

            $nameConfig['font_weight'] =
                self::NAME_FONT_WEIGHT;

            $nameConfig['align'] =
                'center';

            $nameConfig['uppercase'] =
                true;

            $svg .=
                $this->renderTextElement(
                    value:
                        $name,

                    config:
                        $nameConfig,

                    defaultX:
                        self::NAME_DEFAULT_X,

                    defaultY:
                        self::NAME_MIN_Y,

                    defaultFontSize:
                        self::NAME_DEFAULT_FONT_SIZE,

                    defaultMinFontSize:
                        self::NAME_MIN_FONT_SIZE,

                    defaultWidth:
                        self::NAME_MAX_WIDTH,

                    defaultWeight:
                        self::NAME_FONT_WEIGHT,

                    defaultColor:
                        '#FFFFFF',

                    defaultFontFamily:
                        'Bebas Neue',
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
        $uppercase =
            (bool) data_get(
                $config,
                'uppercase',
                true
            );

        $value =
            trim(
                $value
            );

        if ($uppercase) {
            $value =
                Str::upper(
                    $value
                );
        }

        if ($value === '') {
            return '';
        }

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

        $maxWidth = max(
            100,
            (int) data_get(
                $config,
                'width',
                $defaultWidth
            )
        );

        $fontSize = max(
            1,
            (int) data_get(
                $config,
                'font_size',
                $defaultFontSize
            )
        );

        $minFontSize = max(
            1,
            (int) data_get(
                $config,
                'min_font_size',
                $defaultMinFontSize
            )
        );

        if (
            $minFontSize
            > $fontSize
        ) {
            $minFontSize =
                $fontSize;
        }

        $fontFamily = trim(
            (string) data_get(
                $config,
                'font_family',
                $defaultFontFamily
            )
        );

        if (
            $fontFamily === ''
        ) {
            $fontFamily =
                $defaultFontFamily;
        }

        /*
        |--------------------------------------------------------------------------
        | Automatic Font Fitting
        |--------------------------------------------------------------------------
        */

        $fontSize =
            $this->fitFontSize(
                text:
                    $value,

                desiredFontSize:
                    $fontSize,

                minimumFontSize:
                    $minFontSize,

                maxWidth:
                    $maxWidth,

                fontFamily:
                    $fontFamily,
            );

        $fontWeight = e(
            (string) data_get(
                $config,
                'font_weight',
                $defaultWeight
            )
        );

        $color = e(
            (string) data_get(
                $config,
                'color',
                $defaultColor
            )
        );

        $align =
            data_get(
                $config,
                'align',
                'center'
            );

        $textAnchor = match (
            $align
        ) {
            'left' =>
                'start',

            'right' =>
                'end',

            default =>
                'middle',
        };

        $safeText =
            e(
                $value
            );

        $safeFontFamily =
            e(
                $fontFamily
            );

        $svgFontStack =
            "'{$safeFontFamily}', 'Arial Narrow', "
            . "'Liberation Sans Narrow', Arial, sans-serif";

        $shadowY =
            $y + 2;

        return <<<SVG

    <text
        x="{$x}"
        y="{$shadowY}"
        text-anchor="{$textAnchor}"
        font-family="{$svgFontStack}"
        font-size="{$fontSize}"
        font-weight="{$fontWeight}"
        fill="#000000"
        opacity="0.10"
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

        $widthFactor = match (
            strtolower(
                trim(
                    $fontFamily
                )
            )
        ) {
            'bebas neue' =>
                0.50,

            'arial narrow' =>
                0.52,

            'creato display' =>
                0.58,

            default =>
                0.60,
        };

        $characters =
            preg_split(
                '//u',
                $text,
                -1,
                PREG_SPLIT_NO_EMPTY
            ) ?: [];

        while (
            $fontSize
            > $minimumFontSize
        ) {
            $estimatedWidth =
                0.0;

            foreach (
                $characters as $character
            ) {
                $characterFactor =
                    match (true) {
                        in_array(
                            Str::upper(
                                $character
                            ),
                            [
                                'M',
                                'W',
                            ],
                            true
                        ) =>
                            $widthFactor * 1.25,

                        $character === ' ' =>
                            $widthFactor * 0.55,

                        default =>
                            $widthFactor,
                    };

                $estimatedWidth +=
                    $fontSize
                    * $characterFactor;
            }

            if (
                $estimatedWidth
                <= $maxWidth
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
        int $padding = 16
    ): string {
        $token = app(
            QrTokenService::class
        )->generateForAttendee(
            $attendee
        );

        $checkInUrl =
            url(
                '/check-in/'
                . $token
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

        $qrSvgContent =
            QrCode::format('svg')
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
        | Permanent QR Position
        |--------------------------------------------------------------------------
        */

        $centerX =
            self::QR_DEFAULT_X;

        $y =
            self::QR_MIN_Y;

        $size =
            self::QR_DEFAULT_SIZE;

        $padding =
            self::QR_DEFAULT_PADDING;

        $x = (int) round(
            $centerX
            - ($size / 2)
        );

        $encodedQr =
            base64_encode(
                $qrSvgContent
            );

        $padding = max(
            0,
            min(
                $padding,
                (int) (
                    $size / 4
                )
            )
        );

        $innerX =
            $x + $padding;

        $innerY =
            $y + $padding;

        $innerSize = max(
            20,
            $size
            - ($padding * 2)
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
        $layout =
            $template
                ->getDesignConfigWithDefaults();

        /*
        |--------------------------------------------------------------------------
        | Canvas
        |--------------------------------------------------------------------------
        */

        if (
            ! data_get(
                $layout,
                'canvas.width'
            )
        ) {
            data_set(
                $layout,
                'canvas.width',
                $template->width
                    ?: 1638
            );
        }

        if (
            ! data_get(
                $layout,
                'canvas.height'
            )
        ) {
            data_set(
                $layout,
                'canvas.height',
                $template->height
                    ?: 2048
            );
        }

        if (
            ! data_get(
                $layout,
                'canvas.background_image_path'
            )
        ) {
            data_set(
                $layout,
                'canvas.background_image_path',
                $template->background_image_path
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Permanent Category Configuration
        |--------------------------------------------------------------------------
        */

        data_set(
            $layout,
            'category.x',
            self::CATEGORY_DEFAULT_X
        );

        data_set(
            $layout,
            'category.y',
            self::CATEGORY_MIN_Y
        );

        data_set(
            $layout,
            'category.width',
            self::CATEGORY_MAX_WIDTH
        );

        data_set(
            $layout,
            'category.font_size',
            self::CATEGORY_DEFAULT_FONT_SIZE
        );

        data_set(
            $layout,
            'category.min_font_size',
            self::CATEGORY_MIN_FONT_SIZE
        );

        data_set(
            $layout,
            'category.font_weight',
            self::CATEGORY_FONT_WEIGHT
        );

        data_set(
            $layout,
            'category.align',
            'center'
        );

        data_set(
            $layout,
            'category.uppercase',
            true
        );

        /*
        |--------------------------------------------------------------------------
        | Permanent Name Configuration
        |--------------------------------------------------------------------------
        */

        data_set(
            $layout,
            'name.x',
            self::NAME_DEFAULT_X
        );

        data_set(
            $layout,
            'name.y',
            self::NAME_MIN_Y
        );

        data_set(
            $layout,
            'name.width',
            self::NAME_MAX_WIDTH
        );

        data_set(
            $layout,
            'name.font_size',
            self::NAME_DEFAULT_FONT_SIZE
        );

        data_set(
            $layout,
            'name.min_font_size',
            self::NAME_MIN_FONT_SIZE
        );

        data_set(
            $layout,
            'name.font_weight',
            self::NAME_FONT_WEIGHT
        );

        data_set(
            $layout,
            'name.align',
            'center'
        );

        data_set(
            $layout,
            'name.uppercase',
            true
        );

        /*
        |--------------------------------------------------------------------------
        | Permanent QR Configuration
        |--------------------------------------------------------------------------
        */

        data_set(
            $layout,
            'qr_code.x',
            self::QR_DEFAULT_X
        );

        data_set(
            $layout,
            'qr_code.y',
            self::QR_MIN_Y
        );

        data_set(
            $layout,
            'qr_code.size',
            self::QR_DEFAULT_SIZE
        );

        data_set(
            $layout,
            'qr_code.padding',
            self::QR_DEFAULT_PADDING
        );

        /*
        |--------------------------------------------------------------------------
        | Preserve Visibility
        |--------------------------------------------------------------------------
        */

        if (
            data_get(
                $layout,
                'qr_code.visible'
            ) === null
        ) {
            $qrFromElements =
                $this->resolveQrFromFlexibleElements(
                    data_get(
                        $layout,
                        'elements',
                        []
                    )
                );

            data_set(
                $layout,
                'qr_code.visible',
                (bool) data_get(
                    $qrFromElements,
                    'visible',
                    true
                )
            );
        }

        return $this
            ->normalizeFlexibleElements(
                $layout
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Normalize Flexible Elements
    |--------------------------------------------------------------------------
    */

    protected function normalizeFlexibleElements(
        array $layout
    ): array {
        $elements =
            data_get(
                $layout,
                'elements',
                []
            );

        if (
            ! is_array(
                $elements
            )
        ) {
            $elements = [];
        }

        $normalized = [];

        foreach (
            $elements as $element
        ) {
            $type =
                data_get(
                    $element,
                    'type'
                );

            /*
            |--------------------------------------------------------------------------
            | Category
            |--------------------------------------------------------------------------
            */

            if (
                $type === 'category'
            ) {
                $element['x'] =
                    self::CATEGORY_DEFAULT_X;

                $element['y'] =
                    self::CATEGORY_MIN_Y;

                $element['width'] =
                    self::CATEGORY_MAX_WIDTH;

                $element['font_size'] =
                    self::CATEGORY_DEFAULT_FONT_SIZE;

                $element['min_font_size'] =
                    self::CATEGORY_MIN_FONT_SIZE;

                $element['font_weight'] =
                    self::CATEGORY_FONT_WEIGHT;

                $element['align'] =
                    'center';

                $element['uppercase'] =
                    true;
            }

            /*
            |--------------------------------------------------------------------------
            | Name
            |--------------------------------------------------------------------------
            */

            if (
                in_array(
                    $type,
                    [
                        'name',
                        'attendee_name',
                    ],
                    true
                )
            ) {
                $element['x'] =
                    self::NAME_DEFAULT_X;

                $element['y'] =
                    self::NAME_MIN_Y;

                $element['width'] =
                    self::NAME_MAX_WIDTH;

                $element['font_size'] =
                    self::NAME_DEFAULT_FONT_SIZE;

                $element['min_font_size'] =
                    self::NAME_MIN_FONT_SIZE;

                $element['font_weight'] =
                    self::NAME_FONT_WEIGHT;

                $element['align'] =
                    'center';

                $element['uppercase'] =
                    true;
            }

            /*
            |--------------------------------------------------------------------------
            | QR
            |--------------------------------------------------------------------------
            */

            if (
                $type === 'qr_code'
            ) {
                $element['x'] =
                    self::QR_DEFAULT_X;

                $element['y'] =
                    self::QR_MIN_Y;

                $element['size'] =
                    self::QR_DEFAULT_SIZE;

                $element['padding'] =
                    self::QR_DEFAULT_PADDING;
            }

            $normalized[] =
                $element;
        }

        data_set(
            $layout,
            'elements',
            $normalized
        );

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
                'x' =>
                    self::QR_DEFAULT_X,

                'y' =>
                    self::QR_MIN_Y,

                'size' =>
                    self::QR_DEFAULT_SIZE,

                'padding' =>
                    self::QR_DEFAULT_PADDING,

                'visible' =>
                    (bool) data_get(
                        $element,
                        'visible',
                        true
                    ),
            ];
        }

        return [
            'x' =>
                self::QR_DEFAULT_X,

            'y' =>
                self::QR_MIN_Y,

            'size' =>
                self::QR_DEFAULT_SIZE,

            'padding' =>
                self::QR_DEFAULT_PADDING,

            'visible' =>
                true,
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
        $baseQuery =
            fn () =>
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

        if (
            $attendee->badge_type_id
        ) {
            $template =
                $baseQuery()
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

        if (
            $attendee->category_id
        ) {
            $template =
                $baseQuery()
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

        $template =
            $baseQuery()
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

        $template =
            $baseQuery()
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

        $template =
            $baseQuery()
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

        if (
            $allowed !== []
        ) {
            $attendee
                ->forceFill(
                    $allowed
                )
                ->save();
        }
    }
}