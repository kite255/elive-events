<?php

namespace App\Filament\Resources\Events\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Event')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('organization.name')
                    ->label('Organization')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('event_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'conference' => 'Conference',
                        'seminar' => 'Seminar',
                        'workshop' => 'Workshop',
                        'wedding' => 'Wedding',
                        'church_event' => 'Church Event',
                        'graduation' => 'Graduation',
                        'corporate_event' => 'Corporate Event',
                        'exhibition' => 'Exhibition',
                        'training' => 'Training',
                        'vip_ceremony' => 'VIP Ceremony',
                        'other' => 'Other',
                        default => $state ? ucfirst(str_replace('_', ' ', $state)) : '-',
                    })
                    ->searchable()
                    ->sortable(),

                TextColumn::make('venue')
                    ->label('Venue')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('starts_at')
                    ->label('Starts')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('ends_at')
                    ->label('Ends')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('capacity')
                    ->label('Capacity')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('attendees_count')
                    ->label('Attendees')
                    ->counts('attendees')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray' => 'draft',
                        'success' => 'published',
                        'warning' => 'ongoing',
                        'info' => 'completed',
                        'danger' => 'cancelled',
                    ])
                    ->sortable(),

                TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

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
                SelectFilter::make('organization')
                    ->relationship('organization', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('event_type')
                    ->label('Event Type')
                    ->options([
                        'conference' => 'Conference',
                        'seminar' => 'Seminar',
                        'workshop' => 'Workshop',
                        'wedding' => 'Wedding',
                        'church_event' => 'Church Event',
                        'graduation' => 'Graduation',
                        'corporate_event' => 'Corporate Event',
                        'exhibition' => 'Exhibition',
                        'training' => 'Training',
                        'vip_ceremony' => 'VIP Ceremony',
                        'other' => 'Other',
                    ]),

                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                        'ongoing' => 'Ongoing',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
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
            ->defaultSort('starts_at', 'desc');
    }
}