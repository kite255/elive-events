<?php

namespace App\Filament\Resources\BadgeTemplates\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BadgeTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Template Information')
                    ->description('Basic badge template details.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Template Name')
                            ->placeholder('Example: VIP Conference Badge')
                            ->required()
                            ->maxLength(255),

                        Select::make('event_id')
                            ->label('Event')
                            ->relationship('event', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->placeholder('Global template / select event'),

                        Select::make('category_id')
                            ->label('Attendee Category')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->placeholder('Optional category-specific template'),

                        Select::make('badge_type_id')
                            ->label('Badge Type')
                            ->relationship('badgeType', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->placeholder('Optional badge-type-specific template'),

                        Toggle::make('is_default')
                            ->label('Default Template')
                            ->helperText('Use this template as the default badge design.')
                            ->default(false),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->columnSpanFull(),

                Section::make('Images')
                    ->description('Upload the badge background and optional logo.')
                    ->schema([
                        FileUpload::make('background_image_path')
                            ->label('Badge Background Image')
                            ->image()
                            ->disk('public')
                            ->directory('badge-template-backgrounds')
                            ->visibility('public')
                            ->acceptedFileTypes([
                                'image/jpeg',
                                'image/png',
                                'image/webp',
                            ])
                            ->maxSize(5120)
                            ->previewable(true)
                            ->imagePreviewHeight('180')
                            ->downloadable()
                            ->openable()
                            ->preserveFilenames(false)
                            ->helperText('Upload PNG, JPG, or WEBP badge background. Recommended size: 420px × 620px.'),

                        FileUpload::make('logo_path')
                            ->label('Logo')
                            ->image()
                            ->disk('public')
                            ->directory('badge-template-logos')
                            ->visibility('public')
                            ->acceptedFileTypes([
                                'image/jpeg',
                                'image/png',
                                'image/webp',
                            ])
                            ->maxSize(2048)
                            ->previewable(true)
                            ->imagePreviewHeight('120')
                            ->downloadable()
                            ->openable()
                            ->preserveFilenames(false)
                            ->helperText('Optional logo for badge branding. PNG with transparent background is recommended.'),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->columnSpanFull()
                    ->collapsible(),

                Section::make('Badge Size')
                    ->description('Set the badge canvas size in pixels.')
                    ->schema([
                        TextInput::make('width')
                            ->label('Width')
                            ->numeric()
                            ->required()
                            ->minValue(100)
                            ->maxValue(2000)
                            ->default(420)
                            ->suffix('px'),

                        TextInput::make('height')
                            ->label('Height')
                            ->numeric()
                            ->required()
                            ->minValue(100)
                            ->maxValue(3000)
                            ->default(620)
                            ->suffix('px'),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->columnSpanFull(),

                Section::make('Brand Colors')
                    ->description('Default colors used when no custom background image is uploaded.')
                    ->schema([
                        ColorPicker::make('background_color')
                            ->label('Background Color')
                            ->required()
                            ->default('#F8FAFC'),

                        ColorPicker::make('header_color')
                            ->label('Header Color')
                            ->required()
                            ->default('#233F7E'),

                        ColorPicker::make('accent_color')
                            ->label('Accent Color')
                            ->required()
                            ->default('#F99A12'),

                        ColorPicker::make('text_color')
                            ->label('Text Color')
                            ->required()
                            ->default('#FFFFFF'),

                        ColorPicker::make('footer_color')
                            ->label('Footer Color')
                            ->required()
                            ->default('#0B1F3A'),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 3,
                    ])
                    ->columnSpanFull(),

                Section::make('Visible Badge Fields')
                    ->description('Choose which attendee details should appear on generated badges.')
                    ->schema([
                        Toggle::make('show_category')
                            ->label('Show Category')
                            ->default(true),

                        Toggle::make('show_badge_type')
                            ->label('Show Badge Type')
                            ->default(true),

                        Toggle::make('show_badge_number')
                            ->label('Show Badge Number')
                            ->default(true),

                        Toggle::make('show_organization')
                            ->label('Show Organization')
                            ->default(true),

                        Toggle::make('show_position')
                            ->label('Show Position')
                            ->default(true),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 3,
                    ])
                    ->columnSpanFull(),
            ]);
    }
}