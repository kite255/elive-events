<?php

namespace App\Filament\Resources\Organizers\Pages;

use App\Filament\Resources\Organizers\OrganizerResource;
use App\Services\Organizers\OrganizerManagementService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateOrganizer extends CreateRecord
{
    protected static string $resource =
        OrganizerResource::class;

    protected function handleRecordCreation(
        array $data
    ): Model {
        return app(
            OrganizerManagementService::class
        )->createOrAssign(
            $data
        );
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl(
            'index'
        );
    }
}
