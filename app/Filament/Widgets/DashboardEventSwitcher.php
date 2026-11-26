<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Events\Pages\EventWorkspace;
use App\Models\User;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Collection;

class DashboardEventSwitcher extends Widget
{
    protected static ?int $sort = -21;

    protected string $view = 'filament.widgets.dashboard-event-switcher';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && ! $user->is_gate_staff;
    }

    public function eventOptions(): Collection
    {
        abort_unless(static::canView(), 403);

        return EventWorkspace::authorizedEvents(auth()->user())
            ->orderByDesc('starts_at')->orderBy('id')->limit(100)->get(['id', 'name']);
    }

    public function openEvent(string $id): void
    {
        abort_unless(static::canView(), 403);
        abort_unless(ctype_digit($id), 404);

        $event = EventWorkspace::authorizedEvents(auth()->user())->whereKey((int) $id)->firstOrFail();

        $this->redirect(\App\Filament\Resources\Events\EventResource::getUrl('workspace', ['record' => $event->id]));
    }
}
