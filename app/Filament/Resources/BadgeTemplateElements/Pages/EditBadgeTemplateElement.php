<?php

namespace App\Filament\Resources\BadgeTemplateElements\Pages;

use App\Filament\Resources\BadgeTemplateElements\BadgeTemplateElementResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBadgeTemplateElement extends EditRecord
{
    protected static string $resource = BadgeTemplateElementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
