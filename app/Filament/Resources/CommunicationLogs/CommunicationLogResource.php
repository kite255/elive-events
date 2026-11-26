<?php

namespace App\Filament\Resources\CommunicationLogs;

use App\Filament\Resources\CommunicationLogs\Pages\ListCommunicationLogs;
use App\Filament\Resources\CommunicationLogs\Pages\ViewCommunicationLog;
use App\Filament\Resources\CommunicationLogs\Tables\CommunicationLogsTable;
use App\Models\CommunicationLog;
use App\Models\Event;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class CommunicationLogResource extends Resource
{
    protected static ?string $model =
        CommunicationLog::class;

    protected static string|BackedEnum|null $navigationIcon =
        'heroicon-o-chat-bubble-left-right';

    protected static string|UnitEnum|null $navigationGroup =
        'Communications';

    protected static ?string $navigationLabel =
        'Communication Logs';

    protected static ?string $modelLabel =
        'Communication Log';

    protected static ?string $pluralModelLabel =
        'Communication Logs';

    protected static ?int $navigationSort = 30;

    /*
    |--------------------------------------------------------------------------
    | Ticket Organizer Access
    |--------------------------------------------------------------------------
    */

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        if (! $user || $user->is_gate_staff) {
            return false;
        }

        if ($user->isTicketOrganizer()) {
            return false;
        }

        return parent::shouldRegisterNavigation();
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        if (! $user || $user->is_gate_staff) {
            return false;
        }

        if ($user->isTicketOrganizer()) {
            return false;
        }

        return ($user->isSuperAdmin() || Event::query()->accessibleBy($user)->get()
            ->contains(fn (Event $event): bool => $user->canManageEventCommunication($event)))
            && parent::canViewAny();
    }

    public static function canView(
        Model $record
    ): bool {
        $user = auth()->user();

        if (! $user || $user->is_gate_staff) {
            return false;
        }

        if ($user->isTicketOrganizer()) {
            return false;
        }

        return $record instanceof CommunicationLog
            && static::getEloquentQuery()->whereKey($record->getKey())->exists()
            && parent::canView($record);
    }

    /*
    |--------------------------------------------------------------------------
    | Form / Table
    |--------------------------------------------------------------------------
    */

    public static function form(
        Schema $schema
    ): Schema {
        return $schema->components([]);
    }

    public static function table(
        Table $table
    ): Table {
        return CommunicationLogsTable::configure(
            $table
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Query
    |--------------------------------------------------------------------------
    */

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with([
                'event.organization',
                'attendee',
                'campaign',
            ]);

        $user = auth()->user();

        if (! $user || $user->is_gate_staff) {
            return $query->whereRaw(
                '1 = 0'
            );
        }

        if ($user->isSuperAdmin()) {
            return $query;
        }

        if ($user->isTicketOrganizer()) {
            return $query->whereRaw('1 = 0');
        }

        $eventIds = Event::query()->accessibleBy($user)->get()
            ->filter(fn (Event $event): bool => $user->canManageEventCommunication($event))
            ->pluck('id');

        return $query->whereIn('event_id', $eventIds);
    }

    /*
    |--------------------------------------------------------------------------
    | Permissions
    |--------------------------------------------------------------------------
    */

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(
        Model $record
    ): bool {
        return false;
    }

    public static function canDelete(
        Model $record
    ): bool {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function canForceDelete(
        Model $record
    ): bool {
        return false;
    }

    public static function canForceDeleteAny(): bool
    {
        return false;
    }

    public static function canRestore(
        Model $record
    ): bool {
        return false;
    }

    public static function canRestoreAny(): bool
    {
        return false;
    }

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    */

    public static function getPages(): array
    {
        return [
            'index' =>
                ListCommunicationLogs::route(
                    '/'
                ),

            'view' =>
                ViewCommunicationLog::route(
                    '/{record}'
                ),
        ];
    }
}