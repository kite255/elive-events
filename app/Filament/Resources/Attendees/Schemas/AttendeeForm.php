<?php

namespace App\Filament\Resources\Attendees\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class AttendeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Event and Badge')
                    ->description('Select the event, attendee category, and badge type for this attendee.')
                    ->schema([
                        Select::make('event_id')
                            ->label('Event')
                            ->relationship('event', 'name')
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->required()
                            ->placeholder('Select event')
                            ->optionsLimit(50)
                            ->default(fn () => request()->integer('event_id') ?: null)
                            ->disabled(fn () => request()->filled('event_id'))
                            ->dehydrated()
                            ->live()
                            ->afterStateUpdated(function (Set $set) {
                                $set('category_id', null);
                                $set('badge_type_id', null);
                            }),

                        Select::make('category_id')
                            ->label('Attendee Category')
                            ->relationship(
                                name: 'category',
                                titleAttribute: 'name',
                                modifyQueryUsing: function (Builder $query, Get $get) {
                                    $eventId = $get('event_id') ?: request()->integer('event_id');

                                    if ($eventId) {
                                        $query->where('event_id', $eventId);
                                    }

                                    return $query;
                                }
                            )
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->placeholder('Select category')
                            ->optionsLimit(50)
                            ->disabled(fn (Get $get) => blank($get('event_id')) && ! request()->filled('event_id'))
                            ->helperText('Only categories for the selected event will appear.'),

                        Select::make('badge_type_id')
                            ->label('Badge Type')
                            ->relationship(
                                name: 'badgeType',
                                titleAttribute: 'name',
                                modifyQueryUsing: function (Builder $query, Get $get) {
                                    $eventId = $get('event_id') ?: request()->integer('event_id');

                                    if ($eventId) {
                                        $query->where('event_id', $eventId);
                                    }

                                    return $query;
                                }
                            )
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->placeholder('Select badge type')
                            ->optionsLimit(50)
                            ->disabled(fn (Get $get) => blank($get('event_id')) && ! request()->filled('event_id'))
                            ->helperText('Only badge types for the selected event will appear.'),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 3,
                    ])
                    ->columnSpanFull(),

                Section::make('Attendee Information')
                    ->description('Basic attendee details used for badge generation, reports, and check-in.')
                    ->schema([
                        TextInput::make('full_name')
                            ->label('Full Name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('phone')
                            ->label('Phone Number')
                            ->tel()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label('Email Address')
                            ->email()
                            ->maxLength(255),

                        TextInput::make('organization_name')
                            ->label('Organization / Company')
                            ->maxLength(255),

                        TextInput::make('position')
                            ->label('Position / Title')
                            ->maxLength(255),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->columnSpanFull(),

                Section::make('Registration Status')
                    ->description('Control how the attendee was registered and their attendance state.')
                    ->schema([
                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'registered' => 'Registered',
                                'confirmed' => 'Confirmed',
                                'cancelled' => 'Cancelled',
                                'checked_in' => 'Checked In',
                            ])
                            ->default('registered')
                            ->native(false)
                            ->required(),

                        Select::make('registration_source')
                            ->label('Registration Source')
                            ->options([
                                'manual' => 'Manual',
                                'public' => 'Public Form',
                                'import' => 'Excel Import',
                                'onsite' => 'Onsite',
                            ])
                            ->default('manual')
                            ->native(false)
                            ->required(),

                        DateTimePicker::make('registered_at')
                            ->label('Registered At')
                            ->seconds(false)
                            ->default(now()),

                        DateTimePicker::make('checked_in_at')
                            ->label('Checked In At')
                            ->seconds(false),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->columnSpanFull(),

                Section::make('Badge Information')
                    ->description('Badge number and generated badge file details.')
                    ->schema([
                        TextInput::make('badge_number')
                            ->label('Badge Number')
                            ->maxLength(255),

                        TextInput::make('badge_path')
                            ->label('Badge File Path')
                            ->maxLength(255)
                            ->disabled()
                            ->dehydrated(),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->collapsible()
                    ->columnSpanFull(),
            ]);
    }
}