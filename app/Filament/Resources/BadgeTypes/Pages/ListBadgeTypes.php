<?php

namespace App\Filament\Resources\BadgeTypes\Pages;

use App\Filament\Resources\BadgeTypes\BadgeTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBadgeTypes extends ListRecords
{
    protected static string $resource = BadgeTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
