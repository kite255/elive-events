<?php

namespace App\Filament\Widgets;

use App\Models\Event;
use App\Models\TicketOrder;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PlatformFinanceOverview extends StatsOverviewWidget
{
    protected static ?int $sort = -10;

    protected function getColumns(): int|array
    {
        return 4;
    }

    public static function canView(): bool
    {
        return auth()->user() instanceof User
            && auth()->user()->isSuperAdmin();
    }

    protected function getStats(): array
    {
        // Paid orders are the source of truth for issued-ticket revenue.
        // Refunded and pending orders must not be included here.
        $orders = TicketOrder::query()
            ->where('status', TicketOrder::STATUS_PAID);

        $gross = (float) (clone $orders)->sum('gross_amount');
        $platformFees = (float) (clone $orders)->sum('platform_commission_amount');
        $gatewayFees = (float) (clone $orders)->sum('gateway_fee_amount');
        $organizerNet = (float) (clone $orders)->sum('organizer_net_amount');

        return [
            Stat::make('Active events', number_format(Event::query()
                ->where('status', Event::STATUS_ACTIVE)->count()))
                ->description('Events currently marked active')
                ->icon('heroicon-o-calendar-days'),

            Stat::make('Paid ticket orders', number_format((clone $orders)->count()))
                ->description('Completed ticket orders only')
                ->icon('heroicon-o-ticket'),

            Stat::make('Gross ticket sales', 'TZS '.number_format($gross, 2))
                ->description('Before fees; not platform income')
                ->icon('heroicon-o-banknotes'),

            Stat::make('eLive ticket commission', 'TZS '.number_format($platformFees, 2))
                ->description('Platform commission from paid orders')
                ->icon('heroicon-o-chart-bar'),

            Stat::make('Gateway fees', 'TZS '.number_format($gatewayFees, 2))
                ->description('Payment processing fees; not platform income')
                ->icon('heroicon-o-credit-card'),

            Stat::make('Organizer net earnings', 'TZS '.number_format($organizerNet, 2))
                ->description('After recorded ticket-sale deductions; not unpaid payout balance')
                ->icon('heroicon-o-wallet'),
        ];
    }
}
