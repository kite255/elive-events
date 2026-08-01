<?php

namespace App\Filament\Resources\BadgeTemplateElements;

use App\Filament\Resources\BadgeTemplateElements\Pages\CreateBadgeTemplateElement;
use App\Filament\Resources\BadgeTemplateElements\Pages\EditBadgeTemplateElement;
use App\Filament\Resources\BadgeTemplateElements\Pages\ListBadgeTemplateElements;
use App\Filament\Resources\BadgeTemplateElements\Schemas\BadgeTemplateElementForm;
use App\Filament\Resources\BadgeTemplateElements\Tables\BadgeTemplateElementsTable;
use App\Models\BadgeTemplateElement;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class BadgeTemplateElementResource extends Resource
{
    protected static ?string $model = BadgeTemplateElement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $recordTitleAttribute = 'label';

    protected static ?string $navigationLabel = 'Badge Elements';

    protected static ?string $modelLabel = 'Badge Element';

    protected static ?string $pluralModelLabel = 'Badge Elements';

    protected static string|UnitEnum|null $navigationGroup = 'Badge Management';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return BadgeTemplateElementForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BadgeTemplateElementsTable::configure($table);
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
            'index' => ListBadgeTemplateElements::route('/'),
            'create' => CreateBadgeTemplateElement::route('/create'),
            'edit' => EditBadgeTemplateElement::route('/{record}/edit'),
        ];
    }
}