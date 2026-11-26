<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use Filament\Resources\Pages\CreateRecord;
use App\Models\Event;

class CreateEvent extends CreateRecord
{
    protected static string $resource = EventResource::class;

    protected function getRedirectUrl(): string
    {
        return EventResource::getUrl('edit', ['record' => $this->getRecord()]);
    }

    protected function afterCreate(): void
    {
        app(\App\Services\EventAdmissionConfiguration::class)->persist(
            $this->getRecord(),
            (string) ($this->data['admission_mode'] ?? 'manual'),
        );
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $mode = (string) ($this->data['admission_mode'] ?? 'manual');
        [$registration] = \App\Services\EventAdmissionConfiguration::flags($mode);
        $data['registration_is_open'] = $registration;
        if (! $registration) {
            $data['registration_access_code_hash'] = null;
        }

        // Public event routes resolve by slug without an organization prefix.
        $data['slug'] = Event::generateUniqueSlug($data['name']);
        $data['status'] = Event::STATUS_DRAFT;
        // The Event model assigns the operational code after creation.
        unset($data['event_code']);

        return $data;
    }
}
