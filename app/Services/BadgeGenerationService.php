<?php

namespace App\Services;

use App\Models\Attendee;
use App\Models\BadgeTemplate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Imagick;
use ImagickDraw;
use ImagickPixel;
use RuntimeException;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Throwable;

class BadgeGenerationService
{
    /*
    |--------------------------------------------------------------------------
    | Permanent Badge Layout
    |--------------------------------------------------------------------------
    */

    // 1638 × 2048 reference badge layout.
    // Text X values are horizontal centres.
    // Text Y values are baselines because Imagick/SVG use baseline positioning.
    protected const NAME_DEFAULT_X = 819;
    protected const NAME_DEFAULT_Y = 920;
    protected const NAME_DEFAULT_FONT_SIZE = 108;
    protected const NAME_MIN_FONT_SIZE = 60;
    protected const NAME_MAX_WIDTH = 1238;
    protected const NAME_FONT_WEIGHT = '400';

    protected const CATEGORY_DEFAULT_X = 819;
    protected const CATEGORY_DEFAULT_Y = 1040;
    protected const CATEGORY_DEFAULT_FONT_SIZE = 74;
    protected const CATEGORY_MIN_FONT_SIZE = 52;
    protected const CATEGORY_MAX_WIDTH = 1100;
    protected const CATEGORY_FONT_WEIGHT = '400';

    protected const QR_DEFAULT_X = 819;
    protected const QR_DEFAULT_Y = 1160;
    protected const QR_DEFAULT_SIZE = 610;
    protected const QR_DEFAULT_PADDING = 20;

    /*
    |--------------------------------------------------------------------------
    | Physical Print Size
    |--------------------------------------------------------------------------
    */

    protected const DEFAULT_PRINT_WIDTH_MM = 80;
    protected const DEFAULT_PRINT_HEIGHT_MM = 100;

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
            | Resolve Template
            |--------------------------------------------------------------------------
            */

            $template =
                $this->resolveTemplate(
                    $attendee
                );

            if (! $template) {
                throw new RuntimeException(
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

            $width =
                (int) data_get(
                    $layout,
                    'canvas.width',
                    $template->width ?: 1638
                );

            $height =
                (int) data_get(
                    $layout,
                    'canvas.height',
                    $template->height ?: 2048
                );

            if (
                $width <= 0
                || $height <= 0
            ) {
                throw new RuntimeException(
                    'Badge canvas dimensions are invalid.'
                );
            }

            $backgroundColor =
                $template->background_color
                ?: '#FFFFFF';

            $backgroundImagePath =
                $template
                    ->backgroundImagePath();

            /*
            |--------------------------------------------------------------------------
            | File Names
            |--------------------------------------------------------------------------
            */

            $safeName =
                Str::slug(
                    $attendee->full_name
                        ?: 'attendee'
                );

            if ($safeName === '') {
                $safeName =
                    'attendee';
            }

            $baseFilename =
                sprintf(
                    'attendee-%s-%s',
                    $attendee->id,
                    $safeName
                );

            $svgPath =
                sprintf(
                    'events/%s/badges/svg/%s.svg',
                    $attendee->event_id,
                    $baseFilename
                );

            $pngPath =
                sprintf(
                    'events/%s/badges/png/%s.png',
                    $attendee->event_id,
                    $baseFilename
                );

            $pdfPath =
                sprintf(
                    'events/%s/badges/pdf/%s.pdf',
                    $attendee->event_id,
                    $baseFilename
                );

            /*
            |--------------------------------------------------------------------------
            | SVG Text Elements
            |--------------------------------------------------------------------------
            */

            $elementsSvg =
                $this->renderDesignedElements(
                    attendee:
                        $attendee,

                    layout:
                        $layout,

                    width:
                        $width,
                );

            /*
            |--------------------------------------------------------------------------
            | QR
            |--------------------------------------------------------------------------
            */

            $qrConfig =
                data_get(
                    $layout,
                    'qr_code',
                    []
                );

            $qrSvg =
                '';

            if (
                (bool) data_get(
                    $qrConfig,
                    'visible',
                    true
                )
            ) {
                $qrSvg =
                    $this->renderQrCode(
                        attendee:
                            $attendee,

                        centerX:
                            (int) data_get(
                                $qrConfig,
                                'x',
                                self::QR_DEFAULT_X
                            ),

                        y:
                            (int) data_get(
                                $qrConfig,
                                'y',
                                self::QR_DEFAULT_Y
                            ),

                        size:
                            (int) data_get(
                                $qrConfig,
                                'size',
                                self::QR_DEFAULT_SIZE
                            ),

                        padding:
                            (int) data_get(
                                $qrConfig,
                                'padding',
                                self::QR_DEFAULT_PADDING
                            ),
                    );
            }

            /*
            |--------------------------------------------------------------------------
            | SVG Background
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
            | Final Master SVG
            |--------------------------------------------------------------------------
            */

            $svg = <<<SVG
<svg
    width="{$width}"
    height="{$height}"
    viewBox="0 0 {$width} {$height}"
    xmlns="http://www.w3.org/2000/svg"
    xmlns:xlink="http://www.w3.org/1999/xlink"
>
{$backgroundSvg}

{$elementsSvg}

{$qrSvg}
</svg>
SVG;

            /*
            |--------------------------------------------------------------------------
            | Save Master SVG
            |--------------------------------------------------------------------------
            */

            $svgSaved =
                Storage::disk(
                    'public'
                )->put(
                    $svgPath,
                    $svg
                );

            if (
                ! $svgSaved
                || ! Storage::disk(
                    'public'
                )->exists(
                    $svgPath
                )
            ) {
                throw new RuntimeException(
                    'The SVG badge could not be saved.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Generate PNG Directly
            |--------------------------------------------------------------------------
            |
            | IMPORTANT:
            |
            | Do NOT rasterize the master SVG here.
            |
            | The digital PNG is composed directly using:
            |
            | background
            | category
            | attendee name
            | QR
            |
            */

            $this->generatePngBadge(
                attendee:
                    $attendee,

                layout:
                    $layout,

                pngPath:
                    $pngPath,

                width:
                    $width,

                height:
                    $height,

                backgroundImagePath:
                    $backgroundImagePath,

                backgroundColor:
                    $backgroundColor,
            );

            /*
            |--------------------------------------------------------------------------
            | Print Size
            |--------------------------------------------------------------------------
            */

            $printWidthMm =
                (float) data_get(
                    $layout,
                    'canvas.print_width_mm',
                    self::DEFAULT_PRINT_WIDTH_MM
                );

            $printHeightMm =
                (float) data_get(
                    $layout,
                    'canvas.print_height_mm',
                    self::DEFAULT_PRINT_HEIGHT_MM
                );

            /*
            |--------------------------------------------------------------------------
            | PDF
            |--------------------------------------------------------------------------
            */

            $this->generatePdfBadge(
                pngPath:
                    $pngPath,

                pdfPath:
                    $pdfPath,

                printWidthMm:
                    $printWidthMm,

                printHeightMm:
                    $printHeightMm,
            );

            /*
            |--------------------------------------------------------------------------
            | Mark Generated
            |--------------------------------------------------------------------------
            */

            $this->updateBadgeState(
                $attendee,
                [
                    'badge_path' =>
                        $svgPath,

                    'badge_status' =>
                        'generated',

                    'badge_generated_at' =>
                        now(),
                ]
            );

            Log::info(
                'Badge generated successfully.',
                [
                    'event_id' =>
                        $attendee->event_id,

                    'attendee_id' =>
                        $attendee->id,

                    'svg_path' =>
                        $svgPath,

                    'png_path' =>
                        $pngPath,

                    'pdf_path' =>
                        $pdfPath,

                    'canvas_width' =>
                        $width,

                    'canvas_height' =>
                        $height,

                    'print_width_mm' =>
                        $printWidthMm,

                    'print_height_mm' =>
                        $printHeightMm,
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | Refresh
            |--------------------------------------------------------------------------
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
            | Communication
            |--------------------------------------------------------------------------
            */

            $this->triggerBadgeReadyCommunication(
                $attendee
            );

            return $svgPath;
        } catch (
            Throwable $exception
        ) {
            $this->updateBadgeState(
                $attendee,
                [
                    'badge_status' =>
                        'failed',
                ]
            );

            Log::error(
                'Badge generation failed.',
                [
                    'event_id' =>
                        $attendee->event_id,

                    'attendee_id' =>
                        $attendee->id,

                    'error' =>
                        $exception->getMessage(),
                ]
            );

            throw $exception;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Generate PNG Directly
    |--------------------------------------------------------------------------
    */

    protected function generatePngBadge(
        Attendee $attendee,
        array $layout,
        string $pngPath,
        int $width,
        int $height,
        ?string $backgroundImagePath,
        string $backgroundColor
    ): string {
        if (
            ! class_exists(
                Imagick::class
            )
        ) {
            throw new RuntimeException(
                'Imagick PHP extension is required to generate PNG badges.'
            );
        }

        $canvas =
            null;

        $background =
            null;

        $qrImage =
            null;

        $qrBox =
            null;

        try {
            /*
            |--------------------------------------------------------------------------
            | Canvas
            |--------------------------------------------------------------------------
            */

            $canvas =
                new Imagick();

            $canvas->newImage(
                $width,
                $height,
                new ImagickPixel(
                    $backgroundColor
                ),
                'png'
            );

            $canvas->setImageColorspace(
                Imagick::COLORSPACE_SRGB
            );

            /*
            |--------------------------------------------------------------------------
            | Background Image
            |--------------------------------------------------------------------------
            */

            if (
                filled(
                    $backgroundImagePath
                )
                && Storage::disk(
                    'public'
                )->exists(
                    $backgroundImagePath
                )
            ) {
                $absoluteBackgroundPath =
                    Storage::disk(
                        'public'
                    )->path(
                        $backgroundImagePath
                    );

                $background =
                    new Imagick(
                        $absoluteBackgroundPath
                    );

                if (
                    $background
                        ->getNumberImages()
                    > 1
                ) {
                    $background
                        ->setIteratorIndex(
                            0
                        );
                }

                /*
                |--------------------------------------------------------------------------
                | Match Badge Canvas Exactly
                |--------------------------------------------------------------------------
                |
                | Existing badge design stretches the supplied artwork to the
                | badge canvas using preserveAspectRatio="none".
                |
                */

                $background->resizeImage(
                    $width,
                    $height,
                    Imagick::FILTER_LANCZOS,
                    1,
                    false
                );

                $background->setImagePage(
                    0,
                    0,
                    0,
                    0
                );

                $canvas->compositeImage(
                    $background,
                    Imagick::COMPOSITE_OVER,
                    0,
                    0
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Category
            |--------------------------------------------------------------------------
            */

            $enabledElements =
                data_get(
                    $layout,
                    'enabled_elements',
                    [
                        'category',
                        'name',
                        'qr_code',
                    ]
                );

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
                    $attendee
                        ->category
                        ?->name
                    ?? $attendee
                        ->badgeType
                        ?->name
                    ?? 'Guest';

                $categoryConfig =
                    data_get(
                        $layout,
                        'category',
                        []
                    );

                $this->drawTextOnImage(
                    image:
                        $canvas,

                    value:
                        $category,

                    config:
                        $categoryConfig,

                    defaultX:
                        self::CATEGORY_DEFAULT_X,

                    defaultY:
                        self::CATEGORY_DEFAULT_Y,

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
            | Name
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

                $this->drawTextOnImage(
                    image:
                        $canvas,

                    value:
                        $name,

                    config:
                        $nameConfig,

                    defaultX:
                        self::NAME_DEFAULT_X,

                    defaultY:
                        self::NAME_DEFAULT_Y,

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

            /*
            |--------------------------------------------------------------------------
            | QR
            |--------------------------------------------------------------------------
            */

            $qrConfig =
                data_get(
                    $layout,
                    'qr_code',
                    []
                );

            if (
                in_array(
                    'qr_code',
                    $enabledElements,
                    true
                )
                && (bool) data_get(
                    $qrConfig,
                    'visible',
                    true
                )
            ) {
                $qrPath =
                    sprintf(
                        'events/%s/qr-codes/attendee-%s.svg',
                        $attendee->event_id,
                        $attendee->id
                    );

                if (
                    ! Storage::disk(
                        'public'
                    )->exists(
                        $qrPath
                    )
                ) {
                    throw new RuntimeException(
                        'The attendee QR code could not be found for PNG generation.'
                    );
                }

                $qrSvg =
                    Storage::disk(
                        'public'
                    )->get(
                        $qrPath
                    );

                /*
                |--------------------------------------------------------------------------
                | Rasterize Only QR SVG
                |--------------------------------------------------------------------------
                |
                | The QR SVG contains only vector QR paths/shapes and does not
                | contain external/base64 badge artwork.
                |
                */

                $qrImage =
                    new Imagick();

                $qrImage->setResolution(
                    144,
                    144
                );

                $qrImage->setBackgroundColor(
                    new ImagickPixel(
                        'white'
                    )
                );

                $qrImage->readImageBlob(
                    $qrSvg
                );

                $qrImage->setImageBackgroundColor(
                    new ImagickPixel(
                        'white'
                    )
                );

                $flattenedQr =
                    $qrImage->mergeImageLayers(
                        Imagick::LAYERMETHOD_FLATTEN
                    );

                if (
                    $flattenedQr
                    instanceof Imagick
                ) {
                    if (
                        $flattenedQr
                        !== $qrImage
                    ) {
                        $qrImage->clear();
                        $qrImage->destroy();
                    }

                    $qrImage =
                        $flattenedQr;
                }

                $size =
                    max(
                        20,
                        (int) data_get(
                            $qrConfig,
                            'size',
                            self::QR_DEFAULT_SIZE
                        )
                    );

                $padding =
                    max(
                        0,
                        (int) data_get(
                            $qrConfig,
                            'padding',
                            self::QR_DEFAULT_PADDING
                        )
                    );

                $centerX =
                    (int) data_get(
                        $qrConfig,
                        'x',
                        self::QR_DEFAULT_X
                    );

                $y =
                    (int) data_get(
                        $qrConfig,
                        'y',
                        self::QR_DEFAULT_Y
                    );

                $x =
                    (int) round(
                        $centerX
                        - ($size / 2)
                    );

                $padding =
                    max(
                        0,
                        min(
                            $padding,
                            (int) (
                                $size / 4
                            )
                        )
                    );

                $innerSize =
                    max(
                        20,
                        $size
                        - ($padding * 2)
                    );

                $qrImage->resizeImage(
                    $innerSize,
                    $innerSize,
                    Imagick::FILTER_POINT,
                    1,
                    false
                );

                /*
                |--------------------------------------------------------------------------
                | White QR Box
                |--------------------------------------------------------------------------
                */

                $qrBox =
                    new Imagick();

                $qrBox->newImage(
                    $size,
                    $size,
                    new ImagickPixel(
                        '#FFFFFF'
                    ),
                    'png'
                );

                $qrBox->compositeImage(
                    $qrImage,
                    Imagick::COMPOSITE_OVER,
                    $padding,
                    $padding
                );

                /*
                |--------------------------------------------------------------------------
                | Add QR To Badge
                |--------------------------------------------------------------------------
                */

                $canvas->compositeImage(
                    $qrBox,
                    Imagick::COMPOSITE_OVER,
                    $x,
                    $y
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Final PNG
            |--------------------------------------------------------------------------
            */

            $canvas->setImageFormat(
                'png'
            );

            $canvas->setImageColorspace(
                Imagick::COLORSPACE_SRGB
            );

            $canvas->stripImage();

            $canvas->setOption(
                'png:compression-level',
                '9'
            );

            $png =
                $canvas->getImageBlob();

            if (
                ! is_string(
                    $png
                )
                || $png === ''
            ) {
                throw new RuntimeException(
                    'Imagick returned an empty PNG badge.'
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Save PNG
            |--------------------------------------------------------------------------
            */

            $saved =
                Storage::disk(
                    'public'
                )->put(
                    $pngPath,
                    $png
                );

            if (
                ! $saved
                || ! Storage::disk(
                    'public'
                )->exists(
                    $pngPath
                )
            ) {
                throw new RuntimeException(
                    'The generated PNG badge could not be saved.'
                );
            }

            $sizeBytes =
                Storage::disk(
                    'public'
                )->size(
                    $pngPath
                );

            /*
            |--------------------------------------------------------------------------
            | Detect Suspicious Output
            |--------------------------------------------------------------------------
            */

            if (
                $sizeBytes < 10000
            ) {
                Log::warning(
                    'Generated PNG badge is unusually small.',
                    [
                        'attendee_id' =>
                            $attendee->id,

                        'png_path' =>
                            $pngPath,

                        'size_bytes' =>
                            $sizeBytes,

                        'background_image_path' =>
                            $backgroundImagePath,
                    ]
                );
            }

            Log::info(
                'PNG delivery badge generated.',
                [
                    'attendee_id' =>
                        $attendee->id,

                    'png_path' =>
                        $pngPath,

                    'width' =>
                        $width,

                    'height' =>
                        $height,

                    'size_bytes' =>
                        $sizeBytes,

                    'background_image_path' =>
                        $backgroundImagePath,
                ]
            );

            return $pngPath;
        } catch (
            Throwable $exception
        ) {
            Log::error(
                'PNG badge generation failed.',
                [
                    'attendee_id' =>
                        $attendee->id,

                    'png_path' =>
                        $pngPath,

                    'background_image_path' =>
                        $backgroundImagePath,

                    'error' =>
                        $exception->getMessage(),
                ]
            );

            throw new RuntimeException(
                'Unable to generate PNG badge: '
                . $exception->getMessage(),
                previous:
                    $exception
            );
        } finally {
            foreach (
                [
                    $background,
                    $qrImage,
                    $qrBox,
                    $canvas,
                ] as $image
            ) {
                if (
                    $image
                    instanceof Imagick
                ) {
                    $image->clear();
                    $image->destroy();
                }
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Draw Text Directly On PNG
    |--------------------------------------------------------------------------
    */

    protected function drawTextOnImage(
        Imagick $image,
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
    ): void {
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
            return;
        }

        $x =
            (int) data_get(
                $config,
                'x',
                $defaultX
            );

        $y =
            (int) data_get(
                $config,
                'y',
                $defaultY
            );

        $maxWidth =
            max(
                100,
                (int) data_get(
                    $config,
                    'width',
                    $defaultWidth
                )
            );

        $desiredFontSize =
            max(
                1,
                (int) data_get(
                    $config,
                    'font_size',
                    $defaultFontSize
                )
            );

        $minimumFontSize =
            max(
                1,
                (int) data_get(
                    $config,
                    'min_font_size',
                    $defaultMinFontSize
                )
            );

        if (
            $minimumFontSize
            > $desiredFontSize
        ) {
            $minimumFontSize =
                $desiredFontSize;
        }

        $preferredFontFamily =
            trim(
                (string) data_get(
                    $config,
                    'font_family',
                    $defaultFontFamily
                )
            );

        if (
            $preferredFontFamily === ''
        ) {
            $preferredFontFamily =
                $defaultFontFamily;
        }

        $fontPath =
            $this->resolveImagickFontPath(
                $preferredFontFamily
            );

        $fontWeight =
            (int) data_get(
                $config,
                'font_weight',
                $defaultWeight
            );

        $fontWeight =
            max(
                100,
                min(
                    900,
                    $fontWeight
                )
            );

        $color =
            (string) data_get(
                $config,
                'color',
                $defaultColor
            );

        /*
        |--------------------------------------------------------------------------
        | Find Actual Font Size
        |--------------------------------------------------------------------------
        */

        $fontSize =
            $this->fitImagickFontSize(
                image:
                    $image,

                text:
                    $value,

                fontPath:
                    $fontPath,

                fontWeight:
                    $fontWeight,

                desiredFontSize:
                    $desiredFontSize,

                minimumFontSize:
                    $minimumFontSize,

                maxWidth:
                    $maxWidth,
            );

        /*
        |--------------------------------------------------------------------------
        | Shadow
        |--------------------------------------------------------------------------
        */

        $shadow =
            new ImagickDraw();

        $shadow->setFont(
            $fontPath
        );

        $shadow->setFontSize(
            $fontSize
        );

        $shadow->setFontWeight(
            $fontWeight
        );

        $shadow->setTextAlignment(
            Imagick::ALIGN_CENTER
        );

        $shadow->setFillColor(
            new ImagickPixel(
                'rgba(0,0,0,0.10)'
            )
        );

        $image->annotateImage(
            $shadow,
            $x,
            $y + 2,
            0,
            $value
        );

        /*
        |--------------------------------------------------------------------------
        | Main Text
        |--------------------------------------------------------------------------
        */

        $draw =
            new ImagickDraw();

        $draw->setFont(
            $fontPath
        );

        $draw->setFontSize(
            $fontSize
        );

        $draw->setFontWeight(
            $fontWeight
        );

        $draw->setTextAlignment(
            Imagick::ALIGN_CENTER
        );

        $draw->setFillColor(
            new ImagickPixel(
                $color
            )
        );

        $image->annotateImage(
            $draw,
            $x,
            $y,
            0,
            $value
        );

        $shadow->clear();
        $draw->clear();
    }

    /*
    |--------------------------------------------------------------------------
    | Resolve Installed Font
    |--------------------------------------------------------------------------
    */

    protected function resolveImagickFontPath(
        string $preferredFontFamily
    ): string {
        $fontMap = [
            'bebas neue' =>
                public_path(
                    'fonts/bebas-neue/BebasNeue-Regular.ttf'
                ),

            'bebasneue' =>
                public_path(
                    'fonts/bebas-neue/BebasNeue-Regular.ttf'
                ),
        ];

        $key =
            strtolower(
                trim(
                    $preferredFontFamily
                )
            );

        $fontPath =
            $fontMap[
                $key
            ]
            ?? null;

        if (
            filled(
                $fontPath
            )
            && is_file(
                $fontPath
            )
            && is_readable(
                $fontPath
            )
        ) {
            return $fontPath;
        }

        $fallbackCandidates = [
            '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
        ];

        foreach (
            $fallbackCandidates
            as $fallback
        ) {
            if (
                is_file(
                    $fallback
                )
                && is_readable(
                    $fallback
                )
            ) {
                Log::warning(
                    'Preferred badge font file was not found. Falling back to DejaVu Sans.',
                    [
                        'preferred_font' =>
                            $preferredFontFamily,

                        'fallback_font' =>
                            $fallback,
                    ]
                );

                return $fallback;
            }
        }

        throw new RuntimeException(
            'No usable badge font file was found.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Accurate PNG Font Fitting
    |--------------------------------------------------------------------------
    */

    protected function fitImagickFontSize(
        Imagick $image,
        string $text,
        string $fontPath,
        int $fontWeight,
        int $desiredFontSize,
        int $minimumFontSize,
        int $maxWidth
    ): int {
        $fontSize =
            $desiredFontSize;

        while (
            $fontSize
            >= $minimumFontSize
        ) {
            $draw =
                new ImagickDraw();

            $draw->setFont(
                $fontPath
            );

            $draw->setFontWeight(
                $fontWeight
            );

            $draw->setFontSize(
                $fontSize
            );

            $metrics =
                $image->queryFontMetrics(
                    $draw,
                    $text,
                    false
                );

            $draw->clear();

            $textWidth =
                (float) (
                    $metrics[
                        'textWidth'
                    ]
                    ?? PHP_FLOAT_MAX
                );

            if (
                $textWidth
                <= $maxWidth
            ) {
                return $fontSize;
            }

            $fontSize -=
                2;
        }

        return $minimumFontSize;
    }

    /*
    |--------------------------------------------------------------------------
    | Generate Print PDF
    |--------------------------------------------------------------------------
    */

    protected function generatePdfBadge(
        string $pngPath,
        string $pdfPath,
        float $printWidthMm,
        float $printHeightMm
    ): string {
        if (
            ! Storage::disk(
                'public'
            )->exists(
                $pngPath
            )
        ) {
            throw new RuntimeException(
                'PNG badge does not exist for PDF generation.'
            );
        }

        if (
            $printWidthMm <= 0
            || $printHeightMm <= 0
        ) {
            throw new RuntimeException(
                'Badge print dimensions are invalid.'
            );
        }

        $widthPoints =
            ($printWidthMm / 25.4)
            * 72;

        $heightPoints =
            ($printHeightMm / 25.4)
            * 72;

        try {
            $pngContent =
                Storage::disk(
                    'public'
                )->get(
                    $pngPath
                );

            $dataUri =
                'data:image/png;base64,'
                . base64_encode(
                    $pngContent
                );

            $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">

<style>
@page {
    margin: 0;
}

html,
body {
    margin: 0;
    padding: 0;
    width: {$widthPoints}pt;
    height: {$heightPoints}pt;
    overflow: hidden;
}

body {
    position: relative;
}

.badge {
    position: absolute;
    left: 0;
    top: 0;
    width: {$widthPoints}pt;
    height: {$heightPoints}pt;
}

.badge img {
    display: block;
    width: {$widthPoints}pt;
    height: {$heightPoints}pt;
    margin: 0;
    padding: 0;
    border: 0;
}
</style>
</head>

<body>
<div class="badge">
    <img src="{$dataUri}" alt="">
</div>
</body>
</html>
HTML;

            $pdf =
                Pdf::loadHTML(
                    $html
                );

            $pdf->setPaper(
                [
                    0,
                    0,
                    $widthPoints,
                    $heightPoints,
                ]
            );

            $pdfOutput =
                $pdf->output();

            if (
                ! is_string(
                    $pdfOutput
                )
                || $pdfOutput === ''
            ) {
                throw new RuntimeException(
                    'DomPDF returned an empty PDF badge.'
                );
            }

            $saved =
                Storage::disk(
                    'public'
                )->put(
                    $pdfPath,
                    $pdfOutput
                );

            if (
                ! $saved
                || ! Storage::disk(
                    'public'
                )->exists(
                    $pdfPath
                )
            ) {
                throw new RuntimeException(
                    'The generated PDF badge could not be saved.'
                );
            }

            Log::info(
                'Print-ready PDF badge generated.',
                [
                    'pdf_path' =>
                        $pdfPath,

                    'print_width_mm' =>
                        $printWidthMm,

                    'print_height_mm' =>
                        $printHeightMm,

                    'size_bytes' =>
                        Storage::disk(
                            'public'
                        )->size(
                            $pdfPath
                        ),
                ]
            );

            return $pdfPath;
        } catch (
            Throwable $exception
        ) {
            Log::error(
                'PDF badge generation failed.',
                [
                    'png_path' =>
                        $pngPath,

                    'pdf_path' =>
                        $pdfPath,

                    'error' =>
                        $exception->getMessage(),
                ]
            );

            throw new RuntimeException(
                'Unable to generate PDF badge: '
                . $exception->getMessage(),
                previous:
                    $exception
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Communication
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
        } catch (
            Throwable $exception
        ) {
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
    | SVG Background
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
            && Storage::disk(
                'public'
            )->exists(
                $backgroundImagePath
            )
        ) {
            $imageContent =
                Storage::disk(
                    'public'
                )->get(
                    $backgroundImagePath
                );

            $mimeType =
                Storage::disk(
                    'public'
                )->mimeType(
                    $backgroundImagePath
                )
                ?: $this
                    ->guessImageMimeType(
                        $backgroundImagePath
                    );

            $encodedImage =
                base64_encode(
                    $imageContent
                );

            $imageUri =
                "data:{$mimeType};base64,{$encodedImage}";

            return <<<SVG
    <image
        href="{$imageUri}"
        xlink:href="{$imageUri}"
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
    | MIME
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
    | SVG Designed Elements
    |--------------------------------------------------------------------------
    */

    protected function renderDesignedElements(
        Attendee $attendee,
        array $layout,
        int $width
    ): string {
        $enabledElements =
            data_get(
                $layout,
                'enabled_elements',
                [
                    'category',
                    'name',
                    'qr_code',
                ]
            );

        $svg =
            '';

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
                $attendee
                    ->category
                    ?->name
                ?? $attendee
                    ->badgeType
                    ?->name
                ?? 'Guest';

            $categoryConfig =
                data_get(
                    $layout,
                    'category',
                    []
                );

            $svg .=
                $this->renderTextElement(
                    value:
                        $category,

                    config:
                        $categoryConfig,

                    defaultX:
                        self::CATEGORY_DEFAULT_X,

                    defaultY:
                        self::CATEGORY_DEFAULT_Y,

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

            $svg .=
                $this->renderTextElement(
                    value:
                        $name,

                    config:
                        $nameConfig,

                    defaultX:
                        self::NAME_DEFAULT_X,

                    defaultY:
                        self::NAME_DEFAULT_Y,

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
    | SVG Text
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

        $x =
            (int) data_get(
                $config,
                'x',
                $defaultX
            );

        $y =
            (int) data_get(
                $config,
                'y',
                $defaultY
            );

        $maxWidth =
            max(
                100,
                (int) data_get(
                    $config,
                    'width',
                    $defaultWidth
                )
            );

        $fontSize =
            max(
                1,
                (int) data_get(
                    $config,
                    'font_size',
                    $defaultFontSize
                )
            );

        $minFontSize =
            max(
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

        $fontFamily =
            trim(
                (string) data_get(
                    $config,
                    'font_family',
                    $defaultFontFamily
                )
            );

        if ($fontFamily === '') {
            $fontFamily =
                $defaultFontFamily;
        }

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

        $fontWeight =
            e(
                (string) data_get(
                    $config,
                    'font_weight',
                    $defaultWeight
                )
            );

        $color =
            e(
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

        $textAnchor =
            match (
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
            "'{$safeFontFamily}', "
            . "'Arial Narrow', "
            . "'Liberation Sans Narrow', "
            . 'Arial, sans-serif';

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
    | SVG Font Fitting
    |--------------------------------------------------------------------------
    */

    protected function fitFontSize(
        string $text,
        int $desiredFontSize,
        int $minimumFontSize,
        int $maxWidth,
        string $fontFamily = 'Bebas Neue'
    ): int {
        $fontSize =
            max(
                $minimumFontSize,
                $desiredFontSize
            );

        $widthFactor =
            match (
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
            )
            ?: [];

        while (
            $fontSize
            > $minimumFontSize
        ) {
            $estimatedWidth =
                0.0;

            foreach (
                $characters
                as $character
            ) {
                $characterFactor =
                    match (
                        true
                    ) {
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
                            $widthFactor
                            * 1.25,

                        $character === ' ' =>
                            $widthFactor
                            * 0.55,

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

            $fontSize -=
                2;
        }

        return max(
            $minimumFontSize,
            $fontSize
        );
    }

    /*
    |--------------------------------------------------------------------------
    | QR
    |--------------------------------------------------------------------------
    */

    protected function renderQrCode(
        Attendee $attendee,
        int $centerX,
        int $y,
        int $size,
        int $padding = 16
    ): string {
        $token =
            app(
                QrTokenService::class
            )->generateForAttendee(
                $attendee
            );

        $checkInUrl =
            url(
                '/check-in/'
                . $token
            );

        $qrPath =
            sprintf(
                'events/%s/qr-codes/attendee-%s.svg',
                $attendee->event_id,
                $attendee->id
            );

        $qrSvgContent =
            QrCode::format(
                'svg'
            )
                ->size(
                    500
                )
                ->margin(
                    0
                )
                ->generate(
                    $checkInUrl
                );

        Storage::disk(
            'public'
        )->put(
            $qrPath,
            $qrSvgContent
        );

        $size =
            max(
                20,
                $size
            );

        $padding =
            max(
                0,
                $padding
            );

        $x =
            (int) round(
                $centerX
                - ($size / 2)
            );

        $encodedQr =
            base64_encode(
                $qrSvgContent
            );

        $padding =
            max(
                0,
                min(
                    $padding,
                    (int) (
                        $size / 4
                    )
                )
            );

        $innerX =
            $x
            + $padding;

        $innerY =
            $y
            + $padding;

        $innerSize =
            max(
                20,
                $size
                - ($padding * 2)
            );

        $qrUri =
            'data:image/svg+xml;base64,'
            . $encodedQr;

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
        href="{$qrUri}"
        xlink:href="{$qrUri}"
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
    | Layout
    |--------------------------------------------------------------------------
    */

    protected function resolveLayout(
        BadgeTemplate $template
    ): array {
        $layout =
            $template
                ->getDesignConfigWithDefaults();

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
                $template
                    ->background_image_path
            );
        }

        if (
            ! data_get(
                $layout,
                'canvas.print_width_mm'
            )
        ) {
            data_set(
                $layout,
                'canvas.print_width_mm',
                self::DEFAULT_PRINT_WIDTH_MM
            );
        }

        if (
            ! data_get(
                $layout,
                'canvas.print_height_mm'
            )
        ) {
            data_set(
                $layout,
                'canvas.print_height_mm',
                self::DEFAULT_PRINT_HEIGHT_MM
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Category
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
            self::CATEGORY_DEFAULT_Y
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
        | Name
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
            self::NAME_DEFAULT_Y
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
        | QR
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
            self::QR_DEFAULT_Y
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

        if (
            data_get(
                $layout,
                'qr_code.visible'
            ) === null
        ) {
            $qrFromElements =
                $this
                    ->resolveQrFromFlexibleElements(
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
            $elements =
                [];
        }

        $normalized =
            [];

        foreach (
            $elements
            as $element
        ) {
            $type =
                data_get(
                    $element,
                    'type'
                );

            if (
                $type === 'category'
            ) {
                $element['x'] =
                    self::CATEGORY_DEFAULT_X;

                $element['y'] =
                    self::CATEGORY_DEFAULT_Y;

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
                    self::NAME_DEFAULT_Y;

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

            if (
                $type === 'qr_code'
            ) {
                $element['x'] =
                    self::QR_DEFAULT_X;

                $element['y'] =
                    self::QR_DEFAULT_Y;

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
    | Flexible QR
    |--------------------------------------------------------------------------
    */

    protected function resolveQrFromFlexibleElements(
        array $elements
    ): array {
        foreach (
            $elements
            as $element
        ) {
            if (
                data_get(
                    $element,
                    'type'
                )
                !== 'qr_code'
            ) {
                continue;
            }

            return [
                'x' =>
                    self::QR_DEFAULT_X,

                'y' =>
                    self::QR_DEFAULT_Y,

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
                self::QR_DEFAULT_Y,

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
    | Badge State
    |--------------------------------------------------------------------------
    */

    protected function updateBadgeState(
        Attendee $attendee,
        array $data
    ): void {
        $allowed =
            [];

        foreach (
            $data
            as $column => $value
        ) {
            if (
                Schema::hasColumn(
                    'attendees',
                    $column
                )
            ) {
                $allowed[
                    $column
                ] =
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