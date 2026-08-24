<?php

/*
|--------------------------------------------------------------------------
| Event Communication Hero Fields
|--------------------------------------------------------------------------
|
| Add these components to the EventCommunication Filament form schema.
|
*/

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

Section::make('Hero Section')
    ->description(
        'Optional hero shown at the top of the public communication page.'
    )
    ->schema([
        Toggle::make('hero_enabled')
            ->label('Enable Hero Section')
            ->default(true)
            ->live(),

        FileUpload::make('hero_image_path')
            ->label('Hero Image')
            ->disk('public')
            ->directory('event-communications/heroes')
            ->image()
            ->imageEditor()
            ->imagePreviewHeight('180')
            ->downloadable()
            ->openable()
            ->maxSize(5120)
            ->helperText(
                'Recommended: 1600×700px or wider. JPG, PNG or WEBP.'
            )
            ->visible(
                fn (Get $get): bool =>
                    (bool) $get('hero_enabled')
            )
            ->columnSpanFull(),

        TextInput::make('hero_title')
            ->label('Hero Title')
            ->placeholder("Today's Highlights")
            ->maxLength(255)
            ->visible(
                fn (Get $get): bool =>
                    (bool) $get('hero_enabled')
            ),

        TextInput::make('hero_subtitle')
            ->label('Hero Subtitle')
            ->placeholder('23 August 2026')
            ->maxLength(255)
            ->visible(
                fn (Get $get): bool =>
                    (bool) $get('hero_enabled')
            ),

        Toggle::make('hero_overlay_enabled')
            ->label('Dark Image Overlay')
            ->helperText(
                'Improves text readability on bright images.'
            )
            ->default(true)
            ->visible(
                fn (Get $get): bool =>
                    (bool) $get('hero_enabled')
            ),

        Select::make('hero_text_alignment')
            ->label('Text Alignment')
            ->options([
                'left' => 'Left',
                'center' => 'Center',
            ])
            ->default('left')
            ->native(false)
            ->visible(
                fn (Get $get): bool =>
                    (bool) $get('hero_enabled')
            ),

        Select::make('hero_height')
            ->label('Hero Height')
            ->options([
                'small' => 'Small',
                'medium' => 'Medium',
                'large' => 'Large',
            ])
            ->default('medium')
            ->native(false)
            ->visible(
                fn (Get $get): bool =>
                    (bool) $get('hero_enabled')
            ),
    ])
    ->columns(2)
    ->collapsible();
