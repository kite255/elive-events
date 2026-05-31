<?php

namespace App\Filament\Resources\Attendees\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AttendeesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->label('Attendee')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('event.name')
                    ->label('Event')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('category.name')
                    ->label('Category')
                    ->badge()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('badgeType.name')
                    ->label('Badge Type')
                    ->toggleable(),

                TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),

                TextColumn::make('organization_name')
                    ->label('Organization')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray' => 'registered',
                        'success' => 'confirmed',
                        'danger' => 'cancelled',
                        'info' => 'checked_in',
                    ])
                    ->sortable(),

                TextColumn::make('registration_source')
                    ->label('Source')
                    ->badge()
                    ->toggleable(),

                IconColumn::make('checked_in_at')
                    ->label('Checked In')
                    ->boolean()
                    ->getStateUsing(fn ($record): bool => filled($record->checked_in_at)),

                TextColumn::make('registered_at')
                    ->label('Registered')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('checked_in_at')
                    ->label('Checked In At')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('event')
                    ->relationship('event', 'name')
                    ->searchable(),

                SelectFilter::make('category')
                    ->relationship('category', 'name')
                    ->searchable(),

                SelectFilter::make('status')
                    ->options([
                        'registered' => 'Registered',
                        'confirmed' => 'Confirmed',
                        'cancelled' => 'Cancelled',
                        'checked_in' => 'Checked In',
                    ]),

                SelectFilter::make('registration_source')
                    ->label('Source')
                    ->options([
                        'manual' => 'Manual',
                        'public' => 'Public Form',
                        'import' => 'Excel Import',
                        'onsite' => 'Onsite',
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