<?php

namespace App\Filament\Resources\Attendees\Schemas;

use App\Services\PhoneNumberService;
use Closure;
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
                    ->description(
                        'Select the event, attendance days, attendee category, and badge type.'
                    )
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
                            ->default(
                                fn () =>
                                    request()->integer('event_id') ?: null
                            )
                            ->disabled(
                                fn () => request()->filled('event_id')
                            )
                            ->dehydrated()
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('eventDays', []);
                                $set('category_id', null);
                                $set('badge_type_id', null);
                            }),

                        Select::make('eventDays')
                            ->label('Attendance Days')
                            ->relationship(
                                name: 'eventDays',
                                titleAttribute: 'name',
                                modifyQueryUsing: function (
                                    Builder $query,
                                    Get $get
                                ): Builder {
                                    $eventId = $get('event_id')
                                        ?: request()->integer('event_id');

                                    if ($eventId) {
                                        $query
                                            ->where('event_id', $eventId)
                                            ->where('status', 'active')
                                            ->orderBy('display_order')
                                            ->orderBy('event_date')
                                            ->orderBy('id');
                                    } else {
                                        $query->whereRaw('1 = 0');
                                    }

                                    return $query;
                                }
                            )
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->placeholder('Select one or more event days')
                            ->optionsLimit(100)
                            ->disabled(
                                fn (Get $get): bool =>
                                    blank($get('event_id'))
                                    && ! request()->filled('event_id')
                            )
                            ->helperText(
                                'Select every day this attendee is expected to attend. Leave empty only when the event has no configured days.'
                            )
                            ->pivotData([
                                'selection_source' => 'admin',
                                'selected_at' => now(),
                            ]),

                        Select::make('category_id')
                            ->label('Attendee Category')
                            ->relationship(
                                name: 'category',
                                titleAttribute: 'name',
                                modifyQueryUsing: function (
                                    Builder $query,
                                    Get $get
                                ): Builder {
                                    $eventId = $get('event_id')
                                        ?: request()->integer('event_id');

                                    if ($eventId) {
                                        $query->where(
                                            'event_id',
                                            $eventId
                                        );
                                    }

                                    return $query;
                                }
                            )
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->placeholder('Select category')
                            ->optionsLimit(50)
                            ->disabled(
                                fn (Get $get): bool =>
                                    blank($get('event_id'))
                                    && ! request()->filled('event_id')
                            )
                            ->helperText(
                                'Only categories for the selected event will appear.'
                            ),

                        Select::make('badge_type_id')
                            ->label('Badge Type')
                            ->relationship(
                                name: 'badgeType',
                                titleAttribute: 'name',
                                modifyQueryUsing: function (
                                    Builder $query,
                                    Get $get
                                ): Builder {
                                    $eventId = $get('event_id')
                                        ?: request()->integer('event_id');

                                    if ($eventId) {
                                        $query
                                            ->where('event_id', $eventId)
                                            ->where('is_active', true);
                                    }

                                    return $query;
                                }
                            )
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->placeholder('Select badge type')
                            ->optionsLimit(50)
                            ->disabled(
                                fn (Get $get): bool =>
                                    blank($get('event_id'))
                                    && ! request()->filled('event_id')
                            )
                            ->helperText(
                                'Only active badge types for the selected event will appear.'
                            ),
                    ])
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->columnSpanFull(),

                Section::make('Attendee Information')
                    ->description(
                        'Basic attendee details used for badge generation, reports, and check-in.'
                    )
                    ->schema([
                        TextInput::make('full_name')
                            ->label('Full Name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('phone')
                            ->label('Phone Number')
                            ->tel()
                            ->placeholder('0650537539')
                            ->helperText(
                                'Accepted formats: 0650537539, +255650537539, or 255650537539.'
                            )
                            ->maxLength(20)
                            ->rules([
                                fn (): Closure =>
                                    function (
                                        string $attribute,
                                        mixed $value,
                                        Closure $fail
                                    ): void {
                                        if (blank($value)) {
                                            return;
                                        }

                                        $phoneService = app(
                                            PhoneNumberService::class
                                        );

                                        if (! $phoneService->isValid(
                                            (string) $value
                                        )) {
                                            $fail(
                                                'Enter a valid Tanzanian mobile number, for example 0650537539.'
                                            );
                                        }
                                    },
                            ])
                            ->dehydrateStateUsing(
                                function (
                                    ?string $state
                                ): ?string {
                                    if (blank($state)) {
                                        return null;
                                    }

                                    return app(
                                        PhoneNumberService::class
                                    )->normalize($state);
                                }
                            ),

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
                    ->description(
                        'Control how the attendee was registered and their attendance state.'
                    )
                    ->schema([
                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'registered' => 'Registered',
                                'pending_approval' =>
                                    'Pending Approval',
                                'waitlisted' => 'Waitlisted',
                                'confirmed' => 'Confirmed',
                                'approved' => 'Approved',
                                'cancelled' => 'Cancelled',
                                'rejected' => 'Rejected',
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
                    ->description(
                        'Badge number and generated badge file details.'
                    )
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