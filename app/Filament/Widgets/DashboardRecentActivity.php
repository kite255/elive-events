<?php

namespace App\Filament\Widgets;

use App\Models\Event;
use App\Models\User;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Collection;

class DashboardRecentActivity extends Widget
{
    protected static ?int $sort = -8;

    protected string $view = 'filament.widgets.dashboard-recent-activity';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && ! $user->is_gate_staff;
    }

    public function recentEvents(): Collection
    {
        abort_unless(static::canView(), 403);
        $user = auth()->user();

        $query = Event::query()->accessibleBy($user);
        if ($user->isTicketOrganizer()) {
            $query->whereIn('events.id', $user->assignedTicketingEventIds());
        }

        return $query->whereNotNull('updated_at')
            ->orderByDesc('updated_at')->orderByDesc('id')->limit(5)
            ->get(['id', 'organization_id', 'name', 'status', 'updated_at']);
    }
}
