<?php

namespace App\Filament\Resources\MerchandiseProducts\Pages;

use App\Filament\Resources\MerchandiseProducts\MerchandiseProductResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateMerchandiseProduct extends CreateRecord
{
    protected static string $resource = MerchandiseProductResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        abort_unless(\App\Models\Event::query()->find($data['event_id'])?->canBeManagedBy(auth()->user()) === true, 403);

        return $data;
    }
}
