<?php

namespace App\Filament\Resources\Events\Schemas;

use App\Models\CommunicationTemplate;
use App\Services\EventPresetService;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class EventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                /*
                |--------------------------------------------------------------------------
                | Event Information
                |--------------------------------------------------------------------------
                */

                Section::make('Event Information')
                    ->description(
                        'Basic event details and organization ownership.'
                    )
                    ->schema([
                        Select::make('organization_id')
                            ->label('Organization')
                            ->relationship(
                                'organization',
                                'name'
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(
                                function (
                                    $state,
                                    Set $set
                                ): void {
                                    /*
                                     * A template from a previous organization
                                     * must never remain selected.
                                     */
                                    $set(
                                        'registration_sms_template_id',
                                        null
                                    );
                                }
                            ),

                        TextInput::make('name')
                            ->label('Event Name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(
                                function (
                                    string $operation,
                                    $state,
                                    callable $set
                                ): void {
                                    if (
                                        $operation === 'create'
                                        && filled($state)
                                    ) {
                                        $set(
                                            'slug',
                                            Str::slug($state)
                                        );
                                    }
                                }
                            ),

                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(
                                table: 'events',
                                column: 'slug',
                                ignoreRecord: true,
                                modifyRuleUsing: function (
                                    $rule,
                                    Get $get
                                ) {
                                    return $rule->where(
                                        'organization_id',
                                        $get('organization_id')
                                    );
                                }
                            )
                            ->helperText(
                                'Must be unique within the selected organization.'
                            ),

                        TextInput::make('event_code')
                            ->label('Event Code')
                            ->helperText(
                                'Used for badge numbers. Example: LC26 gives ELV-LC26-000001.'
                            )
                            ->placeholder('LC26')
                            ->maxLength(20)
                            ->unique(ignoreRecord: true)
                            ->alphaDash()
                            ->dehydrateStateUsing(
                                fn ($state) =>
                                    filled($state)
                                        ? strtoupper(
                                            (string) $state
                                        )
                                        : null
                            )
                            ->nullable(),

                        Select::make('event_type')
                            ->label('Event Type')
                            ->options(
                                EventPresetService::eventTypes()
                            )
                            ->searchable()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(
                                function (
                                    $state,
                                    Set $set
                                ): void {
                                    foreach (
                                        EventPresetService::preset(
                                            $state
                                        ) as $field => $value
                                    ) {
                                        $set(
                                            $field,
                                            $value
                                        );
                                    }

                                    if ($state !== 'other') {
                                        $set(
                                            'custom_event_type',
                                            null
                                        );
                                    }
                                }
                            )
                            ->helperText(
                                'Event type provides recommended registration defaults. You can still customize every setting below.'
                            ),

                        TextInput::make(
                            'custom_event_type'
                        )
                            ->label('Custom Event Type')
                            ->placeholder(
                                'Example: Medical Research Symposium'
                            )
                            ->maxLength(255)
                            ->required(
                                fn (Get $get): bool =>
                                    $get('event_type')
                                    === 'other'
                            )
                            ->visible(
                                fn (Get $get): bool =>
                                    $get('event_type')
                                    === 'other'
                            ),

                        TextInput::make('venue')
                            ->label('Main Venue')
                            ->helperText(
                                'General event venue. Individual event days and sessions can use different venues.'
                            )
                            ->maxLength(255),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                /*
                |--------------------------------------------------------------------------
                | Schedule and Capacity
                |--------------------------------------------------------------------------
                */

                Section::make('Schedule and Capacity')
                    ->description(
                        'Set the overall event period, capacity, and publishing status.'
                    )
                    ->schema([
                        DateTimePicker::make('starts_at')
                            ->label('Start Date & Time')
                            ->seconds(false),

                        DateTimePicker::make('ends_at')
                            ->label('End Date & Time')
                            ->seconds(false)
                            ->afterOrEqual('starts_at'),

                        TextInput::make('capacity')
                            ->label('Overall Capacity')
                            ->helperText(
                                'Leave empty if the event has no strict overall capacity limit. Event days and sessions may also have their own capacities.'
                            )
                            ->numeric()
                            ->minValue(1),

                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'draft' =>
                                    'Draft',

                                'active' =>
                                    'Active',

                                'completed' =>
                                    'Completed',

                                'cancelled' =>
                                    'Cancelled',
                            ])
                            ->helperText(
                                'Use Active when the event is ready for attendee registration and operations.'
                            )
                            ->default('draft')
                            ->required()
                            ->native(false),
                    ])
                    ->columns(2),

                /*
                |--------------------------------------------------------------------------
                | Event Structure
                |--------------------------------------------------------------------------
                */

                Section::make('Event Structure')
                    ->description(
                        'Define whether this is a simple event or a multi-day event with sessions and activities. Event days and sessions are configured after the event is created.'
                    )
                    ->schema([
                        Select::make('schedule_mode')
                            ->label('Schedule Mode')
                            ->options([
                                'single_day' =>
                                    'Single-day Event',

                                'multi_day' =>
                                    'Multi-day Event',
                            ])
                            ->default('single_day')
                            ->required()
                            ->live()
                            ->native(false)
                            ->helperText(
                                'Choose Multi-day for conferences, exhibitions, bonanzas, festivals, trainings, or events running across several days.'
                            ),

                        Toggle::make(
                            'registration_allow_day_selection'
                        )
                            ->label(
                                'Allow Attendees to Select Event Days'
                            )
                            ->helperText(
                                'When event days exist, attendees can choose which days they plan to attend.'
                            )
                            ->default(true)
                            ->visible(
                                fn (Get $get): bool =>
                                    $get('schedule_mode')
                                    === 'multi_day'
                            )
                            ->live()
                            ->afterStateUpdated(
                                function (
                                    bool $state,
                                    Set $set
                                ): void {
                                    if (! $state) {
                                        $set(
                                            'registration_allow_all_days',
                                            false
                                        );
                                    }
                                }
                            ),

                        Toggle::make(
                            'registration_allow_all_days'
                        )
                            ->label(
                                'Allow "All Event Days" Selection'
                            )
                            ->helperText(
                                'Adds a single option that registers the attendee for every available event day.'
                            )
                            ->default(true)
                            ->visible(
                                fn (Get $get): bool =>
                                    $get('schedule_mode')
                                        === 'multi_day'
                                    && (bool) $get(
                                        'registration_allow_day_selection'
                                    )
                            ),

                        Toggle::make('sessions_enabled')
                            ->label(
                                'Enable Sessions / Activities'
                            )
                            ->helperText(
                                'Use sessions for workshops, keynotes, panels, matches, games, performances, ceremonies, networking, and other activities.'
                            )
                            ->default(true),

                        Toggle::make(
                            'session_registration_enabled'
                        )
                            ->label(
                                'Allow Public Session Selection'
                            )
                            ->helperText(
                                'Attendees can select registration-required sessions on the public registration form.'
                            )
                            ->default(true)
                            ->visible(
                                fn (Get $get): bool =>
                                    (bool) $get(
                                        'sessions_enabled'
                                    )
                            ),

                        Toggle::make(
                            'session_check_in_enabled'
                        )
                            ->label(
                                'Enable Session-level Check-in'
                            )
                            ->helperText(
                                'Allows separate attendance tracking for individual workshops, keynotes, matches, and other activities.'
                            )
                            ->default(true)
                            ->visible(
                                fn (Get $get): bool =>
                                    (bool) $get(
                                        'sessions_enabled'
                                    )
                            ),
                    ])
                    ->columns(2),

                /*
                |--------------------------------------------------------------------------
                | Public Registration
                |--------------------------------------------------------------------------
                */

                Section::make('Public Registration')
                    ->description(
                        'Control whether attendees can register publicly for this event.'
                    )
                    ->schema([
                        Toggle::make(
                            'registration_is_open'
                        )
                            ->label('Registration Open')
                            ->helperText(
                                'Enable this to allow public registration for this event.'
                            )
                            ->default(false),

                        Toggle::make(
                            'registration_requires_approval'
                        )
                            ->label('Requires Approval')
                            ->helperText(
                                'If enabled, public registrations will be marked as pending approval.'
                            )
                            ->default(false),

                        Toggle::make(
                            'registration_auto_generate_badge'
                        )
                            ->label(
                                'Auto-generate Badge'
                            )
                            ->helperText(
                                'Automatically generate a badge after public registration when the attendee is registered.'
                            )
                            ->default(true),

                        Toggle::make(
                            'registration_waitlist_enabled'
                        )
                            ->label(
                                'Enable Waitlist'
                            )
                            ->helperText(
                                'Allow attendees to join the waitlist when the event reaches capacity.'
                            )
                            ->default(false),

                        TextInput::make(
                            'registration_welcome_title'
                        )
                            ->label(
                                'Welcome Title'
                            )
                            ->maxLength(255)
                            ->placeholder(
                                'Register for this event'
                            ),

                        Textarea::make(
                            'registration_welcome_message'
                        )
                            ->label(
                                'Welcome Message'
                            )
                            ->rows(3)
                            ->placeholder(
                                'Complete the form below to register for this event.'
                            )
                            ->columnSpanFull(),

                        Textarea::make(
                            'registration_success_message'
                        )
                            ->label(
                                'Success Message'
                            )
                            ->rows(3)
                            ->placeholder(
                                'Thank you. Your registration has been received.'
                            )
                            ->columnSpanFull(),

                        Textarea::make(
                            'registration_waitlist_message'
                        )
                            ->label(
                                'Waitlist Message'
                            )
                            ->rows(3)
                            ->placeholder(
                                'This event is currently full. You have been added to the waitlist.'
                            )
                            ->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->collapsible(),

                /*
                |--------------------------------------------------------------------------
                | Automatic Registration Communication
                |--------------------------------------------------------------------------
                */

                Section::make(
                    'Automatic Registration Communication'
                )
                    ->description(
                        'Automatically send an SMS when an attendee successfully completes registration.'
                    )
                    ->schema([
                        Toggle::make(
                            'registration_sms_enabled'
                        )
                            ->label(
                                'Send SMS After Registration'
                            )
                            ->helperText(
                                'When enabled, registered attendees automatically receive the selected SMS template.'
                            )
                            ->default(false)
                            ->live()
                            ->afterStateUpdated(
                                function (
                                    bool $state,
                                    Set $set
                                ): void {
                                    if ($state) {
                                        /*
                                         * SMS cannot work without a
                                         * phone number.
                                         */
                                        $set(
                                            'registration_show_phone',
                                            true
                                        );

                                        $set(
                                            'registration_require_phone',
                                            true
                                        );

                                        return;
                                    }

                                    $set(
                                        'registration_sms_template_id',
                                        null
                                    );
                                }
                            ),

                        Select::make(
                            'registration_sms_template_id'
                        )
                            ->label(
                                'Registration SMS Template'
                            )
                            ->placeholder(
                                'Select SMS template'
                            )
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

                                    return CommunicationTemplate::query()
                                        ->where(
                                            'organization_id',
                                            $organizationId
                                        )
                                        ->where(
                                            'channel',
                                            CommunicationTemplate::CHANNEL_SMS
                                        )
                                        ->where(
                                            'is_active',
                                            true
                                        )
                                        ->orderBy(
                                            'name'
                                        )
                                        ->pluck(
                                            'name',
                                            'id'
                                        )
                                        ->all();
                                }
                            )
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->live()
                            ->visible(
                                fn (Get $get): bool =>
                                    (bool) $get(
                                        'registration_sms_enabled'
                                    )
                            )
                            ->required(
                                fn (Get $get): bool =>
                                    (bool) $get(
                                        'registration_sms_enabled'
                                    )
                            )
                            ->helperText(
                                'Only active SMS templates belonging to this event organization are available.'
                            ),

                        \Filament\Forms\Components\Placeholder::make(
                            'registration_sms_information'
                        )
                            ->label(
                                'How it works'
                            )
                            ->content(
                                'After a successful public registration, eLive Events creates the attendee and badge first, then queues the selected SMS through the communications queue.'
                            )
                            ->visible(
                                fn (Get $get): bool =>
                                    (bool) $get(
                                        'registration_sms_enabled'
                                    )
                            )
                            ->columnSpanFull(),

                        \Filament\Forms\Components\Placeholder::make(
                            'registration_sms_status_information'
                        )
                            ->label(
                                'Registration Status'
                            )
                            ->content(
                                'For now, this confirmation SMS is sent only when the attendee status is Registered. Pending approval and waitlisted attendees will use separate automatic templates later.'
                            )
                            ->visible(
                                fn (Get $get): bool =>
                                    (bool) $get(
                                        'registration_sms_enabled'
                                    )
                            )
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible(),

                /*
                |--------------------------------------------------------------------------
                | Public Registration Fields
                |--------------------------------------------------------------------------
                */

                Section::make(
                    'Public Registration Fields'
                )
                    ->description(
                        'Choose which standard attendee fields appear on the public form and whether they are optional or required.'
                    )
                    ->schema([
                        Toggle::make(
                            'registration_show_phone'
                        )
                            ->label(
                                'Show Phone Number'
                            )
                            ->helperText(
                                'Display the phone number field in Personal Details.'
                            )
                            ->default(true)
                            ->live()
                            ->afterStateUpdated(
                                function (
                                    bool $state,
                                    Set $set
                                ): void {
                                    if (! $state) {
                                        $set(
                                            'registration_require_phone',
                                            false
                                        );

                                        /*
                                         * Automatic SMS cannot remain
                                         * enabled if the registration form
                                         * no longer collects a phone number.
                                         */
                                        $set(
                                            'registration_sms_enabled',
                                            false
                                        );

                                        $set(
                                            'registration_sms_template_id',
                                            null
                                        );
                                    }
                                }
                            ),

                        Toggle::make(
                            'registration_require_phone'
                        )
                            ->label(
                                'Require Phone Number'
                            )
                            ->helperText(
                                'Attendees must provide a phone number.'
                            )
                            ->default(true)
                            ->visible(
                                fn (Get $get): bool =>
                                    (bool) $get(
                                        'registration_show_phone'
                                    )
                            ),

                        Toggle::make(
                            'registration_show_email'
                        )
                            ->label(
                                'Show Email Address'
                            )
                            ->helperText(
                                'Display the email address field in Personal Details.'
                            )
                            ->default(true)
                            ->live()
                            ->afterStateUpdated(
                                function (
                                    bool $state,
                                    Set $set
                                ): void {
                                    if (! $state) {
                                        $set(
                                            'registration_require_email',
                                            false
                                        );
                                    }
                                }
                            ),

                        Toggle::make(
                            'registration_require_email'
                        )
                            ->label(
                                'Require Email Address'
                            )
                            ->helperText(
                                'Attendees must provide a valid email address.'
                            )
                            ->default(false)
                            ->visible(
                                fn (Get $get): bool =>
                                    (bool) $get(
                                        'registration_show_email'
                                    )
                            ),

                        Toggle::make(
                            'registration_show_organization'
                        )
                            ->label(
                                'Show Organization / Company'
                            )
                            ->helperText(
                                'Display Organization / Company in the event-specific registration details section.'
                            )
                            ->default(false)
                            ->live()
                            ->afterStateUpdated(
                                function (
                                    bool $state,
                                    Set $set
                                ): void {
                                    if (! $state) {
                                        $set(
                                            'registration_require_organization',
                                            false
                                        );
                                    }
                                }
                            ),

                        Toggle::make(
                            'registration_require_organization'
                        )
                            ->label(
                                'Require Organization / Company'
                            )
                            ->helperText(
                                'Attendees must provide their organization or company.'
                            )
                            ->default(false)
                            ->visible(
                                fn (Get $get): bool =>
                                    (bool) $get(
                                        'registration_show_organization'
                                    )
                            ),

                        Toggle::make(
                            'registration_show_position'
                        )
                            ->label(
                                'Show Position / Title'
                            )
                            ->helperText(
                                'Display Position / Title in the event-specific registration details section.'
                            )
                            ->default(false)
                            ->live()
                            ->afterStateUpdated(
                                function (
                                    bool $state,
                                    Set $set
                                ): void {
                                    if (! $state) {
                                        $set(
                                            'registration_require_position',
                                            false
                                        );
                                    }
                                }
                            ),

                        Toggle::make(
                            'registration_require_position'
                        )
                            ->label(
                                'Require Position / Title'
                            )
                            ->helperText(
                                'Attendees must provide their position or title.'
                            )
                            ->default(false)
                            ->visible(
                                fn (Get $get): bool =>
                                    (bool) $get(
                                        'registration_show_position'
                                    )
                            ),

                        Toggle::make(
                            'registration_show_category'
                        )
                            ->label(
                                'Show Attendee Category'
                            )
                            ->helperText(
                                'Allow attendees to select an event category.'
                            )
                            ->default(false)
                            ->live()
                            ->afterStateUpdated(
                                function (
                                    bool $state,
                                    Set $set
                                ): void {
                                    if (! $state) {
                                        $set(
                                            'registration_require_category',
                                            false
                                        );
                                    }
                                }
                            ),

                        Toggle::make(
                            'registration_require_category'
                        )
                            ->label(
                                'Require Attendee Category'
                            )
                            ->helperText(
                                'Attendees must select a category.'
                            )
                            ->default(false)
                            ->visible(
                                fn (Get $get): bool =>
                                    (bool) $get(
                                        'registration_show_category'
                                    )
                            ),

                        Toggle::make(
                            'registration_show_badge_type'
                        )
                            ->label(
                                'Show Badge Type'
                            )
                            ->helperText(
                                'Allow attendees to select an active badge type.'
                            )
                            ->default(false)
                            ->live()
                            ->afterStateUpdated(
                                function (
                                    bool $state,
                                    Set $set
                                ): void {
                                    if (! $state) {
                                        $set(
                                            'registration_require_badge_type',
                                            false
                                        );
                                    }
                                }
                            ),

                        Toggle::make(
                            'registration_require_badge_type'
                        )
                            ->label(
                                'Require Badge Type'
                            )
                            ->helperText(
                                'Attendees must select a badge type.'
                            )
                            ->default(false)
                            ->visible(
                                fn (Get $get): bool =>
                                    (bool) $get(
                                        'registration_show_badge_type'
                                    )
                            ),
                    ])
                    ->columns(2)
                    ->collapsible(),

                /*
                |--------------------------------------------------------------------------
                | Registration Branding
                |--------------------------------------------------------------------------
                */

                Section::make(
                    'Registration Branding'
                )
                    ->description(
                        'Optional event-level branding for the public registration page. If left empty, organization branding or eLive defaults will be used.'
                    )
                    ->schema([
                        FileUpload::make(
                            'registration_logo_path'
                        )
                            ->label(
                                'Registration Logo'
                            )
                            ->disk('public')
                            ->directory(
                                'event-registration/logos'
                            )
                            ->image()
                            ->imageEditor()
                            ->imagePreviewHeight(
                                '120'
                            )
                            ->downloadable()
                            ->openable()
                            ->maxSize(2048)
                            ->helperText(
                                'Optional event-specific logo shown on the public registration page.'
                            ),

                        FileUpload::make(
                            'registration_banner_image_path'
                        )
                            ->label(
                                'Banner Image'
                            )
                            ->disk('public')
                            ->directory(
                                'event-registration/banners'
                            )
                            ->image()
                            ->imageEditor()
                            ->imagePreviewHeight(
                                '180'
                            )
                            ->downloadable()
                            ->openable()
                            ->maxSize(4096)
                            ->helperText(
                                'Optional banner image shown at the top of the registration page.'
                            ),

                        ColorPicker::make(
                            'registration_primary_color'
                        )
                            ->label(
                                'Primary Color'
                            )
                            ->default(
                                '#233F7E'
                            ),

                        ColorPicker::make(
                            'registration_background_color'
                        )
                            ->label(
                                'Background Color'
                            )
                            ->default(
                                '#F8FAFC'
                            ),

                        ColorPicker::make(
                            'registration_button_color'
                        )
                            ->label(
                                'Button Color'
                            )
                            ->default(
                                '#233F7E'
                            ),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}