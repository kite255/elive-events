<?php

namespace App\Filament\Resources\AttendeeCategories\Pages;

use App\Filament\Resources\AttendeeCategories\AttendeeCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAttendeeCategories extends ListRecords
{
    protected static string $resource = AttendeeCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
