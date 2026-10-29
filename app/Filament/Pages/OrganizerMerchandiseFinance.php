<?php

namespace App\Filament\Pages;

use UnitEnum;

class OrganizerMerchandiseFinance extends OrganizerFinanceOverview
{
    protected static ?string $navigationLabel = 'Merchandise Finance';
    protected static string|UnitEnum|null $navigationGroup = 'Finance';
    protected static ?string $title = 'Merchandise Finance';
    protected static ?string $slug = 'merchandise-finance';
    protected static ?int $navigationSort = 3;

    public function merchandiseTransactions(): \Illuminate\Support\Collection
    {
        if ($this->selectedEventId === null) {
            return collect();
        }

        $event = $this->resolveAccessibleEvent($this->selectedEventId);
        if (! $event) {
            return collect();
        }

        return \App\Models\Payment::query()->where('event_id', $event->id)->whereNotNull('merchandise_order_id')->with('merchandiseOrder:id,order_number,fulfillment_status')->orderByDesc('id')->limit(50)->get(['id', 'merchandise_order_id', 'paid_at', 'created_at', 'amount', 'currency', 'status']);
    }

    protected string $view = 'filament.pages.organizer-merchandise-finance';
}
