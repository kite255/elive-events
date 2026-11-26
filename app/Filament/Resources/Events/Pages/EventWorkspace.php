<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use App\Models\Attendee;
use App\Models\CheckIn;
use App\Models\Event;
use App\Models\TicketOrder;
use App\Models\User;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Locked;

class EventWorkspace extends Page
{
    protected static string $resource = EventResource::class;

    protected string $view = 'filament.resources.events.pages.event-workspace';

    #[Locked]
    public int $eventId;

    public function mount(int|string $record): void
    {
        abort_unless(ctype_digit((string) $record), 404);
        $this->eventId = (int) $record;
        $this->event();
    }

    public static function authorizedEvents(User $user): \Illuminate\Database\Eloquent\Builder
    {
        $query = Event::query()->accessibleBy($user);

        if ($user->is_gate_staff) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isTicketOrganizer()) {
            $query->whereIn('events.id', $user->assignedTicketingEventIds());
        }

        return $query;
    }

    public function event(): Event
    {
        $user = auth()->user();
        abort_unless($user instanceof User && ! $user->is_gate_staff, 403);

        return static::authorizedEvents($user)->whereKey($this->eventId)->firstOrFail();
    }

    public function getTitle(): string|Htmlable
    {
        return $this->event()->name;
    }

    public function metrics(): array
    {
        $event = $this->event();
        $user = auth()->user();
        $stats = [];

        if ($event->canViewReportsBy($user) || $event->canManageRegistrationBy($user)) {
            $stats[] = ['label' => 'Attendees', 'value' => Attendee::query()->where('event_id', $event->id)->count(), 'icon' => 'heroicon-o-users'];
        }

        if ($event->canViewReportsBy($user) || $event->canBeCheckedInBy($user)) {
            $stats[] = ['label' => 'Checked in', 'value' => CheckIn::query()->where('event_id', $event->id)->distinct('attendee_id')->count('attendee_id'), 'icon' => 'heroicon-o-qr-code'];
        }

        if ($event->canBeManagedBy($user) || ($user->isTicketOrganizer() && $user->assignedTicketingEvents()->where('events.id', $event->id)->exists())) {
            $orders = TicketOrder::query()->where('event_id', $event->id)->where('status', TicketOrder::STATUS_PAID);
            $stats[] = ['label' => 'Paid orders', 'value' => (clone $orders)->count(), 'icon' => 'heroicon-o-shopping-cart'];
            $stats[] = ['label' => 'Tickets sold', 'value' => (clone $orders)->sum('quantity'), 'icon' => 'heroicon-o-ticket'];
        }

        return $stats;
    }
}
