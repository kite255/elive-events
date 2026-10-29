<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\Widget;

class DashboardQuickActions extends Widget
{
    protected static ?int $sort = -19;

    protected string $view = 'filament.widgets.dashboard-quick-actions';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user() instanceof User;
    }
}
