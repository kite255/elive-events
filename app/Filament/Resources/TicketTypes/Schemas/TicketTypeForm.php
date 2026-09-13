<?php

namespace App\Filament\Resources\TicketTypes\Schemas;

use App\Models\Event;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TicketTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Ticket Information')
                    ->schema([
                        Select::make('event_id')
                            ->label('Event')
                            ->options(
                                Event::query()
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                            )
                            ->searchable()
                            ->preload()
                            ->required(),

                        TextInput::make('name')
                            ->label('Ticket Name')
                            ->placeholder('VIP')
                            ->required()
                            ->maxLength(150),

                        TextInput::make('code')
                            ->label('Ticket Code')
                            ->placeholder('VIP')
                            ->required()
                            ->maxLength(30)
                            ->dehydrateStateUsing(
                                fn (?string $state): ?string =>
                                    filled($state)
                                        ? strtoupper(trim($state))
                                        : null
                            ),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(4)
                            ->maxLength(2000)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Pricing')
                    ->schema([
                        TextInput::make('price')
                            ->label('Price')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required(),

                        Select::make('currency')
                            ->label('Currency')
                            ->options([
                                'TZS' => 'TZS',
                                'USD' => 'USD',
                            ])
                            ->default('TZS')
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Capacity & Order Limits')
                    ->schema([
                        TextInput::make('capacity')
                            ->label('Capacity')
                            ->numeric()
                            ->minValue(1)
                            ->helperText(
                                'Leave blank for unlimited capacity.'
                            ),

                        TextInput::make('min_per_order')
                            ->label('Minimum Per Order')
                            ->numeric()
                            ->minValue(1)
                            ->default(1)
                            ->required(),

                        TextInput::make('max_per_order')
                            ->label('Maximum Per Order')
                            ->numeric()
                            ->minValue(1)
                            ->default(10)
                            ->required(),

                        TextInput::make('sort_order')
                            ->label('Sort Order')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Sales Window')
                    ->schema([
                        DateTimePicker::make('sales_start_at')
                            ->label('Sales Start')
                            ->seconds(false),

                        DateTimePicker::make('sales_end_at')
                            ->label('Sales End')
                            ->seconds(false),
                    ])
                    ->columns(2),

                Section::make('Availability')
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),

                        Toggle::make('is_public')
                            ->label('Public')
                            ->helperText(
                                'Public ticket types appear on the ticket-buying page.'
                            )
                            ->default(true),

                        Toggle::make('requires_holder_details')
                            ->label('Require Holder Details')
                            ->helperText(
                                'Use this when each issued ticket needs individual holder information.'
                            )
                            ->default(false),
                    ])
                    ->columns(3),
            ]);
    }
}