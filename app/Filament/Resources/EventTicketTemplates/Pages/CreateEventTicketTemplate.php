<?php

namespace App\Filament\Resources\EventTicketTemplates\Pages;

use App\Filament\Resources\EventTicketTemplates\EventTicketTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEventTicketTemplate extends CreateRecord
{
    protected static string $resource =
        EventTicketTemplateResource::class;
}
