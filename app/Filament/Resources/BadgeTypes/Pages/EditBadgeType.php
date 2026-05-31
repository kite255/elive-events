<?php

namespace App\Filament\Resources\BadgeTypes\Pages;

use App\Filament\Resources\BadgeTypes\BadgeTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBadgeType extends EditRecord
{
    protected static string $resource = BadgeTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
