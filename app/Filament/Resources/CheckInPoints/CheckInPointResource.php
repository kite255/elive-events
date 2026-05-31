<?php

namespace App\Filament\Resources\CheckInPoints;

use App\Filament\Resources\CheckInPoints\Pages\CreateCheckInPoint;
use App\Filament\Resources\CheckInPoints\Pages\EditCheckInPoint;
use App\Filament\Resources\CheckInPoints\Pages\ListCheckInPoints;
use App\Filament\Resources\CheckInPoints\Schemas\CheckInPointForm;
use App\Filament\Resources\CheckInPoints\Tables\CheckInPointsTable;
use App\Models\CheckInPoint;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CheckInPointResource extends Resource
{
    protected static ?string $model = CheckInPoint::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Check-in Points';

    protected static ?string $modelLabel = 'Check-in Point';

    protected static ?string $pluralModelLabel = 'Check-in Points';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return CheckInPointForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CheckInPointsTable::configure($table);
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
            'index' => ListCheckInPoints::route('/'),
            'create' => CreateCheckInPoint::route('/create'),
            'edit' => EditCheckInPoint::route('/{record}/edit'),
        ];
    }
}