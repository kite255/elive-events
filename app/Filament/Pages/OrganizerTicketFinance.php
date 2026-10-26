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

    protected string $view = 'filament.pages.organizer-ticket-finance';
}
