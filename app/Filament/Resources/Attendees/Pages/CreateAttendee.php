<?php

namespace App\Filament\Resources\Attendees\Pages;

use App\Filament\Resources\Attendees\AttendeeResource;
use App\Filament\Resources\Events\EventResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAttendee extends CreateRecord
{
    protected static string $resource = AttendeeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $eventId = request()->integer('event_id');

        if ($eventId) {
            $data['event_id'] = $eventId;
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        $eventId = $this->record?->event_id ?? request()->integer('event_id');

        if ($eventId) {
            return EventResource::getUrl('edit', [
                'record' => $eventId,
            ]);
        }

        return AttendeeResource::getUrl('index');
    }
}