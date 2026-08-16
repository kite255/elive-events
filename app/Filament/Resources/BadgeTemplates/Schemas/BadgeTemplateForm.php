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

                /*
                |--------------------------------------------------------------------------
                | Template Information
                |--------------------------------------------------------------------------
                */

                Section::make('Template Information')
                    ->description(
                        'Configure the badge template for this event.'
                    )
                    ->schema([
                        TextInput::make('name')
                            ->label('Template Name')
                            ->placeholder(
                                'Example: DCC Camp Meeting 2026 Badge'
                            )
                            ->required()
                            ->maxLength(255),

                        Select::make('event_id')
                            ->label('Event')
                            ->relationship('event', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->required()
                            ->helperText(
                                'Select the event that will use this badge.'
                            ),

                        Select::make('category_id')
                            ->label('Specific Attendee Category')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->placeholder('All categories')
                            ->helperText(
                                'Leave empty to use this template for all attendee categories.'
                            ),

                        Select::make('badge_type_id')
                            ->label('Specific Badge Type')
                            ->relationship('badgeType', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->placeholder('All badge types')
                            ->helperText(
                                'Leave empty unless this design is only for one badge type.'
                            ),

                        Toggle::make('is_default')
                            ->label('Default Template')
                            ->helperText(
                                'Use this as the default badge for the selected event.'
                            )
                            ->default(true),

                        Toggle::make('is_active')
                            ->label('Active')
                            ->helperText(
                                'Only active templates can be used for badge generation.'
                            )
                            ->default(true),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->columnSpanFull(),

                /*
                |--------------------------------------------------------------------------
                | Badge Background
                |--------------------------------------------------------------------------
                */

                Section::make('Badge Background')
                    ->description(
                        'Upload the finished badge artwork. Category, attendee name and QR code will be placed on top.'
                    )
                    ->schema([
                        FileUpload::make('background_image_path')
                            ->label('Background Image')
                            ->image()
                            ->disk('public')
                            ->directory('badge-template-backgrounds')
                            ->visibility('public')
                            ->acceptedFileTypes([
                                'image/jpeg',
                                'image/png',
                                'image/webp',
                            ])
                            ->maxSize(10240)
                            ->previewable(true)
                            ->imagePreviewHeight('300')
                            ->downloadable()
                            ->openable()
                            ->preserveFilenames(false)
                            ->required()
                            ->helperText(
                                'Upload the clean badge background without attendee category, attendee name or QR code. Recommended size: 1638 × 2048 px.'
                            ),
                    ])
                    ->columnSpanFull(),

                /*
                |--------------------------------------------------------------------------
                | Badge Size
                |--------------------------------------------------------------------------
                */

                Section::make('Badge Size')
                    ->description(
                        'The current Camp Meeting artwork uses a 1638 × 2048 pixel canvas.'
                    )
                    ->schema([
                        TextInput::make('width')
                            ->label('Width')
                            ->numeric()
                            ->required()
                            ->minValue(100)
                            ->maxValue(5000)
                            ->default(1638)
                            ->suffix('px'),

                        TextInput::make('height')
                            ->label('Height')
                            ->numeric()
                            ->required()
                            ->minValue(100)
                            ->maxValue(5000)
                            ->default(2048)
                            ->suffix('px'),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->columnSpanFull(),

                /*
                |--------------------------------------------------------------------------
                | Category
                |--------------------------------------------------------------------------
                */

                Section::make('Category')
                    ->description(
                        'Controls the attendee category text, for example MEDIA CREW, VIP, STAFF or DELEGATE.'
                    )
                    ->schema([
                        Toggle::make(
                            'design_config.category.visible'
                        )
                            ->label('Show Category')
                            ->default(true),

                        Toggle::make(
                            'design_config.category.uppercase'
                        )
                            ->label('Uppercase')
                            ->default(true),

                        TextInput::make(
                            'design_config.category.x'
                        )
                            ->label('Horizontal Center')
                            ->numeric()
                            ->required()
                            ->default(819)
                            ->suffix('px')
                            ->helperText(
                                '819 is the center of the 1638px badge.'
                            ),

                        TextInput::make(
                            'design_config.category.y'
                        )
                            ->label('Vertical Position')
                            ->numeric()
                            ->required()
                            ->default(1050)
                            ->suffix('px')
                            ->helperText(
                                'Places the category safely below the event heading.'
                            ),

                        TextInput::make(
                            'design_config.category.width'
                        )
                            ->label('Maximum Text Width')
                            ->numeric()
                            ->required()
                            ->default(1350)
                            ->suffix('px')
                            ->helperText(
                                'Long category names shrink automatically to fit.'
                            ),

                        TextInput::make(
                            'design_config.category.font_size'
                        )
                            ->label('Font Size')
                            ->numeric()
                            ->required()
                            ->default(105)
                            ->suffix('px'),

                        TextInput::make(
                            'design_config.category.min_font_size'
                        )
                            ->label('Minimum Font Size')
                            ->numeric()
                            ->required()
                            ->default(65)
                            ->suffix('px'),

                        Select::make(
                            'design_config.category.font_weight'
                        )
                            ->label('Font Weight')
                            ->options([
                                '400' => 'Regular',
                                '500' => 'Medium',
                                '600' => 'Semi Bold',
                                '700' => 'Bold',
                                '800' => 'Extra Bold',
                                '900' => 'Black',
                            ])
                            ->default('700')
                            ->native(false)
                            ->required(),

                        ColorPicker::make(
                            'design_config.category.color'
                        )
                            ->label('Text Color')
                            ->default('#FFFFFF')
                            ->required(),

                        Select::make(
                            'design_config.category.align'
                        )
                            ->label('Alignment')
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
                        'md' => 2,
                        'xl' => 3,
                    ])
                    ->columnSpanFull()
                    ->collapsible(),

                /*
                |--------------------------------------------------------------------------
                | Attendee Name
                |--------------------------------------------------------------------------
                */

                Section::make('Attendee Name')
                    ->description(
                        'Controls the attendee full name displayed below the category.'
                    )
                    ->schema([
                        Toggle::make(
                            'design_config.name.visible'
                        )
                            ->label('Show Attendee Name')
                            ->default(true),

                        Toggle::make(
                            'design_config.name.uppercase'
                        )
                            ->label('Uppercase')
                            ->default(true),

                        TextInput::make(
                            'design_config.name.x'
                        )
                            ->label('Horizontal Center')
                            ->numeric()
                            ->required()
                            ->default(819)
                            ->suffix('px'),

                        TextInput::make(
                            'design_config.name.y'
                        )
                            ->label('Vertical Position')
                            ->numeric()
                            ->required()
                            ->default(1235)
                            ->suffix('px')
                            ->helperText(
                                'Places the attendee name below the category with more breathing space.'
                            ),

                        TextInput::make(
                            'design_config.name.width'
                        )
                            ->label('Maximum Text Width')
                            ->numeric()
                            ->required()
                            ->default(1250)
                            ->suffix('px')
                            ->helperText(
                                'Long attendee names automatically shrink instead of being cut.'
                            ),

                        TextInput::make(
                            'design_config.name.font_size'
                        )
                            ->label('Font Size')
                            ->numeric()
                            ->required()
                            ->default(68)
                            ->suffix('px'),

                        TextInput::make(
                            'design_config.name.min_font_size'
                        )
                            ->label('Minimum Font Size')
                            ->numeric()
                            ->required()
                            ->default(42)
                            ->suffix('px'),

                        Select::make(
                            'design_config.name.font_weight'
                        )
                            ->label('Font Weight')
                            ->options([
                                '400' => 'Regular',
                                '500' => 'Medium',
                                '600' => 'Semi Bold',
                                '700' => 'Bold',
                                '800' => 'Extra Bold',
                                '900' => 'Black',
                            ])
                            ->default('500')
                            ->native(false)
                            ->required(),

                        ColorPicker::make(
                            'design_config.name.color'
                        )
                            ->label('Text Color')
                            ->default('#FFFFFF')
                            ->required(),

                        Select::make(
                            'design_config.name.align'
                        )
                            ->label('Alignment')
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
                        'md' => 2,
                        'xl' => 3,
                    ])
                    ->columnSpanFull()
                    ->collapsible(),

                /*
                |--------------------------------------------------------------------------
                | QR Code
                |--------------------------------------------------------------------------
                */

                Section::make('QR Code')
                    ->description(
                        'Controls the secure attendee QR code used for check-in.'
                    )
                    ->schema([
                        Toggle::make(
                            'design_config.qr_code.visible'
                        )
                            ->label('Show QR Code')
                            ->default(true),

                        TextInput::make(
                            'design_config.qr_code.x'
                        )
                            ->label('Horizontal Center')
                            ->numeric()
                            ->required()
                            ->default(819)
                            ->suffix('px')
                            ->helperText(
                                'The QR is centered around this horizontal position.'
                            ),

                        TextInput::make(
                            'design_config.qr_code.y'
                        )
                            ->label('Vertical Position')
                            ->numeric()
                            ->required()
                            ->default(1365)
                            ->suffix('px')
                            ->helperText(
                                'Places the QR below the attendee name.'
                            ),

                        TextInput::make(
                            'design_config.qr_code.size'
                        )
                            ->label('QR Size')
                            ->numeric()
                            ->required()
                            ->minValue(100)
                            ->maxValue(1000)
                            ->default(470)
                            ->suffix('px'),

                        TextInput::make(
                            'design_config.qr_code.padding'
                        )
                            ->label('White Padding')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(20)
                            ->suffix('px')
                            ->helperText(
                                'White space around the QR improves scanning reliability.'
                            ),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                        'xl' => 3,
                    ])
                    ->columnSpanFull()
                    ->collapsible(),

                /*
                |--------------------------------------------------------------------------
                | Legacy Compatibility
                |--------------------------------------------------------------------------
                */

                Toggle::make('show_category')
                    ->default(true)
                    ->hidden(),

                Toggle::make('show_badge_type')
                    ->default(false)
                    ->hidden(),

                Toggle::make('show_badge_number')
                    ->default(false)
                    ->hidden(),

                Toggle::make('show_organization')
                    ->default(false)
                    ->hidden(),

                Toggle::make('show_position')
                    ->default(false)
                    ->hidden(),

                /*
                |--------------------------------------------------------------------------
                | Legacy Colors
                |--------------------------------------------------------------------------
                */

                ColorPicker::make('background_color')
                    ->default('#FFFFFF')
                    ->hidden(),

                ColorPicker::make('header_color')
                    ->default('#161943')
                    ->hidden(),

                ColorPicker::make('accent_color')
                    ->default('#F99A12')
                    ->hidden(),

                ColorPicker::make('text_color')
                    ->default('#FFFFFF')
                    ->hidden(),

                ColorPicker::make('footer_color')
                    ->default('#0B1F3A')
                    ->hidden(),
            ]);
    }
}