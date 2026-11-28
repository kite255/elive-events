<?php

namespace App\Filament\Resources\TicketTypes\Pages;

use App\Filament\Resources\TicketTypes\TicketTypeResource;
use Filament\Resources\Pages\CreateRecord;
use Livewire\Attributes\Locked;

class CreateTicketType extends CreateRecord
{
    protected static string $resource = TicketTypeResource::class;

    #[Locked]
    public ?int $workspaceEventId = null;

    public function mount(): void
    {
        if (request()->has('event_id')) {
            $id = request()->query('event_id');
            abort_unless(is_scalar($id) && ctype_digit((string) $id), 404);
            $this->workspaceEventId = (int) $id;
            $this->authorizeWorkspace();
        }

        parent::mount();
    }

    private function authorizeWorkspace(): void
    {
        if ($this->workspaceEventId !== null) {
            abort_unless(TicketTypeResource::authorizedEvents(auth()->user(), true)
                ->whereKey($this->workspaceEventId)->exists(), 403);
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->authorizeWorkspace();

        if ($this->workspaceEventId !== null) {
            $data['event_id'] = $this->workspaceEventId;
        }

        abort_unless(TicketTypeResource::authorizedEvents(auth()->user(), true)->whereKey($data['event_id'] ?? 0)->exists(), 403);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->workspaceEventId === null
            ? parent::getRedirectUrl()
            : TicketTypeResource::getUrl('index', ['event_id' => $this->workspaceEventId]);
    }
}
