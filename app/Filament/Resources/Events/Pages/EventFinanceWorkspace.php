<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use App\Models\Event;
use App\Models\User;
use App\Services\EventFinanceWorkspaceSummary;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Locked;

class EventFinanceWorkspace extends Page
{
    protected static string $resource = EventResource::class;

    protected string $view = 'filament.resources.events.pages.event-finance-workspace';

    #[Locked]
    public int $eventId;

    public static function canViewFinance(Event $event, ?User $user): bool
    {
        return $user instanceof User && ! $user->is_gate_staff
            && ($user->isSuperAdmin() || $user->canManageOrganization($event->organization_id))
            && EventWorkspace::authorizedEvents($user)->whereKey($event->id)->exists();
    }

    public function mount(int|string $record): void
    {
        abort_unless(ctype_digit((string) $record), 404);
        $this->eventId = (int) $record;
        $this->event();
    }

    public function event(): Event
    {
        $event = Event::query()->findOrFail($this->eventId);
        abort_unless(static::canViewFinance($event, auth()->user()), 403);

        return $event;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Finance — '.$this->event()->name;
    }

    public function summary(): array
    {
        return app(EventFinanceWorkspaceSummary::class)->summarize($this->event()->id);
    }
}
