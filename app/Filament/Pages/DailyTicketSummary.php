<?php

namespace App\Filament\Pages;

use App\Filament\Resources\TicketOrders\TicketOrderResource;
use App\Models\TicketOrder;
use App\Models\User;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

class DailyTicketSummary extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Daily Ticket Summary';
    protected static ?string $title = 'Daily Ticket Summary';
    protected static string|UnitEnum|null $navigationGroup = 'Reports';
    protected static ?int $navigationSort = 15;
    protected string $view = 'filament.pages.daily-ticket-summary';

    public ?int $eventId = null;
    public string $startDate = '';
    public string $endDate = '';

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->endDate = now('Africa/Dar_es_Salaam')->toDateString();
        $this->startDate = now('Africa/Dar_es_Salaam')->subDays(29)->toDateString();
    }

    public static function canAccess(): bool
    {
        return auth()->user() instanceof User && TicketOrderResource::canViewAny();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function getEventsProperty(): Collection
    {
        return TicketOrderResource::getEloquentQuery()
            ->join('events', 'events.id', '=', 'ticket_orders.event_id')
            ->select('events.id', 'events.name')
            ->distinct()
            ->orderBy('events.name')
            ->get();
    }

    private function bounds(): array
    {
        $zone = 'Africa/Dar_es_Salaam';
        $end = CarbonImmutable::parse($this->endDate ?: now($zone)->toDateString(), $zone)->startOfDay();
        $start = CarbonImmutable::parse($this->startDate ?: $end->toDateString(), $zone)->startOfDay();
        abort_if($start->greaterThan($end) || $end->diffInDays($start) > 366, 422, 'Select a valid date range of at most 366 days.');

        return [$start, $end];
    }

    public function getDailyRowsProperty(): Collection
    {
        [$start, $end] = $this->bounds();
        $orders = TicketOrderResource::getEloquentQuery()
            ->where('ticket_orders.status', TicketOrder::STATUS_PAID)
            ->whereNotNull('ticket_orders.paid_at')
            ->where('ticket_orders.paid_at', '>=', $start->utc())
            ->where('ticket_orders.paid_at', '<', $end->addDay()->utc())
            ->when($this->eventId, fn ($query) => $query->where('ticket_orders.event_id', $this->eventId))
            ->get(['ticket_orders.id', 'ticket_orders.event_id', 'ticket_orders.quantity', 'ticket_orders.total', 'ticket_orders.organizer_net_amount', 'ticket_orders.paid_at']);

        return $orders->groupBy(fn (TicketOrder $order) => $order->paid_at->timezone('Africa/Dar_es_Salaam')->toDateString())
            ->map(fn (Collection $day, string $date) => [
                'date' => $date,
                'orders' => $day->count(),
                'tickets' => $day->sum('quantity'),
                'free_tickets' => $day->filter(fn ($order) => (float) $order->total === 0.0)->sum('quantity'),
                'gross' => round($day->sum(fn ($order) => (float) $order->total), 2),
                'organizer_net' => round($day->sum(fn ($order) => (float) $order->organizer_net_amount), 2),
            ])
            ->sortKeysDesc()
            ->values();
    }

    public function getTotalsProperty(): array
    {
        $rows = $this->dailyRows;
        return [
            'orders' => $rows->sum('orders'),
            'tickets' => $rows->sum('tickets'),
            'free_tickets' => $rows->sum('free_tickets'),
            'gross' => round($rows->sum('gross'), 2),
            'organizer_net' => round($rows->sum('organizer_net'), 2),
        ];
    }

    public function exportCsv(): StreamedResponse
    {
        $rows = $this->dailyRows;
        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, ['Date (Africa/Dar_es_Salaam)', 'Paid Orders', 'Tickets', 'Free Tickets', 'Gross Ticket Sales (TZS)', 'Organizer Net (TZS)']);
            foreach ($rows as $row) {
                fputcsv($handle, array_values($row));
            }
            fclose($handle);
        }, 'daily-ticket-summary-'.now('Africa/Dar_es_Salaam')->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
