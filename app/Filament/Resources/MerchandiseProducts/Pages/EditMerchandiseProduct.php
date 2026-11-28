<?php

namespace App\Filament\Resources\MerchandiseProducts\Pages;

use App\Filament\Resources\MerchandiseProducts\MerchandiseProductResource;
use Filament\Resources\Pages\EditRecord;

class EditMerchandiseProduct extends EditRecord
{
    protected static string $resource = MerchandiseProductResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $event = MerchandiseProductResource::accessibleEvents()->whereKey($this->record->event_id)->firstOrFail();
        abort_unless(MerchandiseProductResource::canManageMerchandiseFor($event), 403);
        $data['event_id'] = $event->id;

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
