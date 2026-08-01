<?php

namespace App\Filament\Resources\Events\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CheckInPointsRelationManager extends RelationManager
{
    protected static string $relationship = 'checkInPoints';

    protected static ?string $title = 'Check-in Points';

    protected static ?string $modelLabel = 'Check-in Point';

    protected static ?string $pluralModelLabel = 'Check-in Points';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Grid::make(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Point Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Main Gate, VIP Entrance, Registration Desk'),

                        TextInput::make('location')
                            ->label('Location')
                            ->maxLength(255)
                            ->placeholder('Main hall entrance, left side gate'),
                    ]),

                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Point Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('location')
                    ->label('Location')
                    ->searchable()
                    ->placeholder('No location'),

                TextColumn::make('check_ins_count')
                    ->label('Check-ins')
                    ->counts('checkIns')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Check-in Point')
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