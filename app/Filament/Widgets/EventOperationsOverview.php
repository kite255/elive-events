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

    protected function getColumns(): int|array
    {
        return 4;
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
            Stat::make('My accessible events', number_format((clone $ids)->count()))
                ->description('Events available to your account')
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

            $stats[] = Stat::make('Paid ticket orders', number_format((clone $paid)->count()))
                ->description('Confirmed orders from accessible events')
                ->icon('heroicon-o-ticket');
            $stats[] = Stat::make('Tickets sold', number_format((int) (clone $paid)->sum('quantity')))
                ->description('Tickets in paid orders')
                ->icon('heroicon-o-shopping-cart');
        }

        if (! $user->isTicketOrganizer()) {
            $stats[] = Stat::make('Registered attendees', number_format(
                Attendee::query()->whereIn('event_id', clone $ids)->count()
            ))->description('Registration records in accessible events')
                ->icon('heroicon-o-users');

            $stats[] = Stat::make('Attendees checked in', number_format(
                CheckIn::query()->whereIn('event_id', clone $ids)->distinct('attendee_id')->count('attendee_id')
            ))->description('Unique attendees checked in across accessible events')
                ->icon('heroicon-o-qr-code');
        }

        return $stats;
    }
}
