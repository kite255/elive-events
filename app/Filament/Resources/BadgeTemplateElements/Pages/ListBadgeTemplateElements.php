<?php

namespace App\Filament\Resources\BadgeTemplateElements\Pages;

use App\Filament\Resources\BadgeTemplateElements\BadgeTemplateElementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBadgeTemplateElements extends ListRecords
{
    protected static string $resource = BadgeTemplateElementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
