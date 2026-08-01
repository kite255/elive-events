<?php

namespace App\Filament\Resources\Attendees\Pages;

use App\Filament\Resources\Attendees\AttendeeResource;
use App\Models\Attendee;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;

class ViewAttendeeQrCode extends Page
{
    use InteractsWithRecord;

    protected static string $resource = AttendeeResource::class;

    protected string $view = 'filament.resources.attendees.pages.view-attendee-qr-code';

    public string $qrCodeUrl = '';

    public string $checkInUrl = '';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        /** @var Attendee $attendee */
        $attendee = $this->record->load(['event', 'category', 'badgeType']);

        $this->qrCodeUrl = asset('storage/qr-codes/attendee-' . $attendee->id . '.svg');

        $this->checkInUrl = 'Stored securely inside the QR code.';
    }

    public function getTitle(): string
    {
        return 'View Attendee QR Code';
    }
}
