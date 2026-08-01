<?php

namespace App\Filament\Resources\Events\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BadgeTypesRelationManager extends RelationManager
{
    protected static string $relationship = 'badgeTypes';

    protected static ?string $title = 'Badge Types';

    protected static ?string $modelLabel = 'Badge Type';

    protected static ?string $pluralModelLabel = 'Badge Types';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                TextInput::make('code')
                    ->maxLength(50)
                    ->helperText('Example: VIP, DEL, STAFF'),

                ColorPicker::make('color')
                    ->default('#004e96'),

                TextInput::make('description')
                    ->maxLength(255),

                Grid::make(2)
                    ->schema([
                        Toggle::make('requires_payment')
                            ->label('Requires Payment')
                            ->default(false),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Badge Type')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('code')
                    ->label('Code')
                    ->badge()
                    ->searchable(),

                TextColumn::make('color')
                    ->label('Color')
                    ->badge(),

                IconColumn::make('requires_payment')
                    ->label('Payment')
                    ->boolean(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('attendees_count')
                    ->label('Attendees')
                    ->counts('attendees')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Badge Type')
                    ->mutateDataUsing(function (array $data): array {
                        $data['event_id'] = $this->getOwnerRecord()->id;

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
