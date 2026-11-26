<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Attendees\AttendeeResource;
use App\Filament\Resources\Events\EventResource;
use App\Models\Attendee;
use App\Models\Event;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use UnitEnum;

class RegistrationDashboard extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Registration Dashboard';

    protected static ?string $title = 'Registration Dashboard';

    protected static string|UnitEnum|null $navigationGroup = 'Events & Registration';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.registration-dashboard';

    public ?int $eventId = null;

    private static function availableEvents(): Collection
    {
        $user = auth()->user();

        if (! $user instanceof User || $user->is_gate_staff || $user->isTicketOrganizer()) {
            return collect();
        }

        return Event::query()
            ->accessibleBy($user)
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (Event $event): bool => $event->canManageRegistrationBy($user))
            ->values();
    }

    public static function canAccess(): bool
    {
        return static::availableEvents()->isNotEmpty();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $events = static::availableEvents();
        $requested = request()->integer('event_id');
        $this->eventId = ($events->firstWhere('id', $requested) ?? $events->first())?->id;
    }

    protected function getViewData(): array
    {
        $events = static::availableEvents();
        abort_if($events->isEmpty(), 403);

        $event = $events->firstWhere('id', $this->eventId);
        abort_unless($event instanceof Event, 403);

        $query = Attendee::query()->where('event_id', $event->id);

        $statuses = (clone $query)->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')->pluck('total', 'status');

        $count = fn (string $status): int => (int) ($statuses[$status] ?? 0);
        $confirmed = $count('registered') + $count('approved') + $count('confirmed') + $count('checked_in');
        $occupied = $confirmed + $count('pending_approval');
        $capacity = (int) $event->capacity;

        $sources = (clone $query)->selectRaw('registration_source, COUNT(*) AS total')
            ->groupBy('registration_source')->orderByDesc('total')->get();

        $recent = (clone $query)->with('category')->latest('id')->limit(8)->get();
        $badges = (clone $query)->whereNotNull('badge_path')->where('badge_path', '!=', '')->count();

        return [
            'events' => $events,
            'selectedEvent' => $event,
            'stats' => [
                'Total attendees' => (int) $statuses->sum(),
                'Accepted status' => $confirmed,
                'Pending approval' => $count('pending_approval'),
                'Waitlisted' => $count('waitlisted'),
                'Checked in' => $count('checked_in'),
                'Badge records' => $badges,
            ],
            'occupied' => $occupied,
            'capacity' => $capacity,
            'sources' => $sources,
            'recent' => $recent,
            'registrationUrl' => EventResource::getPublicRegistrationUrl($event),
            'manageUrl' => EventResource::canEdit($event) ? EventResource::getUrl('edit', ['record' => $event]) : null,
            'attendeeUrl' => AttendeeResource::canViewAny() ? AttendeeResource::getUrl('index') : null,
        ];
    }
}
