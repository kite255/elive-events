<?php

namespace App\Filament\Resources\BadgeTemplates\Pages;

use App\Filament\Resources\BadgeTemplates\BadgeTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBadgeTemplate extends EditRecord
{
    protected static string $resource = BadgeTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
