<?php

namespace App\Filament\Gate;

use App\Filament\Pages\TicketScanner;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class GateTicketScanner extends TicketScanner
{
    protected static ?string $slug = 'ticket-scanner';

    public static function canAccess(): bool
    {
        return static::scannerUser()?->canUseGateScanner() ?? false;
    }

    protected static function scannerUser(): ?User
    {
        return Auth::guard('gate')->user();
    }

    public function getScanUrl(): string
    {
        return route('gate.ticket-scanner.scan');
    }

    public function getAssignedEventLabel(): ?string
    {
        return static::scannerUser()?->gateScannerEvents()->pluck('events.name')->implode(', ');
    }
}
