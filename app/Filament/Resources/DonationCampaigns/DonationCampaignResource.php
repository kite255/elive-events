<?php

namespace App\Filament\Resources\DonationCampaigns;

use App\Filament\Resources\DonationCampaigns\Pages\CreateDonationCampaign;
use App\Filament\Resources\DonationCampaigns\Pages\EditDonationCampaign;
use App\Filament\Resources\DonationCampaigns\Pages\ListDonationCampaigns;
use App\Filament\Resources\DonationCampaigns\Schemas\DonationCampaignForm;
use App\Filament\Resources\DonationCampaigns\Tables\DonationCampaignsTable;
use App\Models\DonationCampaign;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class DonationCampaignResource extends Resource
{
    protected static ?string $model = DonationCampaign::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static string|UnitEnum|null $navigationGroup = 'Donations';

    protected static ?string $navigationLabel = 'Campaigns';

    protected static ?string $modelLabel = 'Donation Campaign';

    protected static ?string $pluralModelLabel = 'Donation Campaigns';

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return DonationCampaignForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DonationCampaignsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('organization')
            ->accessibleBy(auth()->user());
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->managedOrganizations()->exists();
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canView(Model $record): bool
    {
        return $record instanceof DonationCampaign
            && auth()->user() instanceof User
            && $record->canBeManagedBy(auth()->user());
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
            'index' => ListDonationCampaigns::route('/'),
            'create' => CreateDonationCampaign::route('/create'),
            'edit' => EditDonationCampaign::route('/{record}/edit'),
        ];
    }
}
