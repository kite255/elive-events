<?php

namespace App\Filament\Resources\BadgeTypes\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BadgeTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Badge Type Details')
                    ->description('Define badge types such as VIP Badge, Speaker Badge, Staff Badge, or Delegate Badge.')
                    ->schema([
                        Select::make('event_id')
                            ->label('Event')
                            ->relationship('event', 'name')
                            ->searchable()
                            ->required(),

                        TextInput::make('name')
                            ->label('Badge Type Name')
                            ->placeholder('VIP Badge, Delegate Badge, Staff Badge')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('width')
                            ->label('Badge Width')
                            ->numeric()
                            ->default(600)
                            ->minValue(100)
                            ->required(),

                        TextInput::make('height')
                            ->label('Badge Height')
                            ->numeric()
                            ->default(900)
                            ->minValue(100)
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Badge Design')
                    ->description('Upload a badge background. Advanced drag-and-drop badge designer will come later.')
                    ->schema([
                        FileUpload::make('background_path')
                            ->label('Badge Background')
                            ->image()
                            ->disk('public')
                            ->directory('badges/backgrounds')
                            ->imageEditor()
                            ->maxSize(4096),

                        TextInput::make('design_config')
                            ->label('Design Config')
                            ->disabled()
                            ->dehydrated(false)
                            ->helperText('This will be used later for the advanced badge designer.'),
                    ])
                    ->columns(2)
                    ->collapsible(),

                Section::make('Status')
                    ->schema([
                        Toggle::make('is_default')
                            ->label('Default Badge Type')
                            ->default(false),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }
}