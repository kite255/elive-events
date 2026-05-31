<?php

namespace App\Filament\Resources\Attendees\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AttendeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Event and Badge')
                    ->schema([
                        Select::make('event_id')
                            ->label('Event')
                            ->relationship('event', 'name')
                            ->searchable()
                            ->required(),

                        Select::make('category_id')
                            ->label('Attendee Category')
                            ->relationship('category', 'name')
                            ->searchable(),

                        Select::make('badge_type_id')
                            ->label('Badge Type')
                            ->relationship('badgeType', 'name')
                            ->searchable(),
                    ])
                    ->columns(3),

                Section::make('Attendee Information')
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
                    ->columns(2),

                Section::make('Registration Status')
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
                            ->required(),

                        DateTimePicker::make('registered_at')
                            ->label('Registered At')
                            ->seconds(false),

                        DateTimePicker::make('checked_in_at')
                            ->label('Checked In At')
                            ->seconds(false),
                    ])
                    ->columns(2),

                Section::make('Badge Information')
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
                    ->columns(2)
                    ->collapsible(),
            ]);
    }
}