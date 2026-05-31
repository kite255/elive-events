<?php

namespace App\Filament\Resources\CheckInPoints\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CheckInPointForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Check-in Point Details')
                    ->description('Create scanning points such as Main Gate, VIP Entrance, Media Desk, or Session Room A.')
                    ->schema([
                        Select::make('event_id')
                            ->label('Event')
                            ->relationship('event', 'name')
                            ->searchable()
                            ->required(),

                        TextInput::make('name')
                            ->label('Point Name')
                            ->placeholder('Main Gate, VIP Entrance, Media Desk')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('location')
                            ->label('Location')
                            ->placeholder('Main entrance, Hall A, Registration desk')
                            ->maxLength(255),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }
}