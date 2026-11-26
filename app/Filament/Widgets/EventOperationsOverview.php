<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Events\Pages\EventWorkspace;
use App\Models\Attendee;
use App\Models\CheckIn;
use App\Models\Event;
use App\Models\TicketOrder;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class EventOperationsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = -20;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.event-operations-overview';

    protected function getColumns(): int|array
    {
        return 5;
    }

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && ! $user->is_gate_staff;
    }

    protected function getStats(): array
    {
        if (! static::canView()) {
            return [];
        }

        $user = auth()->user();
        $events = EventWorkspace::authorizedEvents($user)->get(['id', 'organization_id']);
        $ticketIds = [];
        $registrationIds = [];
        $attendanceIds = [];
        $ticketAssignments = $user->isTicketOrganizer()
            ? $user->assignedTicketingEventIds()->all()
            : [];

        foreach ($events as $event) {
            if ($event->canBeManagedBy($user) || in_array($event->id, $ticketAssignments, true)) {
                $ticketIds[] = $event->id;
            }

            if (! $user->isTicketOrganizer()) {
                if ($event->canManageRegistrationBy($user) || $event->canViewReportsBy($user)) {
                    $registrationIds[] = $event->id;
                }

                if ($event->canBeCheckedInBy($user) || $event->canViewReportsBy($user)) {
                    $attendanceIds[] = $event->id;
                }
            }
        }

        $stats = [
            Stat::make('Events', number_format($events->count()))
                ->icon('heroicon-o-calendar-days'),
        ];

        if ($user->isSuperAdmin() || $ticketIds !== []) {
            $paid = TicketOrder::query()->whereIn('event_id', $ticketIds)
                ->where('status', TicketOrder::STATUS_PAID)
                ->selectRaw('COUNT(*) as order_count, COALESCE(SUM(quantity), 0) as ticket_count')
                ->first();

            $stats[] = Stat::make('Paid orders', number_format($paid->order_count))
                ->icon('heroicon-o-shopping-cart');
            $stats[] = Stat::make('Tickets sold', number_format($paid->ticket_count))
                ->icon('heroicon-o-ticket');
        }

        if ($user->isSuperAdmin() || $registrationIds !== []) {
            $stats[] = Stat::make('Attendees', number_format(
                Attendee::query()->whereIn('event_id', $registrationIds)->count()
            ))->icon('heroicon-o-users');
        }

        if ($user->isSuperAdmin() || $attendanceIds !== []) {
            $stats[] = Stat::make('Checked in', number_format(
                CheckIn::query()->whereIn('event_id', $attendanceIds)->distinct('attendee_id')->count('attendee_id')
            ))->icon('heroicon-o-qr-code');
        }

        return $stats;
    }
}
