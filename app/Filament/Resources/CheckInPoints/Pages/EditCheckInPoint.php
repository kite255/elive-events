<?php

namespace App\Filament\Resources\CheckInPoints\Pages;

use App\Filament\Resources\CheckInPoints\CheckInPointResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCheckInPoint extends EditRecord
{
    protected static string $resource = CheckInPointResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
