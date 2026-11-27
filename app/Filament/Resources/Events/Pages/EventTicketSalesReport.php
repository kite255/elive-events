<?php

namespace App\Filament\Resources\Events\Pages;

use App\Exports\EventFinanceStatement;
use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\TicketOrders\TicketOrderResource;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Maatwebsite\Excel\Facades\Excel;

class EventTicketSalesReport extends Page
{
    protected static string $resource = EventResource::class;
    protected string $view = 'filament.resources.events.pages.event-ticket-sales-report';

    #[Locked]
    public int $eventId;

    public static function canViewSales(Event $event, ?User $user): bool
    {
        return $user instanceof User && ! $user->is_gate_staff
            && EventWorkspace::authorizedEvents($user)->whereKey($event->id)->exists()
            && ($user->isTicketOrganizer()
                ? $user->assignedTicketingEvents()->whereKey($event->id)->exists()
                : $event->canBeManagedBy($user))
            && TicketOrderResource::canViewAny();
    }

    public function mount(int|string $record): void
    {
        abort_unless(ctype_digit((string) $record), 404);
        $this->eventId = (int) $record;
        $this->event();
    }

    public function event(): Event
    {
        $event = Event::query()->findOrFail($this->eventId);
        abort_unless(static::canViewSales($event, auth()->user()), 403);

        return $event;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Ticket sales — '.$this->event()->name;
    }

    public function report(): array
    {
        $event = $this->event();
        $orders = TicketOrderResource::getEloquentQuery()->where('ticket_orders.event_id', $event->id);
        $authorizedOrderIds = (clone $orders)->select('ticket_orders.id');
        $byType = DB::table('ticket_order_items as item')
            ->join('ticket_orders as orders', 'orders.id', '=', 'item.ticket_order_id')
            ->leftJoin('ticket_types as type', function ($join) {
                $join->on('type.id', '=', 'item.ticket_type_id')->on('type.event_id', '=', 'orders.event_id');
            })
            ->where('orders.event_id', $event->id)
            ->whereIn('orders.id', $authorizedOrderIds)
            ->where('orders.status', TicketOrder::STATUS_PAID)
            ->selectRaw('item.ticket_type_id, type.name as ticket_type, orders.currency, SUM(item.quantity) as quantity, SUM(item.total) as item_total')
            ->groupBy('item.ticket_type_id', 'type.name', 'orders.currency')
            ->orderBy('type.name')->orderBy('orders.currency')->get();

        return [
            'orders' => (clone $orders)->selectRaw('currency, status, COUNT(*) as orders, SUM(quantity) as quantity, SUM(total) as total')
                ->groupBy('currency', 'status')->orderBy('currency')->orderBy('status')->get(),
            'types' => $byType,
            'tickets' => Ticket::query()->where('event_id', $event->id)->whereIn('ticket_order_id', $authorizedOrderIds)
                ->selectRaw('status, COUNT(*) as quantity')->groupBy('status')->orderBy('status')->get(),
        ];
    }

    private function exportRows(): array
    {
        $event = $this->event();
        $report = $this->report();
        $rows = [
            ['eLive Events — Ticket sales report'],
            ['Event', $event->name],
            ['Generated', now()->toDateTimeString(), config('app.timezone')],
            ['Order totals are recorded amounts, not settled cash or refund amounts.'],
            [],
            ['Order status summary'],
            ['Currency', 'Status', 'Orders', 'Ordered quantity', 'Order total'],
        ];
        foreach ($report['orders'] as $row) {
            $rows[] = [$row->currency, $row->status, $row->orders, $row->quantity, $row->total];
        }
        $rows[] = [];
        $rows[] = ['Paid order items by purchased ticket type'];
        $rows[] = ['Ticket type', 'Currency', 'Quantity', 'Item total'];
        foreach ($report['types'] as $row) {
            $rows[] = [$row->ticket_type ?? 'Unknown type #'.$row->ticket_type_id, $row->currency, $row->quantity, $row->item_total];
        }
        $rows[] = [];
        $rows[] = ['Current ticket status'];
        $rows[] = ['Status', 'Tickets'];
        foreach ($report['tickets'] as $row) {
            $rows[] = [$row->status, $row->quantity];
        }

        return $rows;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_sales')->label('Export Excel')->icon('heroicon-o-arrow-down-tray')
                ->action(function () {
                    $event = $this->event();

                    return Excel::download(new EventFinanceStatement($this->exportRows()), 'event-'.$event->id.'-ticket-sales.xlsx');
                }),
            Action::make('export_sales_pdf')->label('Export PDF')->icon('heroicon-o-document-arrow-down')
                ->action(function () {
                    $event = $this->event();

                    return app(\App\Services\OperationalReportPdf::class)->download($this->exportRows(), 'event-'.$event->id.'-ticket-sales.pdf');
                }),
        ];
    }
}
