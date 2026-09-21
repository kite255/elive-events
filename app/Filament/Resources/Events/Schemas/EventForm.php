<?php

namespace App\Filament\Resources\Events\Schemas;

use App\Models\CommunicationTemplate;
use App\Services\EventPresetService;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
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
    private static function eventType(Get $get): ?string
    {
        $value = $get('event_type');

        return filled($value)
            ? (string) $value
            : null;
    }

    private static function advanced(Get $get): bool
    {
        return (bool) $get('show_advanced_features');
    }

    private static function showTicketing(Get $get): bool
    {
        return self::advanced($get)
            || EventPresetService::usesTicketing(
                self::eventType($get)
            );
    }

    private static function showRegistration(Get $get): bool
    {
        return self::advanced($get)
            || EventPresetService::usesRegistration(
                self::eventType($get)
            );
    }

    private static function showSessions(Get $get): bool
    {
        return self::advanced($get)
            || EventPresetService::usesSessions(
                self::eventType($get)
            );
    }

    private static function showProfessionalFields(Get $get): bool
    {
        return self::advanced($get)
            || EventPresetService::usesProfessionalFields(
                self::eventType($get)
            );
    }

    private static function showBadges(Get $get): bool
    {
        return self::advanced($get)
            || EventPresetService::usesBadges(
                self::eventType($get)
            );
    }

    private static function showGuestRsvp(Get $get): bool
    {
        return self::advanced($get)
            || EventPresetService::usesGuestRsvp(
                self::eventType($get)
            );
    }

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
                        'Create the event and select its type. eLive Events automatically shows the modules relevant to that event type.'
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
                                    $set(
                                        'registration_sms_template_id',
                                        null
                                    );

                                    $set(
                                        'ticket_delivery_email_template_id',
                                        null
                                    );

                                    $set(
                                        'ticket_delivery_sms_template_id',
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
                                'Used for operational numbering and badges. Example: LC26 gives ELV-LC26-000001.'
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
                                'The selected type controls recommended defaults and which setup modules are displayed.'
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
                                'General event venue. Individual event days and sessions may use different venues.'
                            )
                            ->maxLength(255),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Public Event Page')
                    ->description(
                        'Build the detailed page customers see before registering or buying tickets. Empty optional sections are hidden automatically.'
                    )
                    ->schema([
                        TextInput::make('public_theme')
                            ->label('Event Theme')
                            ->placeholder('Revive Us Again, Lord')
                            ->maxLength(255),

                        Section::make('Social Sharing')
                            ->description(
                                'Control how this event appears when its public link is shared on WhatsApp, Facebook and other social platforms.'
                            )
                            ->schema([
                                FileUpload::make('social_share_image_path')
                                    ->label('Social Share Image')
                                    ->disk('public')
                                    ->directory('events/social-share')
                                    ->image()
                                    ->imageEditor()
                                    ->maxSize(5120)
                                    ->helperText(
                                        'Recommended size: 1200 × 630 px. If empty, the event banner is used as fallback.'
                                    )
                                    ->columnSpanFull(),

                                TextInput::make('social_share_title')
                                    ->label('Share Title')
                                    ->maxLength(255)
                                    ->helperText(
                                        'Optional. Defaults to the event name.'
                                    )
                                    ->columnSpanFull(),

                                Textarea::make('social_share_description')
                                    ->label('Share Description')
                                    ->rows(3)
                                    ->maxLength(300)
                                    ->helperText(
                                        'Optional. Defaults to the event description.'
                                    )
                                    ->columnSpanFull(),
                            ])
                            ->collapsible()
                            ->columnSpanFull(),

                        TextInput::make('venue_address')
                            ->label('Full Venue Address')
                            ->maxLength(255),

                        TextInput::make('map_url')
                            ->label('Google Maps Link')
                            ->url()
                            ->maxLength(2048),

                        TextInput::make('ministry_years')
                            ->label('Years of Ministry / Experience')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(999),

                        TextInput::make('group_members_count')
                            ->label('Group Members')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(9999),

                        Repeater::make('public_highlights')
                            ->label('Additional Highlights')
                            ->schema([
                                TextInput::make('value')
                                    ->label('Value')
                                    ->placeholder('Live Band')
                                    ->required()
                                    ->maxLength(100),
                                TextInput::make('label')
                                    ->label('Label')
                                    ->placeholder('Experience')
                                    ->required()
                                    ->maxLength(100),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->reorderable()
                            ->collapsible()
                            ->cloneable()
                            ->addActionLabel('Add Highlight')
                            ->columnSpanFull(),

                        Repeater::make('public_gallery')
                            ->label('Photo Gallery')
                            ->schema([
                                FileUpload::make('image_path')
                                    ->label('Photo')
                                    ->disk('public')
                                    ->directory('events/gallery')
                                    ->image()
                                    ->imageEditor()
                                    ->maxSize(5120)
                                    ->required(),
                                TextInput::make('caption')
                                    ->label('Caption')
                                    ->maxLength(255),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->reorderable()
                            ->collapsible()
                            ->addActionLabel('Add Photo')
                            ->columnSpanFull(),

                        Repeater::make('public_speakers')
                            ->label('Speakers / Performers')
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                                TextInput::make('role')
                                    ->label('Role or Description')
                                    ->maxLength(255),
                                FileUpload::make('image_path')
                                    ->label('Photo')
                                    ->disk('public')
                                    ->directory('events/speakers')
                                    ->image()
                                    ->imageEditor()
                                    ->maxSize(4096),
                            ])
                            ->columns(3)
                            ->defaultItems(0)
                            ->reorderable()
                            ->collapsible()
                            ->addActionLabel('Add Speaker or Performer')
                            ->columnSpanFull(),

                        Repeater::make('public_faqs')
                            ->label('Frequently Asked Questions')
                            ->schema([
                                TextInput::make('question')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                                Textarea::make('answer')
                                    ->required()
                                    ->rows(3)
                                    ->columnSpanFull(),
                            ])
                            ->defaultItems(0)
                            ->reorderable()
                            ->collapsible()
                            ->cloneable()
                            ->addActionLabel('Add Question')
                            ->columnSpanFull(),

                        Textarea::make('dress_code')
                            ->label('Dress Code')
                            ->rows(3),

                        Textarea::make('seating_policy')
                            ->label('Seating Information')
                            ->rows(3),

                        Textarea::make('age_restriction')
                            ->label('Age Restrictions')
                            ->rows(3),

                        Textarea::make('ticket_policy')
                            ->label('Ticket and Entry Policy')
                            ->rows(3),

                        Textarea::make('refund_policy')
                            ->label('Cancellation / Refund Policy')
                            ->rows(3),

                        TextInput::make('organizer_contact_email')
                            ->label('Public Contact Email')
                            ->email()
                            ->maxLength(255),

                        TextInput::make('organizer_contact_phone')
                            ->label('Public Contact Phone')
                            ->tel()
                            ->maxLength(40),

                        TextInput::make('website_url')
                            ->label('Event Website')
                            ->url()
                            ->maxLength(2048),

                        TextInput::make('facebook_url')
                            ->label('Facebook Link')
                            ->url()
                            ->maxLength(2048),

                        TextInput::make('instagram_url')
                            ->label('Instagram Link')
                            ->url()
                            ->maxLength(2048),

                        TextInput::make('youtube_url')
                            ->label('YouTube Link')
                            ->url()
                            ->maxLength(2048),

                        TextInput::make('final_cta_title')
                            ->label('Final Call-to-Action Heading')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Textarea::make('final_cta_body')
                            ->label('Final Call-to-Action Message')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible(),

                /*
                |--------------------------------------------------------------------------
                | Event Profile
                |--------------------------------------------------------------------------
                */

                Section::make('Event Setup Profile')
                    ->description(
                        'The form adapts automatically to the selected event type.'
                    )
                    ->schema([
                        Placeholder::make(
                            'event_profile_information'
                        )
                            ->label('Recommended Setup')
                            ->content(
                                function (
                                    Get $get
                                ): string {
                                    return match (
                                        $get('event_type')
                                    ) {
                                        'concert' =>
                                            'Concert mode focuses on ticket sales, ticket types, payments, digital QR tickets, and ticket check-in. Public attendee registration is hidden by default.',

                                        'conference' =>
                                            'Conference mode focuses on registration, attendee information, badges, sessions, communication, payments, and attendance.',

                                        'seminar' =>
                                            'Seminar mode focuses on participant registration, communication, badges, and session attendance.',

                                        'workshop' =>
                                            'Workshop mode focuses on capacity, participant registration, sessions, payments where required, and attendance.',

                                        'training' =>
                                            'Training mode focuses on participant registration, professional information, sessions, attendance, payments, and badges.',

                                        'wedding',
                                        'send_off',
                                        'engagement',
                                        'birthday' =>
                                            'Guest-event mode focuses on guest registration, RSVP-style information, communication, and optional payment instructions.',

                                        'exhibition',
                                        'expo',
                                        'trade_fair' =>
                                            'Exhibition mode can combine public registration, professional attendee information, ticket sales, sessions, badges, and payments.',

                                        'festival',
                                        'cultural_event' =>
                                            'Festival mode focuses on ticketing and performances or activities. Registration can be enabled through Advanced Features when required.',

                                        'church_event',
                                        'community_event',
                                        'charity_event' =>
                                            'Community mode focuses on participant registration, multi-day attendance, programs or sessions, communication, and badges.',

                                        'bonanza',
                                        'sports_event',
                                        'tournament' =>
                                            'Sports mode focuses on participant registration, teams/categories, games or activities, badges, and attendance.',

                                        default =>
                                            'Select the event type to receive recommended defaults and a simplified setup form.',
                                    };
                                }
                            )
                            ->columnSpanFull(),

                        Toggle::make(
                            'show_advanced_features'
                        )
                            ->label(
                                'Show Advanced / Optional Features'
                            )
                            ->helperText(
                                'Temporarily reveal modules that are normally hidden for this event type.'
                            )
                            ->default(false)
                            ->live()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                    ])
                    ->collapsible(),

                /*
                |--------------------------------------------------------------------------
                | Schedule and Capacity
                |--------------------------------------------------------------------------
                */

                Section::make('Schedule and Capacity')
                    ->description(
                        'Set the overall event dates, capacity, and publishing status.'
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
                                'Leave empty when there is no strict overall capacity. Ticket types and sessions may have their own capacity limits.'
                            )
                            ->numeric()
                            ->minValue(1),

                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'draft' => 'Draft',
                                'active' => 'Active',
                                'completed' => 'Completed',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('draft')
                            ->required()
                            ->native(false)
                            ->helperText(
                                'Use Active when the event is ready for public operations.'
                            ),
                    ])
                    ->columns(2),

                /*
                |--------------------------------------------------------------------------
                | Ticket Sales
                |--------------------------------------------------------------------------
                */

                Section::make('Ticket Sales')
                    ->description(
                        'Configure public admission ticket sales, checkout limits, reservation time, and ticket sales dates.'
                    )
                    ->relationship('ticketSetting')
                    ->schema([
                        Toggle::make(
                            'ticket_sales_enabled'
                        )
                            ->label('Enable Ticket Sales')
                            ->helperText(
                                'Allow customers to purchase admission tickets.'
                            )
                            ->default(false)
                            ->live(),

                        Placeholder::make(
                            'ticket_types_information'
                        )
                            ->label('Ticket Types')
                            ->content(
                                'After saving the event, create ticket types such as VIP, VVIP, Regular, Student, Early Bird, Sponsor, or another admission category.'
                            )
                            ->visible(
                                fn (Get $get): bool =>
                                    (bool) $get(
                                        'ticket_sales_enabled'
                                    )
                            )
                            ->columnSpanFull(),

                        Select::make(
                            'reservation_minutes'
                        )
                            ->label(
                                'Ticket Reservation Time'
                            )
                            ->options([
                                5 => '5 minutes',
                                10 => '10 minutes',
                                15 => '15 minutes',
                                20 => '20 minutes',
                                30 => '30 minutes',
                            ])
                            ->default(15)
                            ->required(
                                fn (Get $get): bool =>
                                    (bool) $get(
                                        'ticket_sales_enabled'
                                    )
                            )
                            ->native(false)
                            ->visible(
                                fn (Get $get): bool =>
                                    (bool) $get(
                                        'ticket_sales_enabled'
                                    )
                            )
                            ->helperText(
                                'How long unpaid checkout temporarily reserves inventory.'
                            ),

                        TextInput::make(
                            'max_tickets_per_order'
                        )
                            ->label(
                                'Maximum Tickets Per Order'
                            )
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(100)
                            ->default(10)
                            ->required(
                                fn (Get $get): bool =>
                                    (bool) $get(
                                        'ticket_sales_enabled'
                                    )
                            )
                            ->visible(
                                fn (Get $get): bool =>
                                    (bool) $get(
                                        'ticket_sales_enabled'
                                    )
                            ),

                        Toggle::make(
                            'allow_guest_checkout'
                        )
                            ->label(
                                'Allow Guest Checkout'
                            )
                            ->helperText(
                                'Allow customers to purchase tickets without creating an account.'
                            )
                            ->default(true)
                            ->visible(
                                fn (Get $get): bool =>
                                    (bool) $get(
                                        'ticket_sales_enabled'
                                    )
                            ),

                        DateTimePicker::make(
                            'sales_start_at'
                        )
                            ->label('Ticket Sales Start')
                            ->seconds(false)
                            ->timezone(config('app.timezone'))
                            ->visible(
                                fn (Get $get): bool =>
                                    (bool) $get(
                                        'ticket_sales_enabled'
                                    )
                            )
                            ->helperText(
                                'Optional. Times are entered in Tanzania local time. Leave empty to allow ticket sales immediately.'
                            ),

                        DateTimePicker::make(
                            'sales_end_at'
                        )
                            ->label('Ticket Sales End')
                            ->seconds(false)
                            ->timezone(config('app.timezone'))
                            ->afterOrEqual(
                                'sales_start_at'
                            )
                            ->visible(
                                fn (Get $get): bool =>
                                    (bool) $get(
                                        'ticket_sales_enabled'
                                    )
                            )
                            ->helperText(
                                'Optional. Times are entered in Tanzania local time. Leave empty to keep sales open until manually disabled.'
                            ),

                        Placeholder::make(
                            'public_ticket_flow_information'
                        )
                            ->label('Public Ticket Flow')
                            ->content(
                                'Buyer selects ticket type → reservation → payment → payment verification → ticket issuance → My Tickets → secure QR ticket.'
                            )
                            ->visible(
                                fn (Get $get): bool =>
                                    (bool) $get(
                                        'ticket_sales_enabled'
                                    )
                            )
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->visible(
                        fn (Get $get): bool =>
                            self::showTicketing($get)
                    )
                    ->collapsible(),

                /*
                |--------------------------------------------------------------------------
                | Event Finance Settings
                |--------------------------------------------------------------------------
                */

                Section::make('Event Finance Settings')
                    ->description(
                        'Configure eLive commission and payment gateway charges for this event. Changes apply only to future paid orders.'
                    )
                    ->relationship('paymentSetting')
                    ->schema([
                        TextInput::make(
                            'platform_commission_rate'
                        )
                            ->label(
                                'eLive Commission Rate'
                            )
                            ->suffix('%')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->default(7)
                            ->required()
                            ->helperText(
                                'Percentage retained by eLive from successful sales. Historical paid orders keep their original frozen commission.'
                            ),

                        TextInput::make(
                            'gateway_fee_rate'
                        )
                            ->label(
                                'Gateway Fee Rate'
                            )
                            ->suffix('%')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->default(0)
                            ->required()
                            ->helperText(
                                'Payment gateway fee percentage used for finance reporting.'
                            ),

                        Select::make(
                            'gateway_fee_bearer'
                        )
                            ->label(
                                'Gateway Fee Bearer'
                            )
                            ->options([
                                'organizer' => 'Organizer',
                                'elive' => 'eLive',
                                'customer' => 'Customer',
                            ])
                            ->default('organizer')
                            ->required()
                            ->native(false)
                            ->helperText(
                                'Organizer: fee reduces organizer net payable. eLive or Customer: fee is tracked but does not reduce organizer net.'
                            ),

                        Placeholder::make(
                            'finance_snapshot_information'
                        )
                            ->label(
                                'Historical Financial Protection'
                            )
                            ->content(
                                'When an order is successfully paid, eLive freezes the commission rate, gateway fee, total charges, and organizer net amount on that order. Later rate changes affect future paid orders only.'
                            )
                            ->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->visible(
                        fn (Get $get): bool =>
                            self::showTicketing($get)
                            || self::showRegistration($get)
                    )
                    ->collapsible(),

                /*
                |--------------------------------------------------------------------------
                | Event Structure / Sessions
                |--------------------------------------------------------------------------
                */

                Section::make('Event Structure')
                    ->description(
                        'Configure multi-day events, sessions, activities, programs, games, workshops, or performances.'
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
                            ->native(false),

                        Toggle::make(
                            'registration_allow_day_selection'
                        )
                            ->label(
                                'Allow Attendees to Select Event Days'
                            )
                            ->default(true)
                            ->live()
                            ->visible(
                                fn (Get $get): bool =>
                                    $get('schedule_mode')
                                    === 'multi_day'
                            )
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
                            ->default(true)
                            ->live(),

                        Toggle::make(
                            'session_registration_enabled'
                        )
                            ->label(
                                'Allow Public Session Selection'
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
                            ->default(true)
                            ->visible(
                                fn (Get $get): bool =>
                                    (bool) $get(
                                        'sessions_enabled'
                                    )
                            ),
                    ])
                    ->columns(2)
                    ->visible(
                        fn (Get $get): bool =>
                            self::showSessions($get)
                    )
                    ->collapsible(),

                /*
                |--------------------------------------------------------------------------
                | Public Registration
                |--------------------------------------------------------------------------
                */

                Section::make('Public Registration')
                    ->description(
                        fn (Get $get): string =>
                            self::showGuestRsvp($get)
                                ? 'Configure guest registration and RSVP-related behavior.'
                                : 'Control public attendee registration for this event.'
                    )
                    ->schema([
                        Toggle::make(
                            'registration_is_open'
                        )
                            ->label('Registration Open')
                            ->default(false),

                        Toggle::make(
                            'registration_requires_approval'
                        )
                            ->label('Requires Approval')
                            ->default(false),

                        Toggle::make(
                            'registration_auto_generate_badge'
                        )
                            ->label('Auto-generate Badge')
                            ->default(true)
                            ->visible(
                                fn (Get $get): bool =>
                                    self::showBadges($get)
                            ),

                        Toggle::make(
                            'registration_waitlist_enabled'
                        )
                            ->label('Enable Waitlist')
                            ->default(false),

                        TextInput::make(
                            'registration_welcome_title'
                        )
                            ->label('Welcome Title')
                            ->maxLength(255)
                            ->placeholder(
                                fn (Get $get): string =>
                                    self::showGuestRsvp($get)
                                        ? 'RSVP for this event'
                                        : 'Register for this event'
                            ),

                        Textarea::make(
                            'registration_welcome_message'
                        )
                            ->label('Welcome Message')
                            ->rows(3)
                            ->placeholder(
                                'Complete the form below.'
                            )
                            ->columnSpanFull(),

                        Textarea::make(
                            'registration_success_message'
                        )
                            ->label('Success Message')
                            ->rows(3)
                            ->placeholder(
                                'Thank you. Your response has been received.'
                            )
                            ->columnSpanFull(),

                        Textarea::make(
                            'registration_waitlist_message'
                        )
                            ->label('Waitlist Message')
                            ->rows(3)
                            ->placeholder(
                                'This event is currently full. You have been added to the waitlist.'
                            )
                            ->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->visible(
                        fn (Get $get): bool =>
                            self::showRegistration($get)
                    )
                    ->collapsible(),

                /*
                |--------------------------------------------------------------------------
                | Registration Communication
                |--------------------------------------------------------------------------
                */

                Section::make(
                    'Registration Communication'
                )
                    ->description(
                        'Configure automatic confirmation messages for attendee or guest registration.'
                    )
                    ->schema([
                        Toggle::make(
                            'registration_sms_enabled'
                        )
                            ->label(
                                'Send Registration SMS'
                            )
                            ->default(false)
                            ->live()
                            ->afterStateUpdated(
                                function (
                                    bool $state,
                                    Set $set
                                ): void {
                                    if ($state) {
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

                        Toggle::make(
                            'registration_email_enabled'
                        )
                            ->label(
                                'Send Registration Email'
                            )
                            ->default(true)
                            ->live()
                            ->afterStateUpdated(
                                function (
                                    bool $state,
                                    Set $set
                                ): void {
                                    if ($state) {
                                        $set(
                                            'registration_show_email',
                                            true
                                        );

                                        $set(
                                            'registration_require_email',
                                            true
                                        );
                                    }
                                }
                            ),

                        Toggle::make(
                            'registration_whatsapp_enabled'
                        )
                            ->label(
                                'Send Registration WhatsApp'
                            )
                            ->default(false)
                            ->live()
                            ->afterStateUpdated(
                                function (
                                    bool $state,
                                    Set $set
                                ): void {
                                    if ($state) {
                                        $set(
                                            'registration_show_phone',
                                            true
                                        );

                                        $set(
                                            'registration_require_phone',
                                            true
                                        );
                                    }
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
                            ->native(false)
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
                            ),

                        Placeholder::make(
                            'registration_channels_information'
                        )
                            ->label('How it works')
                            ->content(
                                'eLive Events queues only the communication channels enabled for this event.'
                            )
                            ->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->visible(
                        fn (Get $get): bool =>
                            self::showRegistration($get)
                    )
                    ->collapsible(),

                /*
                |--------------------------------------------------------------------------
                | Ticket Delivery Communication
                |--------------------------------------------------------------------------
                */

                Section::make(
                    'Ticket Delivery Communication'
                )
                    ->description(
                        'Select the email and SMS templates used to deliver ticket access links after successful payment.'
                    )
                    ->schema([
                        Select::make(
                            'ticket_delivery_email_template_id'
                        )
                            ->label(
                                'Ticket Delivery Email Template'
                            )
                            ->placeholder(
                                'Use system default email template'
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
                                            CommunicationTemplate::CHANNEL_EMAIL
                                        )
                                        ->where(
                                            'key',
                                            CommunicationTemplate::KEY_TICKET_DELIVERY_EMAIL
                                        )
                                        ->where(
                                            'is_active',
                                            true
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
                            ->native(false)
                            ->helperText(
                                'Leave empty to use the system default ticket-delivery email.'
                            ),

                        Select::make(
                            'ticket_delivery_sms_template_id'
                        )
                            ->label(
                                'Ticket Delivery SMS Template'
                            )
                            ->placeholder(
                                'Use system default SMS template'
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
                                            'key',
                                            CommunicationTemplate::KEY_TICKET_DELIVERY_SMS
                                        )
                                        ->where(
                                            'is_active',
                                            true
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
                            ->native(false)
                            ->helperText(
                                'Leave empty to use the system default ticket-delivery SMS.'
                            ),

                        Placeholder::make(
                            'ticket_delivery_channels_information'
                        )
                            ->label('Delivery priority')
                            ->content(
                                'WhatsApp is attempted first when available. Email is also sent when present, and SMS is used when WhatsApp is unavailable or permanently fails.'
                            )
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->visible(
                        fn (Get $get): bool =>
                            self::showTicketing($get)
                    )
                    ->collapsible(),

                /*
                |--------------------------------------------------------------------------
                | Registration Fields
                |--------------------------------------------------------------------------
                */

                Section::make(
                    'Registration Fields'
                )
                    ->description(
                        'Choose the information collected from attendees or guests.'
                    )
                    ->schema([
                        Toggle::make(
                            'registration_show_phone'
                        )
                            ->label('Show Phone Number')
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

                                        $set(
                                            'registration_sms_enabled',
                                            false
                                        );

                                        $set(
                                            'registration_whatsapp_enabled',
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
                            ->label('Require Phone Number')
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
                            ->label('Show Email Address')
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

                                        $set(
                                            'registration_email_enabled',
                                            false
                                        );
                                    }
                                }
                            ),

                        Toggle::make(
                            'registration_require_email'
                        )
                            ->label('Require Email Address')
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
                            ->default(false)
                            ->live()
                            ->visible(
                                fn (Get $get): bool =>
                                    self::showProfessionalFields(
                                        $get
                                    )
                            )
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
                            ->default(false)
                            ->visible(
                                fn (Get $get): bool =>
                                    self::showProfessionalFields(
                                        $get
                                    )
                                    && (bool) $get(
                                        'registration_show_organization'
                                    )
                            ),

                        Toggle::make(
                            'registration_show_position'
                        )
                            ->label(
                                'Show Position / Title'
                            )
                            ->default(false)
                            ->live()
                            ->visible(
                                fn (Get $get): bool =>
                                    self::showProfessionalFields(
                                        $get
                                    )
                            )
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
                            ->default(false)
                            ->visible(
                                fn (Get $get): bool =>
                                    self::showProfessionalFields(
                                        $get
                                    )
                                    && (bool) $get(
                                        'registration_show_position'
                                    )
                            ),

                        Toggle::make(
                            'registration_show_category'
                        )
                            ->label(
                                'Show Attendee Category'
                            )
                            ->default(false)
                            ->live(),

                        Toggle::make(
                            'registration_require_category'
                        )
                            ->label(
                                'Require Attendee Category'
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
                            ->label('Show Badge Type')
                            ->default(false)
                            ->live()
                            ->visible(
                                fn (Get $get): bool =>
                                    self::showBadges($get)
                            )
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
                            ->label('Require Badge Type')
                            ->default(false)
                            ->visible(
                                fn (Get $get): bool =>
                                    self::showBadges($get)
                                    && (bool) $get(
                                        'registration_show_badge_type'
                                    )
                            ),
                    ])
                    ->columns(2)
                    ->visible(
                        fn (Get $get): bool =>
                            self::showRegistration($get)
                    )
                    ->collapsible(),

                /*
                |--------------------------------------------------------------------------
                | Registration Payments
                |--------------------------------------------------------------------------
                */

                Section::make(
                    'Registration Payment Settings'
                )
                    ->description(
                        'Configure payment for attendee registration. Ticket admission prices are configured separately in Ticket Types.'
                    )
                    ->relationship('paymentSetting')
                    ->schema([
                        Toggle::make(
                            'payments_enabled'
                        )
                            ->label(
                                'Enable Registration Payments'
                            )
                            ->default(false)
                            ->live(),

                        Placeholder::make(
                            'registration_ticket_payment_note'
                        )
                            ->label(
                                'Registration vs Ticket Payment'
                            )
                            ->content(
                                'Use this section for paid attendee registration. Ticketed admission uses Ticket Types and Ticket Orders.'
                            )
                            ->columnSpanFull(),

                        Select::make('currency')
                            ->label('Currency')
                            ->options([
                                'TZS' =>
                                    'TZS - Tanzanian Shilling',

                                'USD' =>
                                    'USD - US Dollar',
                            ])
                            ->default('TZS')
                            ->required(
                                fn (Get $get): bool =>
                                    (bool) $get(
                                        'payments_enabled'
                                    )
                            )
                            ->native(false)
                            ->visible(
                                fn (Get $get): bool =>
                                    (bool) $get(
                                        'payments_enabled'
                                    )
                            ),

                        TextInput::make(
                            'registration_fee'
                        )
                            ->label('Registration Fee')
                            ->prefix(
                                fn (Get $get): string =>
                                    (string) (
                                        $get('currency')
                                        ?: 'TZS'
                                    )
                            )
                            ->numeric()
                            ->minValue(0)
                            ->step(0.01)
                            ->default(0)
                            ->required(
                                fn (Get $get): bool =>
                                    (bool) $get(
                                        'payments_enabled'
                                    )
                            )
                            ->visible(
                                fn (Get $get): bool =>
                                    (bool) $get(
                                        'payments_enabled'
                                    )
                            ),


                        Toggle::make(
                            'payment_required_before_confirmation'
                        )
                            ->label(
                                'Require Payment Before Confirmation'
                            )
                            ->default(true)
                            ->visible(
                                fn (Get $get): bool =>
                                    (bool) $get(
                                        'payments_enabled'
                                    )
                            ),

                        Toggle::make(
                            'payment_required_before_badge'
                        )
                            ->label(
                                'Require Payment Before Badge Release'
                            )
                            ->default(true)
                            ->visible(
                                fn (Get $get): bool =>
                                    (bool) $get(
                                        'payments_enabled'
                                    )
                                    && self::showBadges(
                                        $get
                                    )
                            ),

                        Toggle::make(
                            'block_check_in_if_unpaid'
                        )
                            ->label(
                                'Block Check-in When Unpaid'
                            )
                            ->default(false)
                            ->visible(
                                fn (Get $get): bool =>
                                    (bool) $get(
                                        'payments_enabled'
                                    )
                            ),

                        Toggle::make(
                            'allow_manual_payment'
                        )
                            ->label(
                                'Allow Manual Payment Confirmation'
                            )
                            ->default(false)
                            ->visible(
                                fn (Get $get): bool =>
                                    (bool) $get(
                                        'payments_enabled'
                                    )
                            ),
                    ])
                    ->columns(2)
                    ->visible(
                        fn (Get $get): bool =>
                            self::showRegistration($get)
                    )
                    ->collapsible(),

                /*
                |--------------------------------------------------------------------------
                | Manual Payment Instructions
                |--------------------------------------------------------------------------
                */

                Section::make(
                    'Manual / Offline Payment Instructions'
                )
                    ->description(
                        'Optional instructions for mobile money, bank transfer, contributions, or other offline payments.'
                    )
                    ->schema([
                        TextInput::make(
                            'payment_method'
                        )
                            ->label('Payment Method')
                            ->placeholder(
                                'Example: Vodacom M-Pesa'
                            )
                            ->maxLength(255),

                        TextInput::make(
                            'payment_account_name'
                        )
                            ->label(
                                'Payment Account Name'
                            )
                            ->maxLength(255),

                        TextInput::make(
                            'payment_account_number'
                        )
                            ->label(
                                'Payment Account Number'
                            )
                            ->maxLength(255),

                        Textarea::make(
                            'payment_instructions'
                        )
                            ->label(
                                'Payment Instructions'
                            )
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->visible(
                        fn (Get $get): bool =>
                            self::showRegistration($get)
                            || self::showGuestRsvp($get)
                    )
                    ->collapsible()
                    ->collapsed(),

                /*
                |--------------------------------------------------------------------------
                | Event Media
                |--------------------------------------------------------------------------
                |
                | Available for every event type, including concerts.
                | We currently reuse the existing registration media columns
                | so this improvement does not require a database migration.
                |
                */

                Section::make('Event Media')
                    ->description(
                        'Upload the main public-facing images for this event. These images can be used on event listings, event details, ticket pages, and registration pages.'
                    )
                    ->schema([
                        FileUpload::make(
                            'registration_banner_image_path'
                        )
                            ->label('Event Cover Image')
                            ->disk('public')
                            ->directory(
                                'event-registration/banners'
                            )
                            ->image()
                            ->imageEditor()
                            ->imagePreviewHeight('220')
                            ->downloadable()
                            ->openable()
                            ->maxSize(4096)
                            ->helperText(
                                'Recommended: a wide 16:9 image such as 1600 × 900 px. Used as the main event or ticket-page cover image.'
                            )
                            ->columnSpanFull(),

                        FileUpload::make(
                            'registration_logo_path'
                        )
                            ->label('Event Logo')
                            ->disk('public')
                            ->directory(
                                'event-registration/logos'
                            )
                            ->image()
                            ->imageEditor()
                            ->imagePreviewHeight('140')
                            ->downloadable()
                            ->openable()
                            ->maxSize(2048)
                            ->helperText(
                                'Optional event-specific logo. PNG with a transparent background is recommended.'
                            ),
                    ])
                    ->columns(2)
                    ->collapsible(),

                /*
                |--------------------------------------------------------------------------
                | Registration Branding
                |--------------------------------------------------------------------------
                |
                | Registration-specific visual styling stays separate from
                | the event media shared by all event types.
                |
                */

                Section::make('Registration Branding')
                    ->description(
                        'Customize colors used on the public attendee or guest registration experience.'
                    )
                    ->schema([
                        ColorPicker::make(
                            'registration_primary_color'
                        )
                            ->label('Primary Color')
                            ->default('#161943'),

                        ColorPicker::make(
                            'registration_background_color'
                        )
                            ->label('Background Color')
                            ->default('#F8FAFC'),

                        ColorPicker::make(
                            'registration_button_color'
                        )
                            ->label('Button Color')
                            ->default('#161943'),
                    ])
                    ->columns(3)
                    ->visible(
                        fn (Get $get): bool =>
                            EventPresetService::usesRegistration(
                                self::eventType($get)
                            )
                            || self::advanced($get)
                    )
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}
