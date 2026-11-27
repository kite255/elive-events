<?php

namespace App\Filament\Resources\Events\Pages;

use App\Exports\EventFinanceStatement;
use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\TicketOrders\TicketOrderResource;
use App\Models\Attendee;
use App\Models\CheckIn;
use App\Models\Event;
use App\Models\MerchandiseOrder;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Attributes\Locked;
use Maatwebsite\Excel\Facades\Excel;

class EventPerformanceReport extends Page
{
    protected static string $resource = EventResource::class;
    protected string $view = 'filament.resources.events.pages.event-performance-report';

    #[Locked]
    public int $eventId;

    public static function canViewPerformance(Event $event, ?User $user): bool
    {
        return $user instanceof User && ! $user->is_gate_staff
            && EventWorkspace::authorizedEvents($user)->whereKey($event->id)->exists()
            && (($event->canViewReportsBy($user) && ! $user->isTicketOrganizer())
                || EventTicketSalesReport::canViewSales($event, $user)
                || EventMerchandiseSalesReport::canViewSales($event, $user)
                || EventFinanceWorkspace::canViewFinance($event, $user));
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
        abort_unless(static::canViewPerformance($event, auth()->user()), 403);

        return $event;
    }

    public function getTitle(): string|Htmlable
    {
        return 'Event performance — '.$this->event()->name;
    }

    public function sections(): array
    {
        $event = $this->event();
        $user = auth()->user();
        $sections = [];

        if ($event->canViewReportsBy($user) && ! $user->isTicketOrganizer()) {
            $attendees = Attendee::query()->where('event_id', $event->id);
            $eligible = (clone $attendees)->whereNotIn('status', ['rejected', 'cancelled', 'waitlisted', 'pending_approval']);
            $total = (clone $eligible)->count();
            $checked = CheckIn::query()->where('event_id', $event->id)->whereNull('event_session_id')
                ->whereIn('attendee_id', (clone $eligible)->select('id'))->distinct()->count('attendee_id');
            $sections[] = ['title' => 'Registration and attendance', 'headings' => ['Metric', 'Value'], 'rows' => [
                ['Attendee records (all statuses)', (clone $attendees)->count()],
                ['Eligible attendee records', $total],
                ['Eligible attendees with event-level check-in', $checked],
                ['Eligible attendees without event-level check-in', max(0, $total - $checked)],
                ['Attendance rate among eligible records', $total ? round($checked / $total * 100, 1).'%' : '—'],
            ]];
            $sections[] = ['title' => 'Registration status', 'headings' => ['Status', 'Records'], 'rows' =>
                (clone $attendees)->selectRaw('status, COUNT(*) as quantity')->groupBy('status')->orderBy('status')->get()
                    ->map(fn ($row) => [$row->status, $row->quantity])->all()];
        }

        if (EventTicketSalesReport::canViewSales($event, $user)) {
            $orders = TicketOrderResource::getEloquentQuery()->where('ticket_orders.event_id', $event->id);
            $sections[] = ['title' => 'Paid ticket orders', 'headings' => ['Currency', 'Orders', 'Ordered units', 'Recorded total'], 'rows' =>
                (clone $orders)->where('status', TicketOrder::STATUS_PAID)
                    ->selectRaw('currency, COUNT(*) as orders, SUM(quantity) as quantity, SUM(total) as total')
                    ->groupBy('currency')->orderBy('currency')->get()->map(fn ($row) => [$row->currency, $row->orders, $row->quantity, $row->total])->all()];
            $sections[] = ['title' => 'Current ticket status', 'headings' => ['Status', 'Tickets'], 'rows' =>
                Ticket::query()->where('event_id', $event->id)->whereIn('ticket_order_id', (clone $orders)->select('ticket_orders.id'))
                    ->selectRaw('status, COUNT(*) as quantity')->groupBy('status')->orderBy('status')->get()
                    ->map(fn ($row) => [$row->status, $row->quantity])->all()];
        }

        if (EventMerchandiseSalesReport::canViewSales($event, $user)) {
            $orders = MerchandiseOrder::query()->where('event_id', $event->id)->where('status', 'paid');
            $sections[] = ['title' => 'Paid public shop orders (TZS)', 'headings' => ['Metric', 'Value'], 'rows' => [
                ['Paid orders', (clone $orders)->count()],
                ['Ordered units', (clone $orders)->sum('quantity')],
                ['Recorded order total', (clone $orders)->sum('total_tzs')],
                ['Collected orders', (clone $orders)->where('fulfillment_status', 'collected')->count()],
            ]];
        }

        if (EventFinanceWorkspace::canViewFinance($event, $user)) {
            $sections[] = ['title' => 'Recorded paid ticket earnings', 'headings' => ['Currency', 'Gross', 'Charges', 'Organizer net'], 'rows' =>
                TicketOrder::query()->where('event_id', $event->id)->where('status', TicketOrder::STATUS_PAID)
                    ->selectRaw('currency, SUM(gross_amount) as gross, SUM(total_charges) as charges, SUM(organizer_net_amount) as net')
                    ->groupBy('currency')->orderBy('currency')->get()->map(fn ($row) => [$row->currency, $row->gross, $row->charges, $row->net])->all()];
        }

        return $sections;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_performance')->label('Export Excel')->icon('heroicon-o-arrow-down-tray')
                ->action(function () {
                    $event = $this->event();
                    $rows = [
                        ['eLive Events — Event performance'],
                        ['Event', $event->name],
                        ['Generated', now()->toDateTimeString(), config('app.timezone')],
                        ['Recorded amounts only. Separate currencies and revenue sources; not settlement evidence.'],
                    ];
                    foreach ($this->sections() as $section) {
                        $rows[] = [];
                        $rows[] = [$section['title']];
                        $rows[] = $section['headings'];
                        foreach ($section['rows'] as $row) {
                            $rows[] = $row;
                        }
                    }

                    return Excel::download(new EventFinanceStatement($rows), 'event-'.$event->id.'-performance.xlsx');
                }),
        ];
    }
}
