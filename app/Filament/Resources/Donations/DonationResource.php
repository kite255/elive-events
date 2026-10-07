<?php

namespace App\Filament\Resources\Donations;

use App\Filament\Resources\Donations\Pages\ListDonations;
use App\Filament\Resources\Donations\Tables\DonationsTable;
use App\Models\Donation;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class DonationResource extends Resource
{
    protected static ?string $model = Donation::class;

    protected static string|BackedEnum|null $navigationIcon =
        Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup =
        'Donations';

    protected static ?string $navigationLabel =
        'Donations';

    protected static ?string $modelLabel =
        'Donation';

    protected static ?string $pluralModelLabel =
        'Donations';

    protected static ?int $navigationSort = 30;

    public static function table(Table $table): Table
    {
        return DonationsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'campaign.organization',
                'campaign.event',
                'manualSubmission.paymentMethod',
            ])
            ->accessibleBy(auth()->user());
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        return $user->isSuperAdmin()
            || $user->managedOrganizations()->exists()
            || $user->eventManagerEvents()->exists();
    }

    public static function canView(Model $record): bool
    {
        return $record instanceof Donation
            && auth()->user() instanceof User
            && $record->campaign?->canBeManagedBy(auth()->user());
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

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDonations::route('/'),
        ];
    }
}
