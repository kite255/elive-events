<?php

namespace App\Filament\Resources\TicketOrders\Pages;

use App\Filament\Resources\TicketOrders\TicketOrderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTicketOrders extends ListRecords
{
    protected static string $resource = TicketOrderResource::class;

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
        if ($this->workspaceEventId === null) {
            return;
        }

        $user = auth()->user();
        abort_unless($user instanceof \App\Models\User && ! $user->is_gate_staff, 403);
        $event = \App\Filament\Resources\Events\Pages\EventWorkspace::authorizedEvents($user)
            ->whereKey($this->workspaceEventId)->firstOrFail();
        abort_unless($event->canBeManagedBy($user) || ($user->isTicketOrganizer() && $user->assignedTicketingEvents()->whereKey($event->id)->exists()), 403);
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
                        TicketOrderResource::canCreate()
                ),
        ];
    }
}