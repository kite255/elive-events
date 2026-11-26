<?php

namespace App\Filament\Resources\TicketTypes\Pages;

use App\Filament\Resources\TicketTypes\TicketTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTicketType extends CreateRecord
{
    protected static string $resource = TicketTypeResource::class;
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        abort_unless(TicketTypeResource::authorizedEvents(auth()->user(), true)->whereKey($data['event_id'] ?? 0)->exists(), 403);

        return $data;
    }
}
