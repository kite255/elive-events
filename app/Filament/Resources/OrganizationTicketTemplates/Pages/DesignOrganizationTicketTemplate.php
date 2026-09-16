<?php

namespace App\Filament\Resources\OrganizationTicketTemplates\Pages;

use App\Filament\Resources\OrganizationTicketTemplates\OrganizationTicketTemplateResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class DesignOrganizationTicketTemplate extends ViewRecord
{
    protected static string $resource =
        OrganizationTicketTemplateResource::class;

    protected string $view =
        'filament.resources.organization-ticket-templates.pages.designer';

    public function getTitle(): string|Htmlable
    {
        return 'Design Ticket: ' . $this->getRecord()->name;
    }
}
