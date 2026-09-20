<?php

namespace App\Filament\Resources\EventTicketTemplates\Pages;

use App\Filament\Resources\EventTicketTemplates\EventTicketTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEventTicketTemplates extends ListRecords
{
    protected static string $resource =
        EventTicketTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
