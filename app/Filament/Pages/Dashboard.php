<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\DashboardEventSwitcher;
use App\Filament\Widgets\DashboardQuickActions;
use App\Filament\Widgets\DashboardRevenueSummary;
use App\Filament\Widgets\DashboardUpcomingEvents;
use App\Filament\Widgets\EventOperationsOverview;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            DashboardEventSwitcher::class,
            EventOperationsOverview::class,
            DashboardQuickActions::class,
            DashboardRevenueSummary::class,
            DashboardUpcomingEvents::class,
        ];
    }
}
