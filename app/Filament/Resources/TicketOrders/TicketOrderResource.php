<?php

namespace App\Filament\Resources\TicketOrders;

use App\Filament\Resources\TicketOrders\Pages\CreateTicketOrder;
use App\Filament\Resources\TicketOrders\Pages\EditTicketOrder;
use App\Filament\Resources\TicketOrders\Pages\ListTicketOrders;
use App\Filament\Resources\TicketOrders\Schemas\TicketOrderForm;
use App\Filament\Resources\TicketOrders\Tables\TicketOrdersTable;
use App\Models\TicketOrder;
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
    protected static ?string $model =
        TicketOrder::class;

    protected static ?string $recordTitleAttribute =
        'order_number';

    protected static string|BackedEnum|null $navigationIcon =
        Heroicon::OutlinedShoppingCart;

    protected static string|UnitEnum|null $navigationGroup =
        'Ticketing';

    protected static ?string $navigationLabel =
        'Ticket Orders';

    protected static ?string $modelLabel =
        'Ticket Order';

    protected static ?string $pluralModelLabel =
        'Ticket Orders';

    protected static ?int $navigationSort = 20;

    public static function form(
        Schema $schema
    ): Schema {
        return TicketOrderForm::configure(
            $schema
        );
    }

    public static function table(
        Table $table
    ): Table {
        return TicketOrdersTable::configure(
            $table
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Authorization
    |--------------------------------------------------------------------------
    */

    public static function canCreate(): bool
    {
        $user = auth()->user();

        if ($user?->isTicketOrganizer()) {
            return false;
        }

        return parent::canCreate();
    }

    public static function canEdit(
        Model $record
    ): bool {
        $user = auth()->user();

        if ($user?->isTicketOrganizer()) {
            return false;
        }

        return parent::canEdit(
            $record
        );
    }

    public static function canDelete(
        Model $record
    ): bool {
        $user = auth()->user();

        if ($user?->isTicketOrganizer()) {
            return false;
        }

        return parent::canDelete(
            $record
        );
    }

    public static function canDeleteAny(): bool
    {
        $user = auth()->user();

        if ($user?->isTicketOrganizer()) {
            return false;
        }

        return parent::canDeleteAny();
    }

    /*
    |--------------------------------------------------------------------------
    | Query scoping
    |--------------------------------------------------------------------------
    */

    public static function getEloquentQuery(): Builder
    {
        $query =
            parent::getEloquentQuery();

        $user =
            auth()->user();

        if (! $user?->isTicketOrganizer()) {
            return $query;
        }

        $assignedEventIds =
            $user->assignedTicketingEventIds();

        if ($assignedEventIds->isEmpty()) {
            return $query->whereRaw(
                '1 = 0'
            );
        }

        return $query->whereIn(
            'event_id',
            $assignedEventIds
        );
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' =>
                ListTicketOrders::route('/'),

            'create' =>
                CreateTicketOrder::route(
                    '/create'
                ),

            'edit' =>
                EditTicketOrder::route(
                    '/{record}/edit'
                ),
        ];
    }
}