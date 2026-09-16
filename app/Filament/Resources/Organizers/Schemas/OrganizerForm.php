<?php

namespace App\Filament\Resources\Organizers\Schemas;

use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class OrganizerForm
{
    public static function configure(
        Schema $schema
    ): Schema {
        return $schema
            ->components([
                Section::make(
                    'Ticketing Manager'
                )
                    ->description(
                        'Create or assign a user as a Ticketing Manager and select the event(s) they can manage.'
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
                            ->live()
                            ->afterStateUpdated(
                                function (
                                    Set $set
                                ): void {
                                    $set(
                                        'assigned_event_ids',
                                        []
                                    );
                                }
                            )
                            ->required(),

                        Select::make('assigned_event_ids')
                            ->label('Assigned Events')
                            ->multiple()
                            ->options(
                                function (
                                    Get $get
                                ): array {
                                    $organizationId =
                                        $get(
                                            'organization_id'
                                        );

                                    if (
                                        blank(
                                            $organizationId
                                        )
                                    ) {
                                        return [];
                                    }

                                    return Event::query()
                                        ->where(
                                            'organization_id',
                                            $organizationId
                                        )
                                        ->orderBy('name')
                                        ->pluck(
                                            'name',
                                            'id'
                                        )
                                        ->all();
                                }
                            )
                            ->searchable()
                            ->preload()
                            ->disabled(
                                fn (
                                    Get $get
                                ): bool =>
                                    blank(
                                        $get(
                                            'organization_id'
                                        )
                                    )
                            )
                            ->helperText(
                                'Select one or more events this Ticketing Manager can access.'
                            )
                            ->rules([
                                function (
                                    Get $get
                                ) {
                                    return function (
                                        string $attribute,
                                        mixed $value,
                                        \Closure $fail
                                    ) use (
                                        $get
                                    ): void {
                                        $organizationId =
                                            (int) $get(
                                                'organization_id'
                                            );

                                        $eventIds =
                                            collect(
                                                $value ?? []
                                            )
                                                ->map(
                                                    fn (
                                                        $eventId
                                                    ): int =>
                                                        (int) $eventId
                                                )
                                                ->filter()
                                                ->unique()
                                                ->values();

                                        if (
                                            $eventIds->isEmpty()
                                        ) {
                                            return;
                                        }

                                        $validEventCount =
                                            Event::query()
                                                ->where(
                                                    'organization_id',
                                                    $organizationId
                                                )
                                                ->whereIn(
                                                    'id',
                                                    $eventIds
                                                )
                                                ->count();

                                        if (
                                            $validEventCount
                                            !==
                                            $eventIds->count()
                                        ) {
                                            $fail(
                                                'All assigned events must belong to the selected organization.'
                                            );
                                        }
                                    };
                                },
                            ]),

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