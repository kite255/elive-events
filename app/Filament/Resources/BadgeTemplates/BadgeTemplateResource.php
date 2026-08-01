<?php

namespace App\Filament\Resources\BadgeTemplates;

use App\Filament\Resources\BadgeTemplates\Pages\CreateBadgeTemplate;
use App\Filament\Resources\BadgeTemplates\Pages\DesignBadgeTemplate;
use App\Filament\Resources\BadgeTemplates\Pages\EditBadgeTemplate;
use App\Filament\Resources\BadgeTemplates\Pages\ListBadgeTemplates;
use App\Filament\Resources\BadgeTemplates\Pages\PreviewBadgeTemplate;
use App\Filament\Resources\BadgeTemplates\Schemas\BadgeTemplateForm;
use App\Filament\Resources\BadgeTemplates\Tables\BadgeTemplatesTable;
use App\Models\BadgeTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class BadgeTemplateResource extends Resource
{
    protected static ?string $model = BadgeTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Badge Templates';

    protected static ?string $modelLabel = 'Badge Template';

    protected static ?string $pluralModelLabel = 'Badge Templates';

    protected static string|UnitEnum|null $navigationGroup = 'Badge Management';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return BadgeTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BadgeTemplatesTable::configure($table);
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
            'index' => ListBadgeTemplates::route('/'),
            'create' => CreateBadgeTemplate::route('/create'),
            'edit' => EditBadgeTemplate::route('/{record}/edit'),
            'preview' => PreviewBadgeTemplate::route('/{record}/preview'),
            'design' => DesignBadgeTemplate::route('/{record}/design'),
        ];
    }
}