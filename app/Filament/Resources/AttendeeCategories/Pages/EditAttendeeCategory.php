<?php

namespace App\Filament\Resources\AttendeeCategories\Pages;

use App\Filament\Resources\AttendeeCategories\AttendeeCategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAttendeeCategory extends EditRecord
{
    protected static string $resource = AttendeeCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
