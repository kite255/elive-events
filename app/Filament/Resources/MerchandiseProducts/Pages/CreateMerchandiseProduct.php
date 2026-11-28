<?php

namespace App\Filament\Resources\MerchandiseProducts\Pages;

use App\Filament\Resources\MerchandiseProducts\MerchandiseProductResource;
use App\Models\Event;
use Filament\Resources\Pages\CreateRecord;
use Livewire\Attributes\Locked;

class CreateMerchandiseProduct extends CreateRecord
{
    protected static string $resource = MerchandiseProductResource::class;

    #[Locked]
    public ?int $workspaceEventId = null;

    public function mount(): void
    {
        if (request()->has('event_id')) {
            $id = request()->query('event_id');
            abort_unless(is_scalar($id) && ctype_digit((string) $id), 404);
            $this->workspaceEventId = (int) $id;
            $this->authorizeEvent($this->workspaceEventId);
        }

        parent::mount();
    }

    private function authorizeEvent(int $id): Event
    {
        $event = MerchandiseProductResource::accessibleEvents()->whereKey($id)->firstOrFail();
        abort_unless(MerchandiseProductResource::canManageMerchandiseFor($event), 403);

        return $event;
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $event = $this->authorizeEvent($this->workspaceEventId ?? (int) ($data['event_id'] ?? 0));
        $data['event_id'] = $event->id;

        if (filled($data['event_merchandise_id'] ?? null)) {
            abort_unless(
                \App\Models\EventMerchandise::query()
                    ->whereKey($data['event_merchandise_id'])
                    ->where('event_id', $event->id)
                    ->exists(),
                422,
                'The linked merchandise item must belong to the selected event.'
            );
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->workspaceEventId === null
            ? parent::getRedirectUrl()
            : MerchandiseProductResource::getUrl('index', ['event_id' => $this->workspaceEventId]);
    }
}
