<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Events\EventResource;
use App\Models\Event;
use App\Models\User;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Collection;

class DashboardUpcomingEvents extends Widget
{
    protected static ?int $sort = -10;

    protected string $view = 'filament.widgets.dashboard-upcoming-events';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && ! $user->is_gate_staff
            && (new static())->upcomingEvents()->isNotEmpty();
    }

    public function upcomingEvents(): Collection
    {
        $user = auth()->user();

        abort_unless($user instanceof User && ! $user->is_gate_staff, 403);

        $query = Event::query()->accessibleBy($user);
        if ($user->isTicketOrganizer()) {
            $query->whereIn('events.id', $user->assignedTicketingEventIds());
        }

        return $query->where('status', Event::STATUS_ACTIVE)
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')->orderBy('id')->limit(2)->get();
    }
}
