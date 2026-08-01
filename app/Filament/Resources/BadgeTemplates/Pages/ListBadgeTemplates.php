<?php

namespace App\Filament\Resources\BadgeTemplates\Pages;

use App\Filament\Resources\BadgeTemplates\BadgeTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBadgeTemplates extends ListRecords
{
    protected static string $resource = BadgeTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
