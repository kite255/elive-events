<?php

namespace App\Filament\Resources\TicketCheckIns;

use App\Filament\Resources\TicketCheckIns\Pages\ListTicketCheckIns;
use App\Filament\Resources\TicketCheckIns\Tables\TicketCheckInsTable;
use App\Models\TicketCheckIn;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class TicketCheckInResource extends Resource
{
    protected static ?string $model = TicketCheckIn::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Ticketing';

    protected static ?string $navigationLabel = 'Check-in History';

    protected static ?string $modelLabel = 'Ticket Check-in';

    protected static ?string $pluralModelLabel = 'Check-in History';

    protected static ?int $navigationSort = 50;

    public static function table(Table $table): Table
    {
        return TicketCheckInsTable::configure($table);
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        return $user->isSuperAdmin()
            || $user->isTicketOrganizer()
            || $user->managedOrganizations()->exists()
            || $user->eventManagerEvents()->exists()
            || $user->checkInEvents()->exists();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'event',
                'ticket.order',
                'ticket.ticketType',
                'checkInPoint',
                'checkedInBy',
            ])
            ->accessibleBy(auth()->user());
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTicketCheckIns::route('/'),
        ];
    }
}
