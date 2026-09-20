<?php

namespace App\Filament\Resources\Organizers;

use App\Filament\Resources\Organizers\Pages\CreateOrganizer;
use App\Filament\Resources\Organizers\Pages\EditOrganizer;
use App\Filament\Resources\Organizers\Pages\ListOrganizers;
use App\Filament\Resources\Organizers\Schemas\OrganizerForm;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class OrganizerResource extends Resource
{
    protected static ?string $model =
        User::class;

    protected static string|BackedEnum|null $navigationIcon =
        Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel =
        'Ticketing Managers';

    protected static ?string $modelLabel =
        'Ticketing Manager';

    protected static ?string $pluralModelLabel =
        'Ticketing Managers';

    protected static string|UnitEnum|null $navigationGroup =
        'Administration';

    protected static ?int $navigationSort = 20;

    /*
    |--------------------------------------------------------------------------
    | Access
    |--------------------------------------------------------------------------
    */

    public static function shouldRegisterNavigation(): bool
    {
        return static::isSuperAdmin();
    }

    public static function canViewAny(): bool
    {
        return static::isSuperAdmin();
    }

    public static function canCreate(): bool
    {
        return static::isSuperAdmin();
    }

    public static function canEdit(
        Model $record
    ): bool {
        return static::isSuperAdmin();
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

    /*
    |--------------------------------------------------------------------------
    | Query scoping
    |--------------------------------------------------------------------------
    */

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas(
                'organizations',
                fn (Builder $query): Builder =>
                    $query->where(
                        'organization_user.role',
                        User::ORGANIZATION_ROLE_TICKET_ORGANIZER
                    )
            );
    }

    /*
    |--------------------------------------------------------------------------
    | Form
    |--------------------------------------------------------------------------
    */

    public static function form(
        Schema $schema
    ): Schema {
        return OrganizerForm::configure(
            $schema
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Table
    |--------------------------------------------------------------------------
    */

    public static function table(
        Table $table
    ): Table {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Ticketing Manager')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable(),

                TextColumn::make(
                    'organizations.name'
                )
                    ->label('Organization')
                    ->listWithLineBreaks()
                    ->limitList(3),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort(
                'created_at',
                'desc'
            );
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
                ListOrganizers::route('/'),

            'create' =>
                CreateOrganizer::route(
                    '/create'
                ),

            'edit' =>
                EditOrganizer::route(
                    '/{record}/edit'
                ),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private static function isSuperAdmin(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->isSuperAdmin();
    }
}