<?php

namespace App\Filament\Resources\BadgeTemplateElements\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BadgeTemplateElementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Element Information')
                    ->schema([
                        Select::make('badge_template_id')
                            ->label('Badge Template')
                            ->relationship('badgeTemplate', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->required(),

                        TextInput::make('label')
                            ->label('Element Label')
                            ->placeholder('Example: Full Name')
                            ->required()
                            ->maxLength(255),

                        Select::make('type')
                            ->label('Element Type')
                            ->options([
                                'text' => 'Text',
                                'image' => 'Image',
                                'qr' => 'QR Code',
                                'shape' => 'Shape',
                            ])
                            ->default('text')
                            ->native(false)
                            ->required(),

                        Select::make('field_key')
                            ->label('Dynamic Field')
                            ->options([
                                'event_name' => 'Event Name',
                                'full_name' => 'Full Name',
                                'category' => 'Category',
                                'badge_type' => 'Badge Type',
                                'badge_number' => 'Badge Number',
                                'organization_name' => 'Organization',
                                'position' => 'Position',
                                'qr_code' => 'QR Code',
                                'logo' => 'Logo',
                            ])
                            ->searchable()
                            ->native(false)
                            ->placeholder('Select dynamic field'),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->columnSpanFull(),

                Section::make('Position and Size')
                    ->schema([
                        TextInput::make('x')
                            ->label('X Position')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->suffix('px'),

                        TextInput::make('y')
                            ->label('Y Position')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->suffix('px'),

                        TextInput::make('width')
                            ->label('Width')
                            ->numeric()
                            ->required()
                            ->default(200)
                            ->suffix('px'),

                        TextInput::make('height')
                            ->label('Height')
                            ->numeric()
                            ->required()
                            ->default(40)
                            ->suffix('px'),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 4,
                    ])
                    ->columnSpanFull(),

                Section::make('Text Style')
                    ->schema([
                        TextInput::make('font_size')
                            ->label('Font Size')
                            ->numeric()
                            ->required()
                            ->default(16)
                            ->suffix('px'),

                        Select::make('font_weight')
                            ->label('Font Weight')
                            ->options([
                                '300' => 'Light',
                                '400' => 'Regular',
                                '600' => 'Semi Bold',
                                '700' => 'Bold',
                                '800' => 'Extra Bold',
                                '900' => 'Black',
                            ])
                            ->default('700')
                            ->native(false)
                            ->required(),

                        ColorPicker::make('color')
                            ->label('Text Color')
                            ->default('#0B1F3A')
                            ->required(),

                        ColorPicker::make('background_color')
                            ->label('Background Color')
                            ->nullable(),

                        Select::make('text_align')
                            ->label('Text Alignment')
                            ->options([
                                'left' => 'Left',
                                'center' => 'Center',
                                'right' => 'Right',
                            ])
                            ->default('center')
                            ->native(false)
                            ->required(),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 3,
                    ])
                    ->columnSpanFull(),

                Section::make('Visibility and Order')
                    ->schema([
                        Toggle::make('is_visible')
                            ->label('Visible')
                            ->default(true),

                        TextInput::make('sort_order')
                            ->label('Sort Order')
                            ->numeric()
                            ->required()
                            ->default(1),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->columnSpanFull(),
            ]);
    }
}