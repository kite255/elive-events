<?php

namespace App\Filament\Resources\TicketOrders\Pages;

use App\Filament\Resources\TicketOrders\TicketOrderResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTicketOrder extends CreateRecord
{
    protected static string $resource = TicketOrderResource::class;
}
