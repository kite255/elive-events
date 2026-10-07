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

    private static function showDonations(Get $get): bool
    {
        return self::advanced($get)
            || EventPresetService::usesDonations(
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
                        Toggle::make('show_on_elive_website')
                            ->label('Show on eLive Website')
                            ->helperText(
                                'When enabled, this event can appear in the public events section on elive.co.tz. Draft and cancelled events are never published there.'
                            )
                            ->default(false)
                            ->columnSpanFull(),

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

                                        'church_event' =>
                                            'Church mode focuses on registration, attendance days, programs or services, communications, and badges. Fundraising remains optional.',

                                        'community_event' =>
                                            'Community mode focuses on participant registration and activities. Badges, ticketing, and fundraising remain optional unless the event needs them.',

                                        'charity_event' =>
                                            'Charity / fundraising mode focuses on campaign support, donations, activities, communication, and optional participant registration.',

                                        'health_event' =>
                                            'Health / wellness mode focuses on participant registration, screenings or activities, public event information, communications, and linked donation campaigns. Ticketing and professional fields stay hidden by default.',

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
                | Donations / Fundraising
                |--------------------------------------------------------------------------
                */

                Section::make('Donations / Fundraising')
                    ->description(
                        'Use donation campaigns when this event accepts financial or in-kind support.'
                    )
                    ->schema([
                        Placeholder::make('donation_campaign_information')
                            ->label('Donation Campaigns')
                            ->content(
                                'After saving the event, create or manage its campaign under Donations → Campaigns and link the campaign to this event. Campaigns can include payment instructions, progress, donor tracking, and a public image gallery.'
                            )
                            ->columnSpanFull(),
                    ])
                    ->visible(
                        fn (Get $get): bool =>
                            self::showDonations($get)
                    )
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