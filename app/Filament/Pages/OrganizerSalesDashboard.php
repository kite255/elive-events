<?php

namespace App\Filament\Pages;

use App\Models\Event;
use App\Models\User;
use App\Services\Tickets\OrganizerSalesMetricsService;
use App\Services\Tickets\TicketUpgradeMetricsService;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class OrganizerSalesDashboard extends Page
{
    protected static ?string $navigationLabel = 'Sales Dashboard';

    protected static string|UnitEnum|null $navigationGroup = 'Ticketing';

    protected static ?string $title = 'Organizer Sales Dashboard';

    protected static ?string $slug = 'organizer-sales-dashboard';

    protected static ?int $navigationSort = 30;

    protected string $view = 'filament.pages.organizer-sales-dashboard';

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
            $assignedEventIds = $user->assignedTicketingEventIds();

            if ($assignedEventIds->isEmpty()) {
                return [];
            }

            return Event::query()
                ->whereIn('id', $assignedEventIds)
                ->orderByDesc('starts_at')
                ->pluck('name', 'id')
                ->mapWithKeys(fn ($name, $id) => [(int) $id => $name])
                ->toArray();
        }

        return Event::query()
            ->accessibleBy($user)
            ->orderByDesc('starts_at')
            ->get()
            ->filter(fn (Event $event): bool => $user->canViewEventReports($event))
            ->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id) => [(int) $id => $name])
            ->toArray();
    }

    public function salesMetrics(): array
    {
        if (! $this->selectedEventId) {
            return $this->emptyMetrics();
        }

        $event = Event::query()->find($this->selectedEventId);

        if (! $event) {
            return $this->emptyMetrics();
        }

        $user = Auth::user();

        if ($user instanceof User && ! $this->canUserViewEvent($user, $event)) {
            return $this->emptyMetrics();
        }

        return array_merge(
            app(OrganizerSalesMetricsService::class)->forEvent($event),
            app(TicketUpgradeMetricsService::class)->forEvent($event)
        );
    }

    protected function canUserViewEvent(User $user, Event $event): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isTicketOrganizer()) {
            return $user
                ->assignedTicketingEvents()
                ->where('events.id', $event->id)
                ->exists();
        }

        return $user->canViewEventReports($event);
    }

    protected function emptyMetrics(): array
    {
        return [
            'gross_sales' => 0,
            'paid_orders' => 0,
            'pending_orders' => 0,
            'expired_orders' => 0,
            'tickets_sold' => 0,
            'completed_upgrades' => 0,
            'pending_upgrades' => 0,
            'upgrade_revenue' => 0,
            'upgrade_net_payable' => 0,
            'upgrade_paths' => [],
            'currency' => 'TZS',
            'sales_by_ticket_type' => [],
            'recent_orders' => [],
        ];
    }
}
