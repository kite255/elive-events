<?php

namespace App\Filament\Pages;

use App\Models\Event;
use App\Models\User;
use App\Services\Tickets\TicketCapacityMetricsService;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class TicketCapacityDashboard extends Page
{
    protected static ?string $navigationLabel = 'Capacity Dashboard';

    protected static string|UnitEnum|null $navigationGroup = 'Ticketing';

    protected static ?string $title = 'Ticket Capacity Dashboard';

    protected static ?string $slug = 'ticket-capacity-dashboard';

    protected static ?int $navigationSort = 35;

    protected string $view = 'filament.pages.ticket-capacity-dashboard';

    public ?int $selectedEventId = null;

    public static function canAccess(): bool
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return false;
        }

        return $user->isSuperAdmin()
            || $user->isTicketOrganizer()
            || $user->managedOrganizations()->exists()
            || $user->eventManagerEvents()->exists();
    }

    public function mount(): void
    {
        $events = $this->eventOptions();

        if ($this->selectedEventId === null && count($events) > 0) {
            $this->selectedEventId = (int) array_key_first($events);
        }
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function eventOptions(): array
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return [];
        }

        if ($user->isSuperAdmin()) {
            return Event::query()
                ->orderByDesc('starts_at')
                ->pluck('name', 'id')
                ->mapWithKeys(fn ($name, $id) => [(int) $id => $name])
                ->toArray();
        }

        if ($user->isTicketOrganizer()) {
            $ids = $user->assignedTicketingEventIds();

            if ($ids->isEmpty()) {
                return [];
            }

            return Event::query()
                ->whereIn('id', $ids)
                ->orderByDesc('starts_at')
                ->pluck('name', 'id')
                ->mapWithKeys(fn ($name, $id) => [(int) $id => $name])
                ->toArray();
        }

        return Event::query()
            ->accessibleBy($user)
            ->orderByDesc('starts_at')
            ->get()
            ->filter(fn (Event $event): bool => $event->canBeManagedBy($user))
            ->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id) => [(int) $id => $name])
            ->toArray();
    }

    public function capacityMetrics(): array
    {
        if (! $this->selectedEventId) {
            return $this->emptyMetrics();
        }

        $event = Event::query()->find($this->selectedEventId);
        $user = Auth::user();

        if (! $event || ! $user instanceof User || ! $this->canViewEvent($user, $event)) {
            return $this->emptyMetrics();
        }

        return app(TicketCapacityMetricsService::class)->forEvent($event);
    }

    private function canViewEvent(User $user, Event $event): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isTicketOrganizer()) {
            return $user->assignedTicketingEvents()
                ->where('events.id', $event->id)
                ->exists();
        }

        return $event->canBeManagedBy($user);
    }

    private function emptyMetrics(): array
    {
        return [
            'event' => null,
            'summary' => [
                'capacity' => 0,
                'sold' => 0,
                'reserved' => 0,
                'available' => 0,
                'checked_in' => 0,
            ],
            'ticket_types' => [],
        ];
    }
}
