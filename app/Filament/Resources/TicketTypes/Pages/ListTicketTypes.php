<?php

namespace App\Filament\Resources\TicketTypes\Pages;

use App\Filament\Resources\TicketTypes\TicketTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTicketTypes extends ListRecords
{
    protected static string $resource = TicketTypeResource::class;

    #[\Livewire\Attributes\Locked]
    public ?int $workspaceEventId = null;

    public function mount(): void
    {
        parent::mount();

        if (! request()->has('event_id')) {
            return;
        }

        $id = request()->query('event_id');
        abort_unless(is_scalar($id) && ctype_digit((string) $id), 404);
        $this->workspaceEventId = (int) $id;
        $this->authorizeWorkspace();
    }

    private function authorizeWorkspace(): void
    {
        if ($this->workspaceEventId !== null) {
            abort_unless(TicketTypeResource::authorizedEvents(auth()->user())->whereKey($this->workspaceEventId)->exists(), 403);
        }
    }

    public function table(\Filament\Tables\Table $table): \Filament\Tables\Table
    {
        return parent::table($table)->modifyQueryUsing(function (\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder {
            $this->authorizeWorkspace();

            return $this->workspaceEventId === null
                ? $query
                : $query->where($query->getModel()->qualifyColumn('event_id'), $this->workspaceEventId);
        });
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(
                    fn (): bool =>
                        $this->workspaceEventId === null && TicketTypeResource::canCreate()
                ),
        ];
    }
}