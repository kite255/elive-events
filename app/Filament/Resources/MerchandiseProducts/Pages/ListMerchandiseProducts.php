<?php

namespace App\Filament\Resources\MerchandiseProducts\Pages;

use App\Filament\Resources\MerchandiseProducts\MerchandiseProductResource;
use Filament\Resources\Pages\ListRecords;

class ListMerchandiseProducts extends ListRecords
{
    protected static string $resource = MerchandiseProductResource::class;
}
