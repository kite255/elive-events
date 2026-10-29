<?php

namespace App\Filament\Pages;

use UnitEnum;

class OrganizerTicketFinance extends OrganizerFinanceOverview
{
    protected static ?string $navigationLabel = 'Ticket Finance';
    protected static string|UnitEnum|null $navigationGroup = 'Finance';
    protected static ?string $title = 'Ticket Finance';
    protected static ?string $slug = 'ticket-finance';
    protected static ?int $navigationSort = 2;

    public function ticketTransactions(): \Illuminate\Support\Collection
    {
        if ($this->selectedEventId === null) {
            return collect();
        }

        $event = $this->resolveAccessibleEvent($this->selectedEventId);
        if (! $event) {
            return collect();
        }

        return \App\Models\TicketOrder::query()->where('event_id', $event->id)->where('status', \App\Models\TicketOrder::STATUS_PAID)->orderByDesc('paid_at')->orderByDesc('id')->limit(50)->get(['id', 'order_number', 'paid_at', 'gross_amount', 'total_charges', 'organizer_net_amount', 'currency']);
    }

    protected string $view = 'filament.pages.organizer-ticket-finance';
}
