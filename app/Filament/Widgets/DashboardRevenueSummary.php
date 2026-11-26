<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\OrganizerFinanceOverview;
use App\Models\Event;
use App\Models\TicketOrder;
use App\Models\User;
use Filament\Widgets\Widget;

class DashboardRevenueSummary extends Widget
{
    protected static ?int $sort = -18;

    protected string $view = 'filament.widgets.dashboard-revenue-summary';

    protected int|string|array $columnSpan = 'full';

    public string $period = '30';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && ! $user->is_gate_staff
            && ($user->isSuperAdmin() || OrganizerFinanceOverview::canAccess());
    }

    public function revenue(): array
    {
        abort_unless(static::canView(), 403);
        $user = auth()->user();

        $events = Event::query()->accessibleBy($user);
        if (! $user->isSuperAdmin()) {
            if ($user->isTicketOrganizer()) {
                $events->whereIn('events.id', $user->assignedTicketingEventIds());
            } else {
                $events->whereIn('organization_id', $user->ownedOrganizations()->pluck('organizations.id'));
            }
        }

        $period = in_array($this->period, ['7', '30', '90', 'all'], true) ? $this->period : '30';
        $orders = TicketOrder::query()
            ->whereIn('event_id', $events->select('events.id'))
            ->where('status', TicketOrder::STATUS_PAID);

        if ($period !== 'all') {
            $orders->whereBetween('paid_at', [now()->subDays((int) $period - 1)->startOfDay(), now()]);
        }

        return $orders->groupBy('currency')->orderBy('currency')
            ->selectRaw('currency, COUNT(*) as paid_orders, SUM(total) as gross_sales, COALESCE(SUM(quantity), 0) as tickets_sold')
            ->get()->map(fn ($row) => [
                'currency' => $row->currency,
                'paid_orders' => (int) $row->paid_orders,
                'gross_sales' => $row->gross_sales,
                'tickets_sold' => (int) $row->tickets_sold,
            ])->all();
    }
}
