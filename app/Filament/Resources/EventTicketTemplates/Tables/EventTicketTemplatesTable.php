<?php

namespace App\Filament\Resources\EventTicketTemplates\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EventTicketTemplatesTable
{
    public static function configure(
        Table $table
    ): Table {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Template')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('event.name')
                    ->label('Event')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('ticketType.name')
                    ->label('Ticket Type')
                    ->placeholder('Event Default')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('width')
                    ->label('Width')
                    ->sortable(),

                TextColumn::make('height')
                    ->label('Height')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
