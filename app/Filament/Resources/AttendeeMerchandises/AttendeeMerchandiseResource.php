<?php

namespace App\Filament\Resources\AttendeeMerchandises;

use App\Filament\Resources\AttendeeMerchandises\Pages\ListAttendeeMerchandises;
use App\Filament\Resources\AttendeeMerchandises\Tables\AttendeeMerchandisesTable;
use App\Models\AttendeeMerchandise;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class AttendeeMerchandiseResource extends Resource
{
    protected static ?string $model =
        AttendeeMerchandise::class;

    protected static string|BackedEnum|null $navigationIcon =
        Heroicon::OutlinedShoppingBag;

    protected static ?string $navigationLabel =
        'Attendee Merchandise';

    protected static ?string $modelLabel =
        'Attendee Merchandise';

    protected static ?string $pluralModelLabel =
        'Merchandise Orders';

    protected static string|UnitEnum|null $navigationGroup =
        'Merchandise';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute =
        'id';

    public static function table(Table $table): Table
    {
        return AttendeeMerchandisesTable::configure(
            $table
        );
    }

    private static function isRestrictedOrganizationOwner(): bool
    {
        $user = Auth::user();

        return $user && ! $user->isSuperAdmin()
            && ($user->ownedOrganizations()->exists()
                || $user->activeOrganizations()->wherePivot('role', \App\Models\User::ORGANIZATION_ROLE_OWNER)->exists());
    }

    public static function shouldRegisterNavigation(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->isTicketOrganizer() || static::isRestrictedOrganizationOwner()) {
            return false;
        }

        return parent::shouldRegisterNavigation();
    }

    public static function canViewAny(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->isTicketOrganizer()) {
            return false;
        }

        return parent::canViewAny();
    }

    public static function canCreate(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->isTicketOrganizer()) {
            return false;
        }

        return parent::canCreate();
    }

    public static function canEdit(
        $record
    ): bool {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->isTicketOrganizer()) {
            return false;
        }

        return parent::canEdit(
            $record
        );
    }

    public static function canDelete(
        $record
    ): bool {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->isTicketOrganizer()) {
            return false;
        }

        return parent::canDelete(
            $record
        );
    }

    public static function canDeleteAny(): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->isTicketOrganizer()) {
            return false;
        }

        return parent::canDeleteAny();
    }

    public static function getPages(): array
    {
        return [
            'index' =>
                ListAttendeeMerchandises::route('/'),
        ];
    }
}