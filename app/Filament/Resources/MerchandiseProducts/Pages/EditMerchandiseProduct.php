<?php

namespace App\Filament\Resources\MerchandiseProducts\Pages;

use App\Filament\Resources\MerchandiseProducts\MerchandiseProductResource;
use Filament\Resources\Pages\EditRecord;

class EditMerchandiseProduct extends EditRecord
{
    protected static string $resource = MerchandiseProductResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        abort_unless(MerchandiseProductResource::accessibleEvents()->whereKey($data['event_id'])->exists(), 403);

        return $data;
    }
}
