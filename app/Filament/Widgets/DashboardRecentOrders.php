<?php

namespace App\Filament\Widgets;

use App\Models\Event;
use App\Models\TicketOrder;
use App\Models\User;
use Filament\Widgets\Widget;

class DashboardRecentOrders extends Widget
{
    protected static ?int $sort = -17;

    protected string $view = 'filament.widgets.dashboard-recent-orders';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return \App\Filament\Pages\OrganizerSalesDashboard::canAccess();
    }

    public function recentOrders(): array
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user) {
            return [];
        }

        $events = $user->isTicketOrganizer()
            ? Event::query()->whereIn('id', $user->assignedTicketingEventIds())
            : Event::query()->accessibleBy($user);

        return TicketOrder::query()
            ->whereIn('event_id', $events->select('events.id'))
            ->where('status', TicketOrder::STATUS_PAID)
            ->with('event:id,name')
            ->orderByDesc('paid_at')
            ->limit(5)
            ->get(['id', 'event_id', 'order_number', 'total', 'currency', 'paid_at'])
            ->map(fn (TicketOrder $order) => [
                'number' => $order->order_number,
                'event' => $order->event?->name ?? 'Event',
                'amount' => ($order->currency ?: 'TZS').' '.number_format((float) $order->total, 0),
                'date' => $order->paid_at?->format('d M Y, H:i') ?? '—',
            ])->all();
    }
}
