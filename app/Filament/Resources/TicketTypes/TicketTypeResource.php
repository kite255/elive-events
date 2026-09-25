<?php

namespace App\Filament\Resources\TicketTypes;

use App\Filament\Resources\TicketTypes\Pages\CreateTicketType;
use App\Filament\Resources\TicketTypes\Pages\EditTicketType;
use App\Filament\Resources\TicketTypes\Pages\ListTicketTypes;
use App\Filament\Resources\TicketTypes\Schemas\TicketTypeForm;
use App\Filament\Resources\TicketTypes\Tables\TicketTypesTable;
use App\Models\TicketType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class TicketTypeResource extends Resource
{
    protected static ?string $model = TicketType::class;

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|BackedEnum|null $navigationIcon =
        Heroicon::OutlinedTicket;

    protected static string|UnitEnum|null $navigationGroup =
        'Ticketing';

    protected static ?string $navigationLabel =
        'Ticket Types';

    protected static ?string $modelLabel =
        'Ticket Type';

    protected static ?string $pluralModelLabel =
        'Ticket Types';

    protected static ?int $navigationSort = 80;

    public static function form(
        Schema $schema
    ): Schema {
        return TicketTypeForm::configure(
            $schema
        );
    }

    public static function table(
        Table $table
    ): Table {
        return TicketTypesTable::configure(
            $table
        );
    }

    public static function canCreate(): bool
    {
        $user = auth()->user();

        if ($user?->isTicketOrganizer()) {
            return false;
        }

        return parent::canCreate();
    }

    public static function canEdit(Model $record): bool
    {
        $user = auth()->user();

        if ($user?->isTicketOrganizer()) {
            return false;
        }

        return parent::canEdit($record);
    }

    public static function canDelete(Model $record): bool
    {
        $user = auth()->user();

        if ($user?->isTicketOrganizer()) {
            return false;
        }

        return parent::canDelete($record);
    }

    public static function canDeleteAny(): bool
    {
        $user = auth()->user();

        if ($user?->isTicketOrganizer()) {
            return false;
        }

        return parent::canDeleteAny();
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if (! $user?->isTicketOrganizer()) {
            return $query;
        }

        $assignedEventIds = $user->assignedTicketingEventIds();

        if ($assignedEventIds->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('event_id', $assignedEventIds);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTicketTypes::route('/'),
            'create' => CreateTicketType::route('/create'),
            'edit' => EditTicketType::route('/{record}/edit'),
        ];
    }
}
