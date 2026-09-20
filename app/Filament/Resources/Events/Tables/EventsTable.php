<?php

namespace App\Filament\Resources\Events\Tables;

use App\Services\EventPresetService;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
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
                    ->weight('bold')
                    ->description(
                        fn ($record): ?string =>
                            filled($record->event_code)
                                ? 'Code: ' . $record->event_code
                                : null
                    ),

                TextColumn::make('organization.name')
                    ->label('Organization')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('event_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state, $record): string =>
                            EventPresetService::eventTypeLabel(
                                $state,
                                $record->custom_event_type
                            )
                    )
                    ->searchable()
                    ->sortable(),

                TextColumn::make('schedule_mode')
                    ->label('Structure')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string =>
                            $state === 'multi_day'
                                ? 'Multi-day'
                                : 'Single-day'
                    )
                    ->color(
                        fn (?string $state): string =>
                            $state === 'multi_day'
                                ? 'info'
                                : 'gray'
                    )
                    ->sortable(),

                TextColumn::make('days_count')
                    ->label('Days')
                    ->counts('days')
                    ->numeric()
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('sessions_count')
                    ->label('Sessions')
                    ->counts('sessions')
                    ->numeric()
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('attendees_count')
                    ->label('Registered')
                    ->counts('attendees')
                    ->numeric()
                    ->alignCenter()
                    ->sortable(),

                TextColumn::make('capacity')
                    ->label('Capacity')
                    ->formatStateUsing(
                        fn ($state): string =>
                            filled($state)
                                ? number_format((int) $state)
                                : 'Unlimited'
                    )
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('starts_at')
                    ->label('Starts')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string =>
                            match ($state) {
                                'draft' => 'Draft',
                                'active' => 'Active',
                                'completed' => 'Completed',
                                'cancelled' => 'Cancelled',
                                default =>
                                    $state
                                        ? ucfirst(
                                            str_replace(
                                                '_',
                                                ' ',
                                                $state
                                            )
                                        )
                                        : '-',
                            }
                    )
                    ->colors([
                        'gray' => 'draft',
                        'success' => 'active',
                        'info' => 'completed',
                        'danger' => 'cancelled',
                    ])
                    ->sortable(),

                TextColumn::make('venue')
                    ->label('Venue')
                    ->searchable()
                    ->toggleable(
                        isToggledHiddenByDefault: true
                    ),

                TextColumn::make('ends_at')
                    ->label('Ends')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(
                        isToggledHiddenByDefault: true
                    ),

                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->toggleable(
                        isToggledHiddenByDefault: true
                    ),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(
                        isToggledHiddenByDefault: true
                    ),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(
                        isToggledHiddenByDefault: true
                    ),
            ])
            ->filters([
                SelectFilter::make('organization')
                    ->relationship(
                        'organization',
                        'name'
                    )
                    ->searchable()
                    ->preload(),

                SelectFilter::make('event_type')
                    ->label('Event Type')
                    ->options(
                        EventPresetService::eventTypes()
                    ),

                SelectFilter::make('schedule_mode')
                    ->label('Schedule')
                    ->options([
                        'single_day' =>
                            'Single-day Event',
                        'multi_day' =>
                            'Multi-day Event',
                    ]),

                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'active' => 'Active',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),

                TernaryFilter::make(
                    'registration_is_open'
                )
                    ->label('Public Registration')
                    ->trueLabel('Registration Open')
                    ->falseLabel('Registration Closed')
                    ->placeholder('All Events'),

                TernaryFilter::make('sessions_enabled')
                    ->label('Sessions')
                    ->trueLabel('Sessions Enabled')
                    ->falseLabel('Sessions Disabled')
                    ->placeholder('All Events'),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Edit'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('starts_at', 'desc');
    }
}
