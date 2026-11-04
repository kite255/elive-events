<?php

namespace App\Filament\Resources\EventTicketTemplates\Pages;

use App\Filament\Resources\EventTicketTemplates\EventTicketTemplateResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class DesignEventTicketTemplate extends ViewRecord
{
    protected static string $resource =
        EventTicketTemplateResource::class;

    protected string $view =
        'filament.resources.event-ticket-templates.pages.designer';


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
