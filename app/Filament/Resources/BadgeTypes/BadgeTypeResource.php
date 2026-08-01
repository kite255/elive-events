<?php

namespace App\Filament\Resources\BadgeTypes;

use App\Filament\Resources\BadgeTypes\Pages\CreateBadgeType;
use App\Filament\Resources\BadgeTypes\Pages\EditBadgeType;
use App\Filament\Resources\BadgeTypes\Pages\ListBadgeTypes;
use App\Filament\Resources\BadgeTypes\Schemas\BadgeTypeForm;
use App\Filament\Resources\BadgeTypes\Tables\BadgeTypesTable;
use App\Models\BadgeType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class BadgeTypeResource extends Resource
{
    protected static ?string $model = BadgeType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Badge Types';

    protected static ?string $modelLabel = 'Badge Type';

    protected static ?string $pluralModelLabel = 'Badge Types';

    protected static string|UnitEnum|null $navigationGroup = 'Event Management';

    protected static ?int $navigationSort = 5;

    public static function form(Schema $schema): Schema
    {
        return BadgeTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BadgeTypesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBadgeTypes::route('/'),
            'create' => CreateBadgeType::route('/create'),
            'edit' => EditBadgeType::route('/{record}/edit'),
        ];
    }
}