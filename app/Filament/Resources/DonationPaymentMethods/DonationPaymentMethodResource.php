<?php

namespace App\Filament\Resources\DonationPaymentMethods;

use App\Filament\Resources\DonationPaymentMethods\Pages\CreateDonationPaymentMethod;
use App\Filament\Resources\DonationPaymentMethods\Pages\EditDonationPaymentMethod;
use App\Filament\Resources\DonationPaymentMethods\Pages\ListDonationPaymentMethods;
use App\Filament\Resources\DonationPaymentMethods\Schemas\DonationPaymentMethodForm;
use App\Filament\Resources\DonationPaymentMethods\Tables\DonationPaymentMethodsTable;
use App\Models\DonationPaymentMethod;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class DonationPaymentMethodResource extends Resource
{
    protected static ?string $model = DonationPaymentMethod::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = 'Donations';

    protected static ?string $navigationLabel = 'Payment Methods';

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return DonationPaymentMethodForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DonationPaymentMethodsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('campaign')
            ->whereHas(
                'campaign',
                fn (Builder $query): Builder => $query->accessibleBy(auth()->user())
            );
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

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canView(Model $record): bool
    {
        return $record instanceof DonationPaymentMethod
            && auth()->user() instanceof User
            && $record->campaign?->canBeManagedBy(auth()->user());
    }

    public static function canEdit(Model $record): bool
    {
        return static::canView($record);
    }

    public static function canDelete(Model $record): bool
    {
        return static::canView($record);
    }

    public static function canDeleteAny(): bool
    {
        return static::canViewAny();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDonationPaymentMethods::route('/'),
            'create' => CreateDonationPaymentMethod::route('/create'),
            'edit' => EditDonationPaymentMethod::route('/{record}/edit'),
        ];
    }
}
