<?php

namespace App\Filament\Resources\TicketOrders;

use App\Filament\Resources\TicketOrders\Pages\CreateTicketOrder;
use App\Filament\Resources\TicketOrders\Pages\EditTicketOrder;
use App\Filament\Resources\TicketOrders\Pages\ListTicketOrders;
use App\Filament\Resources\TicketOrders\Schemas\TicketOrderForm;
use App\Filament\Resources\TicketOrders\Tables\TicketOrdersTable;
use App\Models\TicketOrder;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class TicketOrderResource extends Resource
{
    protected static ?string $model = TicketOrder::class;

    protected static ?string $recordTitleAttribute = 'order_number';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static string|UnitEnum|null $navigationGroup = 'Ticketing';

    protected static ?string $navigationLabel = 'Ticket Orders';

    protected static ?string $modelLabel = 'Ticket Order';

    protected static ?string $pluralModelLabel = 'Ticket Orders';

    protected static ?int $navigationSort = 60;

    public static function form(Schema $schema): Schema
    {
        return TicketOrderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TicketOrdersTable::configure($table);
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
            || $user->eventManagerEvents()->exists();
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        if (! $user instanceof User || $user->isTicketOrganizer()) {
            return false;
        }

        return parent::canCreate();
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();

        if (! $user instanceof User || $user->isTicketOrganizer()) {
            return false;
        }

        if (! $user->isSuperAdmin()) {
            $event = $record->event;

            if (! $event || ! $event->canBeManagedBy($user)) {
                return false;
            }
        }

        return parent::canEdit($record);
    }

    public static function canDelete(Model $record): bool
    {
        $user = auth()->user();

        if (! $user instanceof User || $user->isTicketOrganizer()) {
            return false;
        }

        if (! $user->isSuperAdmin()) {
            $event = $record->event;

            if (! $event || ! $event->canBeManagedBy($user)) {
                return false;
            }
        }

        return parent::canDelete($record);
    }

    public static function canDeleteAny(): bool
    {
        $user = auth()->user();

        if (! $user instanceof User || $user->isTicketOrganizer()) {
            return false;
        }

        return parent::canDeleteAny();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if (! $user instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isSuperAdmin()) {
            return $query;
        }

        if ($user->isTicketOrganizer()) {
            $assignedEventIds = $user->assignedTicketingEventIds();

            return $assignedEventIds->isEmpty()
                ? $query->whereRaw('1 = 0')
                : $query->whereIn('event_id', $assignedEventIds);
        }

        return $query->whereHas(
            'event',
            fn (Builder $eventQuery): Builder => $eventQuery->accessibleBy($user)
        );
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTicketOrders::route('/'),
            'create' => CreateTicketOrder::route('/create'),
            'edit' => EditTicketOrder::route('/{record}/edit'),
        ];
    }
}
