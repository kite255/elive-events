<?php

namespace App\Filament\Resources\TicketOrders\Pages;

use App\Filament\Resources\TicketOrders\TicketOrderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTicketOrders extends ListRecords
{
    protected static string $resource = TicketOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
