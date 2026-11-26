<?php

namespace App\Filament\Widgets;

use App\Models\Event;
use App\Models\Payment;
use App\Models\User;
use Filament\Widgets\Widget;

class DashboardActionRequired extends Widget
{
    protected static ?int $sort = -9;

    protected string $view = 'filament.widgets.dashboard-action-required';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && ! $user->is_gate_staff && $user->isSuperAdmin();
    }

    public function issues(): array
    {
        abort_unless(static::canView(), 403);

        $events = Event::query()->accessibleBy(auth()->user())->select('events.id');

        return [
            'draft_events' => Event::query()->whereIn('id', clone $events)->where('status', Event::STATUS_DRAFT)->count(),
            'unfulfilled_payments' => Payment::query()->whereIn('event_id', clone $events)
                ->where('status', Payment::STATUS_COMPLETED)->whereNull('fulfilled_at')
                ->where('updated_at', '<=', now()->subMinutes(15))->count(),
        ];
    }
}
