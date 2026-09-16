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

    public function getTitle(): string|Htmlable
    {
        return 'Design Ticket: ' . $this->getRecord()->name;
    }
}
