<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
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

    public static function canAccess(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->isTicketOrganizer()) {
            return false;
        }

        return true;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }
}
