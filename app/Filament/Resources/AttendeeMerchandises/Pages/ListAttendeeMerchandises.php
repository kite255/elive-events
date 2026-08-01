<?php

namespace App\Filament\Resources\AttendeeMerchandises\Pages;

use App\Filament\Resources\AttendeeMerchandises\AttendeeMerchandiseResource;
use Filament\Resources\Pages\ListRecords;

class ListAttendeeMerchandises extends ListRecords
{
    protected static string $resource =
        AttendeeMerchandiseResource::class;

    protected static ?string $title = 'Merchandise Orders';

    protected function getHeaderActions(): array
    {
        return [];
    }
}
