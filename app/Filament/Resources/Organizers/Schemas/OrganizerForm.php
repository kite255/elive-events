<?php

namespace App\Filament\Resources\Organizers\Schemas;

use App\Models\Organization;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrganizerForm
{
    public static function configure(
        Schema $schema
    ): Schema {
        return $schema
            ->components([
                Section::make(
                    'Ticket Organizer'
                )
                    ->description(
                        'Create or assign a user as a Ticket Organizer.'
                    )
                    ->schema([
                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->maxLength(255),

                        TextInput::make('password')
                            ->label('Password')
                            ->password()
                            ->revealable()
                            ->helperText(
                                'Required for a new user. Leave blank when assigning an existing user.'
                            ),

                        Select::make('organization_id')
                            ->label('Organization')
                            ->options(
                                fn (): array =>
                                    Organization::query()
                                        ->orderBy('name')
                                        ->pluck(
                                            'name',
                                            'id'
                                        )
                                        ->all()
                            )
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('status')
                            ->label('Status')
                            ->options([
                                User::ORGANIZATION_STATUS_ACTIVE =>
                                    'Active',

                                User::ORGANIZATION_STATUS_INACTIVE =>
                                    'Inactive',

                                User::ORGANIZATION_STATUS_SUSPENDED =>
                                    'Suspended',
                            ])
                            ->default(
                                User::ORGANIZATION_STATUS_ACTIVE
                            )
                            ->required(),
                    ])
                    ->columns(2),
            ]);
    }
}
