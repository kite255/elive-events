<?php

namespace App\Filament\Resources\CommunicationLogs;

use App\Filament\Resources\CommunicationLogs\Pages\ListCommunicationLogs;
use App\Filament\Resources\CommunicationLogs\Pages\ViewCommunicationLog;
use App\Filament\Resources\CommunicationLogs\Tables\CommunicationLogsTable;
use App\Models\CommunicationLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class CommunicationLogResource extends Resource
{
    protected static ?string $model = CommunicationLog::class;

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

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return CommunicationLogsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with([
                'event.organization',
                'attendee',
                'campaign',
            ]);

        $user = auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if (
            method_exists($user, 'hasRole')
            && $user->hasRole('super_admin')
        ) {
            return $query;
        }

        /*
        |--------------------------------------------------------------------------
        | Organization scoping
        |--------------------------------------------------------------------------
        |
        | Keep communication logs inside organizations accessible to the user.
        | This assumes the existing user <-> organizations relationship already
        | used elsewhere in the project.
        |
        */

        if (method_exists($user, 'organizations')) {
            $organizationIds = $user->organizations()
                ->pluck('organizations.id');

            return $query->whereHas(
                'event',
                fn (Builder $eventQuery) =>
                    $eventQuery->whereIn(
                        'organization_id',
                        $organizationIds
                    )
            );
        }

        return $query->whereRaw('1 = 0');
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

    public static function canForceDelete(Model $record): bool
    {
        return false;
    }

    public static function canForceDeleteAny(): bool
    {
        return false;
    }

    public static function canRestore(Model $record): bool
    {
        return false;
    }

    public static function canRestoreAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' =>
                ListCommunicationLogs::route('/'),

            'view' =>
                ViewCommunicationLog::route('/{record}'),
        ];
    }
}
