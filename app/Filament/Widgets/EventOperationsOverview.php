<?php

namespace App\Filament\Widgets;

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
        return auth()->user() instanceof User;
    }

    protected function getStats(): array
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        $events = Event::query()->accessibleBy($user);
        if ($user->isTicketOrganizer()) {
            $events = Event::query()->whereIn('id', $user->assignedTicketingEventIds());
        }

        $ids = $events->select('events.id');

        $stats = [
            Stat::make('Events', number_format((clone $ids)->count()))
                ->icon('heroicon-o-calendar-days'),
        ];

        $canViewTicketSales = $user->isSuperAdmin()
            || $user->isTicketOrganizer()
            || $user->managedOrganizations()->exists()
            || $user->eventManagerEvents()->exists();

        if ($canViewTicketSales) {
            $paid = TicketOrder::query()
                ->whereIn('event_id', clone $ids)
                ->where('status', TicketOrder::STATUS_PAID);

            $stats[] = Stat::make('Paid orders', number_format((clone $paid)->count()))
                ->icon('heroicon-o-shopping-cart');
            $stats[] = Stat::make('Tickets sold', number_format((int) (clone $paid)->sum('quantity')))
                ->icon('heroicon-o-ticket');
        }

        if (! $user->isTicketOrganizer()) {
            $stats[] = Stat::make('Attendees', number_format(
                Attendee::query()->whereIn('event_id', clone $ids)->count()
            ))
                ->icon('heroicon-o-users');

            $stats[] = Stat::make('Checked in', number_format(
                CheckIn::query()->whereIn('event_id', clone $ids)->distinct('attendee_id')->count('attendee_id')
            ))
                ->icon('heroicon-o-qr-code');
        }

        return $stats;
    }
}
