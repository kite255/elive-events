<?php

namespace App\Filament\Resources\BadgeTypes\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BadgeTypesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('background_path')
                    ->label('Background')
                    ->disk('public')
                    ->square(),

                TextColumn::make('event.name')
                    ->label('Event')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Badge Type')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('width')
                    ->label('Width')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('height')
                    ->label('Height')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('attendees_count')
                    ->label('Attendees')
                    ->counts('attendees')
                    ->sortable(),

                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('event')
                    ->relationship('event', 'name')
                    ->searchable(),

                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        1 => 'Active',
                        0 => 'Inactive',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}