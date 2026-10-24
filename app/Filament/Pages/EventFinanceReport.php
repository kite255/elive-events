<?php

namespace App\Filament\Pages;

use App\Models\Event;
use App\Models\User;
use App\Services\EventFinanceReconciliationService;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class EventFinanceReport extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';
    protected static string|UnitEnum|null $navigationGroup = 'Finance';
    protected static ?string $navigationLabel = 'Event Finance';
    protected static ?string $title = 'Event Finance';
    protected static ?int $navigationSort = 2;
    protected string $view = 'filament.pages.event-finance-report';

    public ?int $eventId = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && (
            $user->isSuperAdmin()
            || $user->managedOrganizations()->exists()
            || $user->assignedTicketingEvents()->exists()
        );
    }

    private function accessibleEvents(): Builder
    {
        $user = auth()->user();
        $query = Event::query();

        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isSuperAdmin()) {
            return $query;
        }

        return $query->where(function (Builder $scope) use ($user): void {
            $scope->whereIn('organization_id', $user->managedOrganizations()->select('organizations.id'))
                ->orWhereIn('events.id', $user->assignedTicketingEvents()->select('events.id'));
        });
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $id = request()->integer('event_id');
        $this->eventId = $id > 0 && $this->accessibleEvents()->whereKey($id)->exists()
            ? $id
            : $this->accessibleEvents()->orderBy('name')->value('events.id');
    }

    public function updatedEventId(): void
    {
        if ($this->eventId && ! $this->accessibleEvents()->whereKey($this->eventId)->exists()) {
            $this->eventId = null;
        }
    }

    public function eventOptions(): array
    {
        return $this->accessibleEvents()->orderBy('name')->pluck('name', 'events.id')->all();
    }

    public function financeReport(): ?array
    {
        if (! $this->eventId || ! $this->accessibleEvents()->whereKey($this->eventId)->exists()) {
            return null;
        }

        return app(EventFinanceReconciliationService::class)->inspect($this->eventId);
    }
}
