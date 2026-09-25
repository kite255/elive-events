<?php

namespace App\Filament\Resources\TicketCheckIns\Pages;

use App\Filament\Resources\TicketCheckIns\TicketCheckInResource;
use Filament\Resources\Pages\ListRecords;

class ListTicketCheckIns extends ListRecords
{
    protected static string $resource = TicketCheckInResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
