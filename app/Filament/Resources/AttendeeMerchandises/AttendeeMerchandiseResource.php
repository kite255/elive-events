<?php

namespace App\Filament\Resources\AttendeeMerchandises;

use App\Filament\Resources\AttendeeMerchandises\Pages\ListAttendeeMerchandises;
use App\Filament\Resources\AttendeeMerchandises\Tables\AttendeeMerchandisesTable;
use App\Models\AttendeeMerchandise;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AttendeeMerchandiseResource extends Resource
{
    protected static ?string $model = AttendeeMerchandise::class;

    protected static string|BackedEnum|null $navigationIcon =
        Heroicon::OutlinedShoppingBag;

    protected static ?string $navigationLabel = 'Merchandise Orders';

    protected static ?string $modelLabel = 'Merchandise Order';

    protected static ?string $pluralModelLabel = 'Merchandise Orders';

    protected static string|UnitEnum|null $navigationGroup =
        'Event Management';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'id';

    public static function table(Table $table): Table
    {
        return AttendeeMerchandisesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttendeeMerchandises::route('/'),
        ];
    }
}
