<?php

namespace App\Filament\Resources\OrganizationTicketTemplates\Schemas;

use App\Models\Organization;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrganizationTicketTemplateForm
{
    public static function configure(
        Schema $schema
    ): Schema {
        return $schema
            ->components([
                Section::make('Template Information')
                    ->schema([
                        Select::make('organization_id')
                            ->label('Organization')
                            ->options(
                                Organization::query()
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                            )
                            ->searchable()
                            ->preload()
                            ->required(),

                        TextInput::make('name')
                            ->label('Template Name')
                            ->required()
                            ->maxLength(150),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(4)
                            ->maxLength(2000)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Canvas')
                    ->schema([
                        TextInput::make('width')
                            ->label('Width')
                            ->numeric()
                            ->minValue(300)
                            ->maxValue(4000)
                            ->default(1080)
                            ->required(),

                        TextInput::make('height')
                            ->label('Height')
                            ->numeric()
                            ->minValue(300)
                            ->maxValue(4000)
                            ->default(1350)
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Availability')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),

                        Toggle::make('is_default')
                            ->label('Default Template')
                            ->default(false),
                    ])
                    ->columns(2),
            ]);
    }
}
