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


    protected function authorizeAccess(): void
    {
        // Designer URLs must use the same organization and event
        // restrictions as the resource listing queries.
        abort_unless(
            static::getResource()::getEloquentQuery()
                ->whereKey($this->getRecord()->getKey())
                ->exists(),
            404
        );

        parent::authorizeAccess();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Design Ticket: ' . $this->getRecord()->name;
    }
}
