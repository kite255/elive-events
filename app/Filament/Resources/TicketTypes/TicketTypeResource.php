<?php

namespace App\Filament\Resources\TicketTypes;

use App\Filament\Resources\TicketTypes\Pages\CreateTicketType;
use App\Filament\Resources\TicketTypes\Pages\EditTicketType;
use App\Filament\Resources\TicketTypes\Pages\ListTicketTypes;
use App\Filament\Resources\TicketTypes\Schemas\TicketTypeForm;
use App\Filament\Resources\TicketTypes\Tables\TicketTypesTable;
use App\Models\TicketType;
use App\Models\Event;
use App\Models\User;
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

    public static function authorizedEvents(?User $user, bool $write = false): Builder
    {
        $query = Event::query()->accessibleBy($user);
        if (! $user || $user->is_gate_staff) {
            return $query->whereRaw('1 = 0');
        }
        if ($user->isSuperAdmin()) {
            return $query;
        }
        if ($user->isTicketOrganizer()) {
            return $write ? $query->whereRaw('1 = 0') : $query->whereIn('events.id', $user->assignedTicketingEventIds());
        }

        return $query->where(function (Builder $scope) use ($user): void {
            $scope->whereIn('events.organization_id', $user->activeOrganizations()
                ->where(function (Builder $membership): void {
                    $membership->where('organization_user.is_owner', true)
                        ->orWhereIn('organization_user.role', [
                            User::ORGANIZATION_ROLE_OWNER,
                            User::ORGANIZATION_ROLE_ADMIN,
                        ]);
                })->select('organizations.id')->getQuery())
                ->orWhereIn('events.id', $user->eventManagerEvents()->select('events.id')->getQuery());
        });
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        if (! $user instanceof User || $user->is_gate_staff) {
            return false;
        }

        // Organizers may open an empty list before their first event assignment.
        // Record queries and workspace authorization remain assignment-scoped.
        return $user->isSuperAdmin()
            || $user->isTicketOrganizer()
            || static::authorizedEvents($user)->exists();
    }

    public static function canView(Model $record): bool
    {
        return $record instanceof TicketType && static::authorizedEvents(auth()->user())->whereKey($record->event_id)->exists();
    }

    public static function canCreate(): bool
    {
        return static::authorizedEvents(auth()->user(), true)->exists();
    }

    public static function canEdit(Model $record): bool
    {
        return $record instanceof TicketType && static::authorizedEvents(auth()->user(), true)->whereKey($record->event_id)->exists();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canEdit($record);
    }

    public static function canDeleteAny(): bool
    {
        return static::canCreate();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereIn('ticket_types.event_id', static::authorizedEvents(auth()->user())->select('events.id'));
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
