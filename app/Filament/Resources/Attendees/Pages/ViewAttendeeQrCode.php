<?php

namespace App\Filament\Resources\Attendees\Pages;

use App\Filament\Resources\Attendees\AttendeeResource;
use App\Models\Attendee;
use App\Services\QrTokenService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class ViewAttendeeQrCode extends Page
{
    use InteractsWithRecord;

    protected static string $resource = AttendeeResource::class;

    protected string $view =
        'filament.resources.attendees.pages.view-attendee-qr-code';

    public string $qrCodeUrl = '';

    public string $checkInUrl = '';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        /** @var Attendee $attendee */
        $attendee = $this->record->load([
            'event',
            'category',
            'badgeType',
        ]);

        $qrPath = sprintf(
            'events/%s/qr-codes/attendee-%s.svg',
            $attendee->event_id,
            $attendee->id
        );

        $this->qrCodeUrl =
            Storage::disk('public')->exists($qrPath)
                ? Storage::disk('public')->url($qrPath)
                : '';

        $this->checkInUrl =
            'Stored securely inside the QR code.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadQrCode')
                ->label('Download QR Code')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('primary')
                ->form([
                    Select::make('format')
                        ->label('Choose Download Format')
                        ->options([
                            'png' => 'PNG - Recommended',
                            'svg' => 'SVG - High Quality / Printing',
                        ])
                        ->default('png')
                        ->required()
                        ->native(false),
                ])
                ->modalHeading('Download QR Code')
                ->modalSubmitActionLabel('Download')
                ->modalCancelActionLabel('Cancel')
                ->action(function (array $data) {
                    /** @var Attendee $attendee */
                    $attendee = $this->record;

                    $format = strtolower(
                        $data['format'] ?? 'png'
                    );

                    abort_unless(
                        in_array($format, ['png', 'svg'], true),
                        422,
                        'Invalid QR download format.'
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | Permanent QR token
                    |--------------------------------------------------------------------------
                    */

                    $token = app(
                        QrTokenService::class
                    )->getTokenForAttendee(
                        $attendee
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | Same check-in URL used by the QR system
                    |--------------------------------------------------------------------------
                    */

                    $checkInUrl = route(
                        'qr.check-in',
                        [
                            'token' => $token,
                        ]
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | Filename
                    |--------------------------------------------------------------------------
                    */

                    $baseFilename =
                        $attendee->badge_number
                            ?: 'attendee-' . $attendee->id;

                    $baseFilename = preg_replace(
                        '/[^A-Za-z0-9\-_]/',
                        '-',
                        $baseFilename
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | SVG
                    |--------------------------------------------------------------------------
                    */

                    if ($format === 'svg') {
                        $svg = QrCode::format('svg')
                            ->size(1000)
                            ->margin(1)
                            ->errorCorrection('H')
                            ->generate($checkInUrl);

                        $filename =
                            $baseFilename .
                            '-qr-code.svg';

                        return response()->streamDownload(
                            function () use ($svg) {
                                echo $svg;
                            },
                            $filename,
                            [
                                'Content-Type' => 'image/svg+xml',
                            ]
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | PNG
                    |--------------------------------------------------------------------------
                    */

                    $png = QrCode::format('png')
                        ->size(1000)
                        ->margin(1)
                        ->errorCorrection('H')
                        ->generate($checkInUrl);

                    $filename =
                        $baseFilename .
                        '-qr-code.png';

                    return response()->streamDownload(
                        function () use ($png) {
                            echo $png;
                        },
                        $filename,
                        [
                            'Content-Type' => 'image/png',
                        ]
                    );
                }),
        ];
    }

    public function getTitle(): string
    {
        return 'View Attendee QR Code';
    }
}