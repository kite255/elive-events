<?php

namespace App\Filament\Resources\Events\RelationManagers;

use App\Models\EventDay;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class DaysRelationManager extends RelationManager
{
    protected static string $relationship = 'days';

    protected static ?string $title = 'Event Days';

    protected static ?string $modelLabel = 'Event Day';

    protected static ?string $pluralModelLabel = 'Event Days';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Day name')
                    ->placeholder('Day 1 – Opening Ceremony')
                    ->required()
                    ->maxLength(255),

                DatePicker::make('event_date')
                    ->label('Event date')
                    ->required()
                    ->native(false),

                TimePicker::make('starts_at')
                    ->label('Start time')
                    ->seconds(false)
                    ->native(false),

                TimePicker::make('ends_at')
                    ->label('End time')
                    ->seconds(false)
                    ->native(false)
                    ->after('starts_at'),

                TextInput::make('venue_name')
                    ->label('Venue')
                    ->placeholder('Main conference hall')
                    ->maxLength(255),

                TextInput::make('capacity')
                    ->label('Daily capacity')
                    ->helperText(
                        'Leave empty when this day has no separate capacity limit.'
                    )
                    ->numeric()
                    ->minValue(1),

                Select::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ])
                    ->default('active')
                    ->required()
                    ->native(false),

                Toggle::make('is_registration_open')
                    ->label('Allow registration for this day')
                    ->helperText(
                        'Attendees can select this day on the public registration form.'
                    )
                    ->default(true),

                TextInput::make('display_order')
                    ->label('Display order')
                    ->helperText(
                        'Lower numbers appear first on the registration form.'
                    )
                    ->numeric()
                    ->default(0)
                    ->minValue(0),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('event_date')
            ->columns([
                TextColumn::make('name')
                    ->label('Event day')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),

                TextColumn::make('event_date')
                    ->label('Date')
                    ->date('D, d M Y')
                    ->sortable(),

                TextColumn::make('starts_at')
                    ->label('Starts')
                    ->time('H:i')
                    ->placeholder('—'),

                TextColumn::make('ends_at')
                    ->label('Ends')
                    ->time('H:i')
                    ->placeholder('—'),

                TextColumn::make('venue_name')
                    ->label('Venue')
                    ->searchable()
                    ->placeholder('Main event venue')
                    ->toggleable(),

                TextColumn::make('expected_attendees')
                    ->label('Expected')
                    ->getStateUsing(
                        fn (EventDay $record): int => $record
                            ->attendees()
                            ->whereIn('attendees.status', [
                                'registered',
                                'confirmed',
                                'approved',
                                'checked_in',
                            ])
                            ->count()
                    )
                    ->badge(),

                TextColumn::make('pending_attendees')
                    ->label('Pending')
                    ->getStateUsing(
                        fn (EventDay $record): int => $record
                            ->attendees()
                            ->where(
                                'attendees.status',
                                'pending_approval'
                            )
                            ->count()
                    )
                    ->badge()
                    ->toggleable(),

                TextColumn::make('capacity')
                    ->label('Capacity')
                    ->numeric()
                    ->placeholder('Unlimited')
                    ->sortable(),

                TextColumn::make('remaining_capacity')
                    ->label('Remaining')
                    ->getStateUsing(function (EventDay $record): string|int {
                        if (
                            blank($record->capacity)
                            || (int) $record->capacity <= 0
                        ) {
                            return 'Unlimited';
                        }

                        $reserved = $record
                            ->attendees()
                            ->whereIn('attendees.status', [
                                'pending_approval',
                                'registered',
                                'confirmed',
                                'approved',
                                'checked_in',
                            ])
                            ->count();

                        return max(
                            0,
                            (int) $record->capacity - $reserved
                        );
                    })
                    ->badge(),

                IconColumn::make('is_registration_open')
                    ->label('Registration')
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-open')
                    ->falseIcon('heroicon-o-lock-closed'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string => match ($state) {
                            'active' => 'Active',
                            'inactive' => 'Inactive',
                            'completed' => 'Completed',
                            'cancelled' => 'Cancelled',
                            default => ucfirst($state ?: 'Unknown'),
                        }
                    )
                    ->sortable(),

                TextColumn::make('display_order')
                    ->label('Order')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'inactive' => 'Inactive',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),

                TernaryFilter::make('is_registration_open')
                    ->label('Registration open')
                    ->trueLabel('Open days')
                    ->falseLabel('Closed days')
                    ->native(false),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Event Day')
                    ->icon('heroicon-o-plus')
                    ->mutateDataUsing(function (array $data): array {
                        $data['display_order'] ??= 0;
                        $data['status'] ??= 'active';
                        $data['is_registration_open'] ??= true;

                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make(),

                DeleteAction::make()
                    ->requiresConfirmation(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('activate_selected')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Activate selected event days?')
                        ->modalDescription(
                            'The selected event days will become active.'
                        )
                        ->modalSubmitActionLabel('Activate')
                        ->action(function (Collection $records): void {
                            $records->each(
                                fn (EventDay $record) => $record->update([
                                    'status' => 'active',
                                ])
                            );
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('deactivate_selected')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Deactivate selected event days?')
                        ->modalDescription(
                            'The selected event days will no longer appear as active registration days.'
                        )
                        ->modalSubmitActionLabel('Deactivate')
                        ->action(function (Collection $records): void {
                            $records->each(
                                fn (EventDay $record) => $record->update([
                                    'status' => 'inactive',
                                    'is_registration_open' => false,
                                ])
                            );
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('open_registration_selected')
                        ->label('Open Registration')
                        ->icon('heroicon-o-lock-open')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Open registration for selected days?')
                        ->modalDescription(
                            'Attendees will be able to select these days on the public registration form.'
                        )
                        ->modalSubmitActionLabel('Open Registration')
                        ->action(function (Collection $records): void {
                            $records->each(
                                fn (EventDay $record) => $record->update([
                                    'status' => 'active',
                                    'is_registration_open' => true,
                                ])
                            );
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('close_registration_selected')
                        ->label('Close Registration')
                        ->icon('heroicon-o-lock-closed')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->modalHeading('Close registration for selected days?')
                        ->modalDescription(
                            'The selected days will remain in the system, but attendees will not be able to select them.'
                        )
                        ->modalSubmitActionLabel('Close Registration')
                        ->action(function (Collection $records): void {
                            $records->each(
                                fn (EventDay $record) => $record->update([
                                    'is_registration_open' => false,
                                ])
                            );
                        })
                        ->deselectRecordsAfterCompletion(),

                    DeleteBulkAction::make()
                        ->requiresConfirmation(),
                ]),
            ])
            ->modifyQueryUsing(
                fn (Builder $query): Builder => $query
                    ->orderBy('event_date')
                    ->orderBy('display_order')
                    ->orderBy('id')
            );
    }
}
