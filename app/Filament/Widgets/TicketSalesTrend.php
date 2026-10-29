<?php

namespace App\Filament\Widgets;

use App\Models\Event;
use App\Models\TicketOrder;
use App\Models\User;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class TicketSalesTrend extends ChartWidget
{
    protected ?string $heading = 'Ticket sales · last 7 days';
    protected static ?int $sort = -18;
    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && (
            $user->isSuperAdmin()
            || $user->isTicketOrganizer()
            || $user->managedOrganizations()->exists()
            || $user->eventManagerEvents()->exists()
        );
    }

    protected function getData(): array
    {
        /** @var User $user */
        $user = auth()->user();

        $events = $user->isTicketOrganizer()
            ? Event::query()->whereIn('id', $user->assignedTicketingEventIds())
            : Event::query()->accessibleBy($user);

        $from = now()->startOfDay()->subDays(6);
        $totals = TicketOrder::query()
            ->whereIn('event_id', $events->select('events.id'))
            ->where('status', TicketOrder::STATUS_PAID)
            ->where('paid_at', '>=', $from)
            ->selectRaw('DATE(paid_at) as sale_day, SUM(total) as revenue')
            ->groupByRaw('DATE(paid_at)')
            ->pluck('revenue', 'sale_day');

        $labels = [];
        $data = [];

        for ($i = 0; $i < 7; $i++) {
            $day = $from->copy()->addDays($i);
            $labels[] = $day->format('D j M');
            $data[] = (float) ($totals[$day->toDateString()] ?? 0);
        }

        return [
            'datasets' => [[
                'label' => 'Paid ticket sales (TZS)',
                'data' => $data,
                'borderColor' => '#007AB2',
                'backgroundColor' => 'rgba(0, 122, 178, 0.12)',
                'fill' => true,
                'tension' => 0.35,
            ]],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
