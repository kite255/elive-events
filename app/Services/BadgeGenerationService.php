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
            | Template
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
            | Layout
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
            | Text Elements
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
            | Save SVG Master
            |--------------------------------------------------------------------------
            */

            Storage::disk(
                'public'
            )->put(
                $svgPath,
                $svg
            );

            if (
                ! Storage::disk(
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
            | Generate PNG Delivery Version
            |--------------------------------------------------------------------------
            */

            $this->generatePngBadge(
                svg:
                    $svg,

                pngPath:
                    $pngPath,

                width:
                    $width,

                height:
                    $height,

                backgroundImagePath:
                    $backgroundImagePath,
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
            | Generate PDF
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

            /*
            |--------------------------------------------------------------------------
            | Log
            |--------------------------------------------------------------------------
            */

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
    | Generate PNG
    |--------------------------------------------------------------------------
    |
    | Master SVG remains completely self-contained.
    |
    | For Imagick rendering we create a temporary copy of the SVG and replace
    | embedded raster/SVG data URIs with temporary local files.
    |
    |--------------------------------------------------------------------------
    */

    protected function generatePngBadge(
        string $svg,
        string $pngPath,
        int $width,
        int $height,
        ?string $backgroundImagePath = null
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

        $image =
            null;

        $temporaryFiles =
            [];

        try {
            /*
            |--------------------------------------------------------------------------
            | Temporary Directory
            |--------------------------------------------------------------------------
            */

            $temporaryDirectory =
                storage_path(
                    'app/tmp/badges'
                );

            if (
                ! is_dir(
                    $temporaryDirectory
                )
            ) {
                if (
                    ! mkdir(
                        $temporaryDirectory,
                        0775,
                        true
                    )
                    && ! is_dir(
                        $temporaryDirectory
                    )
                ) {
                    throw new RuntimeException(
                        'Unable to create temporary badge rendering directory.'
                    );
                }
            }

            /*
            |--------------------------------------------------------------------------
            | Prepare SVG For Rasterization
            |--------------------------------------------------------------------------
            */

            $rasterSvg =
                $this->prepareSvgForRasterization(
                    svg:
                        $svg,

                    temporaryDirectory:
                        $temporaryDirectory,

                    temporaryFiles:
                        $temporaryFiles,

                    backgroundImagePath:
                        $backgroundImagePath,
                );

            /*
            |--------------------------------------------------------------------------
            | Write Temporary SVG
            |--------------------------------------------------------------------------
            */

            $temporarySvgPath =
                $temporaryDirectory
                . '/badge-'
                . Str::uuid()
                . '.svg';

            if (
                file_put_contents(
                    $temporarySvgPath,
                    $rasterSvg
                ) === false
            ) {
                throw new RuntimeException(
                    'Unable to create temporary SVG badge.'
                );
            }

            $temporaryFiles[] =
                $temporarySvgPath;

            /*
            |--------------------------------------------------------------------------
            | Create Imagick
            |--------------------------------------------------------------------------
            */

            $image =
                new Imagick();

            /*
            |--------------------------------------------------------------------------
            | Resolution
            |--------------------------------------------------------------------------
            */

            $image->setResolution(
                144,
                144
            );

            $image->setBackgroundColor(
                new ImagickPixel(
                    'white'
                )
            );

            /*
            |--------------------------------------------------------------------------
            | Read SVG File
            |--------------------------------------------------------------------------
            */

            $image->readImage(
                $temporarySvgPath
            );

            if (
                $image->getNumberImages()
                > 1
            ) {
                $image->setIteratorIndex(
                    0
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Flatten
            |--------------------------------------------------------------------------
            */

            $image->setImageBackgroundColor(
                new ImagickPixel(
                    'white'
                )
            );

            $flattened =
                $image->mergeImageLayers(
                    Imagick::LAYERMETHOD_FLATTEN
                );

            if (
                $flattened
                instanceof Imagick
            ) {
                if (
                    $flattened !== $image
                ) {
                    $image->clear();
                    $image->destroy();
                }

                $image =
                    $flattened;
            }

            /*
            |--------------------------------------------------------------------------
            | Exact Pixel Dimensions
            |--------------------------------------------------------------------------
            */

            if (
                $image->getImageWidth()
                    !== $width
                || $image->getImageHeight()
                    !== $height
            ) {
                $image->resizeImage(
                    $width,
                    $height,
                    Imagick::FILTER_LANCZOS,
                    1
                );
            }

            $image->setImagePage(
                0,
                0,
                0,
                0
            );

            /*
            |--------------------------------------------------------------------------
            | PNG
            |--------------------------------------------------------------------------
            */

            $image->setImageFormat(
                'png'
            );

            $image->stripImage();

            $image->setOption(
                'png:compression-level',
                '9'
            );

            $image->setOption(
                'png:compression-filter',
                '5'
            );

            $png =
                $image->getImageBlob();

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

            Storage::disk(
                'public'
            )->put(
                $pngPath,
                $png
            );

            if (
                ! Storage::disk(
                    'public'
                )->exists(
                    $pngPath
                )
            ) {
                throw new RuntimeException(
                    'The generated PNG badge could not be saved.'
                );
            }

            Log::info(
                'PNG delivery badge generated.',
                [
                    'png_path' =>
                        $pngPath,

                    'width' =>
                        $width,

                    'height' =>
                        $height,

                    'size_bytes' =>
                        Storage::disk(
                            'public'
                        )->size(
                            $pngPath
                        ),
                ]
            );

            return $pngPath;
        } catch (
            Throwable $exception
        ) {
            Log::error(
                'PNG badge generation failed.',
                [
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
            if (
                $image
                instanceof Imagick
            ) {
                $image->clear();
                $image->destroy();
            }

            foreach (
                $temporaryFiles
                as $temporaryFile
            ) {
                if (
                    is_string(
                        $temporaryFile
                    )
                    && file_exists(
                        $temporaryFile
                    )
                ) {
                    @unlink(
                        $temporaryFile
                    );
                }
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Prepare SVG For Imagick
    |--------------------------------------------------------------------------
    */

    protected function prepareSvgForRasterization(
        string $svg,
        string $temporaryDirectory,
        array &$temporaryFiles,
        ?string $backgroundImagePath = null
    ): string {
        $rasterSvg =
            $svg;

        /*
        |--------------------------------------------------------------------------
        | Replace Embedded Background
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

            $backgroundUri =
                $this->fileUri(
                    $absoluteBackgroundPath
                );

            /*
            |--------------------------------------------------------------------------
            | Replace href
            |--------------------------------------------------------------------------
            */

            $updatedSvg =
                preg_replace(
                    '/href="data:image\/(?:png|jpe?g|webp|gif);base64,[^"]+"/i',
                    'href="'
                    . htmlspecialchars(
                        $backgroundUri,
                        ENT_QUOTES
                            | ENT_XML1,
                        'UTF-8'
                    )
                    . '"',
                    $rasterSvg,
                    1
                );

            if (
                is_string(
                    $updatedSvg
                )
            ) {
                $rasterSvg =
                    $updatedSvg;
            }

            /*
            |--------------------------------------------------------------------------
            | Replace xlink:href
            |--------------------------------------------------------------------------
            */

            $updatedSvg =
                preg_replace(
                    '/xlink:href="data:image\/(?:png|jpe?g|webp|gif);base64,[^"]+"/i',
                    'xlink:href="'
                    . htmlspecialchars(
                        $backgroundUri,
                        ENT_QUOTES
                            | ENT_XML1,
                        'UTF-8'
                    )
                    . '"',
                    $rasterSvg,
                    1
                );

            if (
                is_string(
                    $updatedSvg
                )
            ) {
                $rasterSvg =
                    $updatedSvg;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Replace Embedded SVG Assets
        |--------------------------------------------------------------------------
        |
        | This mainly handles the embedded QR SVG.
        |
        */

        $rasterSvg =
            preg_replace_callback(
                '/(?P<attribute>href|xlink:href)="data:image\/svg\+xml;base64,(?P<data>[^"]+)"/i',
                function (
                    array $matches
                ) use (
                    $temporaryDirectory,
                    &$temporaryFiles
                ): string {
                    $decoded =
                        base64_decode(
                            $matches[
                                'data'
                            ],
                            true
                        );

                    if (
                        $decoded
                        === false
                    ) {
                        throw new RuntimeException(
                            'Unable to decode an embedded SVG badge asset.'
                        );
                    }

                    $temporaryAssetPath =
                        $temporaryDirectory
                        . '/asset-'
                        . Str::uuid()
                        . '.svg';

                    if (
                        file_put_contents(
                            $temporaryAssetPath,
                            $decoded
                        ) === false
                    ) {
                        throw new RuntimeException(
                            'Unable to create temporary SVG badge asset.'
                        );
                    }

                    $temporaryFiles[] =
                        $temporaryAssetPath;

                    $uri =
                        htmlspecialchars(
                            $this->fileUri(
                                $temporaryAssetPath
                            ),
                            ENT_QUOTES
                                | ENT_XML1,
                            'UTF-8'
                        );

                    return $matches[
                        'attribute'
                    ]
                        . '="'
                        . $uri
                        . '"';
                },
                $rasterSvg
            );

        if (
            ! is_string(
                $rasterSvg
            )
        ) {
            throw new RuntimeException(
                'Unable to prepare badge SVG for PNG rendering.'
            );
        }

        return $rasterSvg;
    }

    /*
    |--------------------------------------------------------------------------
    | Local File URI
    |--------------------------------------------------------------------------
    */

    protected function fileUri(
        string $path
    ): string {
        $path =
            str_replace(
                '\\',
                '/',
                $path
            );

        return 'file://'
            . $path;
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
            margin: 0;
            padding: 0;
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

            Storage::disk(
                'public'
            )->put(
                $pdfPath,
                $pdfOutput
            );

            if (
                ! Storage::disk(
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
    | Designed Elements
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

        /*
        |--------------------------------------------------------------------------
        | Category
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

            $categoryConfig[
                'x'
            ] =
                self::CATEGORY_DEFAULT_X;

            $categoryConfig[
                'y'
            ] =
                self::CATEGORY_MIN_Y;

            $categoryConfig[
                'width'
            ] =
                self::CATEGORY_MAX_WIDTH;

            $categoryConfig[
                'font_size'
            ] =
                self::CATEGORY_DEFAULT_FONT_SIZE;

            $categoryConfig[
                'min_font_size'
            ] =
                self::CATEGORY_MIN_FONT_SIZE;

            $categoryConfig[
                'font_weight'
            ] =
                self::CATEGORY_FONT_WEIGHT;

            $categoryConfig[
                'align'
            ] =
                'center';

            $categoryConfig[
                'uppercase'
            ] =
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

            $nameConfig[
                'x'
            ] =
                self::NAME_DEFAULT_X;

            $nameConfig[
                'y'
            ] =
                self::NAME_MIN_Y;

            $nameConfig[
                'width'
            ] =
                self::NAME_MAX_WIDTH;

            $nameConfig[
                'font_size'
            ] =
                self::NAME_DEFAULT_FONT_SIZE;

            $nameConfig[
                'min_font_size'
            ] =
                self::NAME_MIN_FONT_SIZE;

            $nameConfig[
                'font_weight'
            ] =
                self::NAME_FONT_WEIGHT;

            $nameConfig[
                'align'
            ] =
                'center';

            $nameConfig[
                'uppercase'
            ] =
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
    | Text
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

        if (
            $value === ''
        ) {
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

        if (
            $fontFamily === ''
        ) {
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
    | Font Fitting
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

        $centerX =
            self::QR_DEFAULT_X;

        $y =
            self::QR_MIN_Y;

        $size =
            self::QR_DEFAULT_SIZE;

        $padding =
            self::QR_DEFAULT_PADDING;

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
            $x + $padding;

        $innerY =
            $y + $padding;

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
    | Template
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