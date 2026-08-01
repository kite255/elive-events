<?php

namespace App\Filament\Resources\AttendeeCategories;

use App\Filament\Resources\AttendeeCategories\Pages\CreateAttendeeCategory;
use App\Filament\Resources\AttendeeCategories\Pages\EditAttendeeCategory;
use App\Filament\Resources\AttendeeCategories\Pages\ListAttendeeCategories;
use App\Filament\Resources\AttendeeCategories\Schemas\AttendeeCategoryForm;
use App\Filament\Resources\AttendeeCategories\Tables\AttendeeCategoriesTable;
use App\Models\AttendeeCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AttendeeCategoryResource extends Resource
{
    protected static ?string $model = AttendeeCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Attendee Categories';

    protected static ?string $modelLabel = 'Attendee Category';

    protected static ?string $pluralModelLabel = 'Attendee Categories';

    protected static string|UnitEnum|null $navigationGroup = 'Event Management';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return AttendeeCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AttendeeCategoriesTable::configure($table);
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
            'index' => ListAttendeeCategories::route('/'),
            'create' => CreateAttendeeCategory::route('/create'),
            'edit' => EditAttendeeCategory::route('/{record}/edit'),
        ];
    }
}