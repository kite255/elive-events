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
        $user = auth()->user();

        return $user instanceof User && ! $user->is_gate_staff;
    }
}
