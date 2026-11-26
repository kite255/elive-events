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

    public function moduleLinks(): array
    {
        $event = $this->event();
        $user = auth()->user();
        $links = [];

        if ($event->canBeManagedBy($user)) {
            $links[] = [
                'label' => 'Event settings',
                'icon' => 'heroicon-o-cog-6-tooth',
                'url' => EventResource::getUrl('edit', ['record' => $event]),
            ];
        }

        if ($event->canBeCheckedInBy($user)
            && in_array($event->status, [Event::STATUS_ACTIVE, Event::STATUS_DRAFT], true)) {
            $links[] = [
                'label' => 'Check-in station',
                'icon' => 'heroicon-o-qr-code',
                'url' => \App\Filament\Pages\CheckInStation::getUrl(['event_id' => $event->id]),
            ];
        }

        if ($user->canManageEventCommunication($event)) {
            $links[] = [
                'label' => 'Communications',
                'icon' => 'heroicon-o-envelope',
                'url' => \App\Filament\Pages\CommunicationCenter::getUrl(['event_id' => $event->id]),
            ];
        }

        return $links;
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
