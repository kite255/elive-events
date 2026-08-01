<?php

namespace App\Filament\Resources\Events\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CheckInsRelationManager extends RelationManager
{
    protected static string $relationship = 'checkIns';

    protected static ?string $title = 'Check-ins';

    protected static ?string $modelLabel = 'Check-in';

    protected static ?string $pluralModelLabel = 'Check-ins';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Check-in Details')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('attendee.full_name')
                                    ->label('Attendee'),

                                \Filament\Infolists\Components\TextEntry::make('attendee.badge_number')
                                    ->label('Badge Number'),

                                \Filament\Infolists\Components\TextEntry::make('checkInPoint.name')
                                    ->label('Check-in Point')
                                    ->placeholder('No point selected'),

                                \Filament\Infolists\Components\TextEntry::make('method')
                                    ->label('Method')
                                    ->badge(),

                                \Filament\Infolists\Components\TextEntry::make('checked_in_at')
                                    ->label('Checked In At')
                                    ->dateTime(),

                                \Filament\Infolists\Components\TextEntry::make('checkedInBy.name')
                                    ->label('Checked In By')
                                    ->placeholder('System / QR'),

                                \Filament\Infolists\Components\TextEntry::make('device_name')
                                    ->label('Device')
                                    ->placeholder('No device'),

                                \Filament\Infolists\Components\TextEntry::make('ip_address')
                                    ->label('IP Address')
                                    ->placeholder('No IP'),

                                \Filament\Infolists\Components\TextEntry::make('note')
                                    ->label('Note')
                                    ->columnSpanFull()
                                    ->placeholder('No note'),
                            ]),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('attendee.full_name')
            ->columns([
                TextColumn::make('attendee.full_name')
                    ->label('Attendee')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('attendee.badge_number')
                    ->label('Badge No.')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('attendee.category.name')
                    ->label('Category')
                    ->badge()
                    ->toggleable(),

                TextColumn::make('attendee.badgeType.name')
                    ->label('Badge Type')
                    ->badge()
                    ->toggleable(),

                TextColumn::make('checkInPoint.name')
                    ->label('Point')
                    ->placeholder('No point')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('method')
                    ->label('Method')
                    ->badge()
                    ->colors([
                        'success' => 'qr',
                        'info' => 'manual',
                    ])
                    ->sortable(),

                TextColumn::make('checked_in_at')
                    ->label('Time')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('checkedInBy.name')
                    ->label('Staff')
                    ->placeholder('System / QR')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('device_name')
                    ->label('Device')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('ip_address')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('note')
                    ->label('Note')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('method')
                    ->options([
                        'qr' => 'QR Scan',
                        'manual' => 'Manual',
                    ]),

                SelectFilter::make('check_in_point_id')
                    ->label('Check-in Point')
                    ->relationship('checkInPoint', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('checked_in_at', 'desc');
    }
}
