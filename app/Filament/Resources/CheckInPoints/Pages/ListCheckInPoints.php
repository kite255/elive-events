<?php

namespace App\Filament\Resources\CheckInPoints\Pages;

use App\Filament\Resources\CheckInPoints\CheckInPointResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCheckInPoints extends ListRecords
{
    protected static string $resource = CheckInPointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
