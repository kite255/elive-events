<?php

namespace App\Filament\Resources\Events\Pages;

use App\Exports\EventFinanceStatement;
use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\MerchandiseOrders\MerchandiseOrderResource;
use App\Filament\Resources\MerchandiseProducts\MerchandiseProductResource;
use App\Models\Event;
use App\Models\MerchandiseOrder;
use App\Models\MerchandiseProduct;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Maatwebsite\Excel\Facades\Excel;

class EventMerchandiseSalesReport extends Page
{
    protected static string $resource = EventResource::class;
    protected string $view = 'filament.resources.events.pages.event-merchandise-sales-report';

    #[Locked]
    public int $eventId;

    public static function canViewSales(Event $event, ?User $user): bool
    {
        return $user instanceof User && ! $user->is_gate_staff
            && auth()->id() === $user->getKey()
            && EventWorkspace::authorizedEvents($user)->whereKey($event->id)->exists()
            && MerchandiseOrderResource::canManageMerchandiseFor($event);
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
        return 'Merchandise sales — '.$this->event()->name;
    }

    public function report(): array
    {
        $event = $this->event();
        $orders = MerchandiseOrder::query()->where('event_id', $event->id);
        $paid = (clone $orders)->where('status', 'paid');
        $products = DB::table('merchandise_orders as orders')
            ->leftJoin('merchandise_products as product', function ($join) {
                $join->on('product.id', '=', 'orders.merchandise_product_id')
                    ->on('product.event_id', '=', 'orders.event_id');
            })
            ->where('orders.event_id', $event->id)->where('orders.status', 'paid');

        return [
            'orders' => (clone $orders)->selectRaw('status, COUNT(*) as orders, SUM(quantity) as quantity, SUM(total_tzs) as total')
                ->groupBy('status')->orderBy('status')->get(),
            'products' => (clone $products)->selectRaw('orders.merchandise_product_id, product.name as product_name, COUNT(*) as orders, SUM(orders.quantity) as quantity, SUM(orders.total_tzs) as total')
                ->groupBy('orders.merchandise_product_id', 'product.name')->orderBy('product.name')->get(),
            'variants' => (clone $products)->selectRaw('orders.merchandise_product_id, product.name as product_name, orders.color, orders.size, SUM(orders.quantity) as quantity, SUM(orders.total_tzs) as total')
                ->groupBy('orders.merchandise_product_id', 'product.name', 'orders.color', 'orders.size')->orderBy('product.name')->orderBy('orders.color')->orderBy('orders.size')->get(),
            'fulfillment' => (clone $paid)->selectRaw('fulfillment_method, fulfillment_status, COUNT(*) as orders, SUM(quantity) as quantity')
                ->groupBy('fulfillment_method', 'fulfillment_status')->orderBy('fulfillment_method')->orderBy('fulfillment_status')->get(),
            'inventory' => MerchandiseProductResource::canManageMerchandiseFor($event)
                ? MerchandiseProduct::query()->where('event_id', $event->id)->orderBy('name')->get(['id', 'name', 'stock_quantity', 'is_active', 'event_merchandise_id'])
                : collect(),
        ];
    }

    public function sections(): array
    {
        $report = $this->report();
        $productLabel = fn ($row) => $row->product_name ?? 'Unknown product #'.($row->merchandise_product_id ?? '—');

        return [
            ['title' => 'Order status summary (TZS)', 'headings' => ['Status', 'Orders', 'Units', 'Recorded order total'], 'rows' => $report['orders']->map(fn ($row) => [$row->status, $row->orders, $row->quantity, $row->total])->all()],
            ['title' => 'Paid sales by product (TZS)', 'headings' => ['Product', 'Orders', 'Units', 'Recorded total'], 'rows' => $report['products']->map(fn ($row) => [$productLabel($row), $row->orders, $row->quantity, $row->total])->all()],
            ['title' => 'Paid sales by colour and size (TZS)', 'headings' => ['Product', 'Colour', 'Size', 'Units', 'Recorded total'], 'rows' => $report['variants']->map(fn ($row) => [$productLabel($row), $row->color, $row->size, $row->quantity, $row->total])->all()],
            ['title' => 'Paid order fulfillment', 'headings' => ['Method', 'Status', 'Orders', 'Units'], 'rows' => $report['fulfillment']->map(fn ($row) => [$row->fulfillment_method, $row->fulfillment_status, $row->orders, $row->quantity])->all()],
            ['title' => 'Recorded product stock', 'headings' => ['Product', 'Stock field', 'Active', 'Shared inventory'], 'rows' => $report['inventory']->map(fn ($row) => [$row->name, $row->stock_quantity, $row->is_active ? 'Yes' : 'No', $row->event_merchandise_id ? 'Yes' : 'No'])->all()],
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_merchandise_sales')->label('Export Excel')->icon('heroicon-o-arrow-down-tray')
                ->action(function () {
                    $event = $this->event();
                    $rows = [
                        ['eLive Events — Merchandise sales report'],
                        ['Event', $event->name],
                        ['Generated', now()->toDateTimeString(), config('app.timezone')],
                        ['Public shop orders only. Recorded TZS totals are not settled cash. Stock fields are not guaranteed available inventory.'],
                    ];
                    foreach ($this->sections() as $section) {
                        $rows[] = [];
                        $rows[] = [$section['title']];
                        $rows[] = $section['headings'];
                        foreach ($section['rows'] as $row) {
                            $rows[] = $row;
                        }
                    }

                    return Excel::download(new EventFinanceStatement($rows), 'event-'.$event->id.'-merchandise-sales.xlsx');
                }),
        ];
    }
}
