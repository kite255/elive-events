<?php

namespace App\Filament\Resources\OrganizationTicketTemplates\Pages;

use App\Filament\Resources\OrganizationTicketTemplates\OrganizationTicketTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOrganizationTicketTemplates extends ListRecords
{
    protected static string $resource =
        OrganizationTicketTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
