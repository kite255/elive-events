<?php

namespace App\Filament\Resources\MerchandiseOrders\Pages;

use App\Filament\Resources\MerchandiseOrders\MerchandiseOrderResource;
use Filament\Resources\Pages\ListRecords;

class ListMerchandiseOrders extends ListRecords
{
    protected static string $resource = MerchandiseOrderResource::class;
}
