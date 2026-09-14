<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class TicketScanner extends Page
{
    protected static string|BackedEnum|null $navigationIcon =
        Heroicon::OutlinedQrCode;

    protected static string|UnitEnum|null $navigationGroup =
        'Attendance';

    protected static ?string $navigationLabel =
        'Ticket Scanner';

    protected static ?string $title =
        'Ticket Scanner';

    protected static ?int $navigationSort = 2;

    protected string $view =
        'filament.pages.ticket-scanner';
}
