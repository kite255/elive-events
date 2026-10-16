<?php

namespace App\Filament\Resources\MerchandiseProducts\Pages;

use App\Filament\Resources\MerchandiseProducts\MerchandiseProductResource;
use Filament\Resources\Pages\EditRecord;

class EditMerchandiseProduct extends EditRecord
{
    protected static string $resource = MerchandiseProductResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        abort_unless(\App\Models\Event::query()->find($data['event_id'])?->canBeManagedBy(auth()->user()) === true, 403);

        if (filled($data['event_merchandise_id'] ?? null)) {
            abort_unless(
                \App\Models\EventMerchandise::query()
                    ->whereKey($data['event_merchandise_id'])
                    ->where('event_id', $data['event_id'])
                    ->exists(),
                422,
                'The linked merchandise item must belong to the selected event.'
            );
        }

        return $data;
    }
}
