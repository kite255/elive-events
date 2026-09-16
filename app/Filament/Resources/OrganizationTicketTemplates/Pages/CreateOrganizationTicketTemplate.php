<?php

namespace App\Filament\Resources\OrganizationTicketTemplates\Pages;

use App\Filament\Resources\OrganizationTicketTemplates\OrganizationTicketTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOrganizationTicketTemplate extends CreateRecord
{
    protected static string $resource =
        OrganizationTicketTemplateResource::class;
}
