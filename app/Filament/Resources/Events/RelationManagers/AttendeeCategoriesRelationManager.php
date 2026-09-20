<?php

namespace App\Filament\Resources\Events\RelationManagers;

use App\Models\BadgeType;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AttendeeCategoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'attendeeCategories';

    protected static ?string $title = 'Participant Types';

    protected static ?string $modelLabel = 'Participant Type';

    protected static ?string $pluralModelLabel = 'Participant Types';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Participant Type')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g. Church Member'),

                Select::make('group_name')
                    ->label('Category Group')
                    ->options([
                        'General Participants' => 'General Participants',
                        'Ministry / Leadership' => 'Ministry / Leadership',
                        'Special Access' => 'Special Access',
                        'Staff / Operations' => 'Staff / Operations',
                    ])
                    ->searchable()
                    ->native(false)
                    ->placeholder('Select group')
                    ->helperText(
                        'Used to organize participant types in the admin area.'
                    ),

                Select::make('badge_type_id')
                    ->label('Default Badge Type')
                    ->options(
                        fn (RelationManager $livewire): array =>
                            BadgeType::query()
                                ->where(
                                    'event_id',
                                    $livewire->getOwnerRecord()->getKey()
                                )
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all()
                    )
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->nullable()
                    ->helperText(
                        'This badge type will be automatically assigned when this participant type is selected.'
                    ),

                ColorPicker::make('color')
                    ->label('Category Color'),

                Toggle::make('is_public')
                    ->label('Publicly Selectable')
                    ->default(true)
                    ->helperText(
                        'Allow this participant type to appear on the public registration form.'
                    ),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->helperText(
                        'Inactive participant types remain in historical records but cannot be selected.'
                    ),

                TextInput::make('sort_order')
                    ->label('Display Order')
                    ->numeric()
                    ->default(0)
                    ->minValue(0),

                Textarea::make('description')
                    ->label('Description')
                    ->rows(3)
                    ->maxLength(1000)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->label('Participant Type')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('group_name')
                    ->label('Group')
                    ->placeholder('General Participants')
                    ->sortable(),

                TextColumn::make('badgeType.name')
                    ->label('Default Badge')
                    ->placeholder('Not assigned')
                    ->sortable(),

                IconColumn::make('is_public')
                    ->label('Public')
                    ->boolean(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Participant Type'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}