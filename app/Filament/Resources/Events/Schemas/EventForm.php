<?php

namespace App\Filament\Resources\Events\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class EventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Event Information')
                    ->description('Basic event details and organization ownership.')
                    ->schema([
                        Select::make('organization_id')
                            ->label('Organization')
                            ->relationship('organization', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        TextInput::make('name')
                            ->label('Event Name')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, $state, callable $set) {
                                if ($operation === 'create' && filled($state)) {
                                    $set('slug', Str::slug($state));
                                }
                            }),

                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('event_code')
                            ->label('Event Code')
                            ->helperText('Used for badge numbers. Example: LC26 gives ELV-LC26-000001.')
                            ->placeholder('LC26')
                            ->maxLength(20)
                            ->unique(ignoreRecord: true)
                            ->alphaDash()
                            ->dehydrateStateUsing(fn ($state) => filled($state) ? strtoupper((string) $state) : null)
                            ->nullable(),

                        Select::make('event_type')
                            ->label('Event Type')
                            ->options([
                                'conference' => 'Conference',
                                'seminar' => 'Seminar',
                                'workshop' => 'Workshop',
                                'wedding' => 'Wedding',
                                'church_event' => 'Church Event',
                                'graduation' => 'Graduation',
                                'corporate_event' => 'Corporate Event',
                                'exhibition' => 'Exhibition',
                                'training' => 'Training',
                                'vip_ceremony' => 'VIP Ceremony',
                                'other' => 'Other',
                            ])
                            ->searchable(),

                        TextInput::make('venue')
                            ->label('Venue')
                            ->maxLength(255),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(4)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Schedule and Capacity')
                    ->description('Set the event date, time, capacity, and publishing status.')
                    ->schema([
                        DateTimePicker::make('starts_at')
                            ->label('Start Date & Time')
                            ->seconds(false),

                        DateTimePicker::make('ends_at')
                            ->label('End Date & Time')
                            ->seconds(false)
                            ->afterOrEqual('starts_at'),

                        TextInput::make('capacity')
                            ->label('Capacity')
                            ->helperText('Leave empty if the event has no strict capacity limit.')
                            ->numeric()
                            ->minValue(1),

                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'draft' => 'Draft',
                                'published' => 'Published',
                                'ongoing' => 'Ongoing',
                                'completed' => 'Completed',
                                'cancelled' => 'Cancelled',
                            ])
                            ->default('draft')
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Public Registration')
                    ->description('Control whether attendees can register publicly for this event.')
                    ->schema([
                        Toggle::make('registration_is_open')
                            ->label('Registration Open')
                            ->helperText('Enable this to allow public registration for this event.')
                            ->default(false),

                        Toggle::make('registration_requires_approval')
                            ->label('Requires Approval')
                            ->helperText('If enabled, public registrations will be marked as pending approval.')
                            ->default(false),

                        Toggle::make('registration_auto_generate_badge')
                            ->label('Auto-generate Badge')
                            ->helperText('Automatically generate a badge after public registration when the attendee is registered.')
                            ->default(true),

                        Toggle::make('registration_waitlist_enabled')
                            ->label('Enable Waitlist')
                            ->helperText('Allow attendees to join the waitlist when the event reaches capacity.')
                            ->default(false),

                        TextInput::make('registration_welcome_title')
                            ->label('Welcome Title')
                            ->maxLength(255)
                            ->placeholder('Register for this event'),

                        Textarea::make('registration_welcome_message')
                            ->label('Welcome Message')
                            ->rows(3)
                            ->placeholder('Complete the form below to register for this event.')
                            ->columnSpanFull(),

                        Textarea::make('registration_success_message')
                            ->label('Success Message')
                            ->rows(3)
                            ->placeholder('Thank you. Your registration has been received.')
                            ->columnSpanFull(),

                        Textarea::make('registration_waitlist_message')
                            ->label('Waitlist Message')
                            ->rows(3)
                            ->placeholder('This event is currently full. You have been added to the waitlist.')
                            ->columnSpanFull(),
                    ])
                    ->columns(3)
                    ->collapsible(),

                Section::make('Registration Branding')
                    ->description('Optional event-level branding for the public registration page. If left empty, organization branding or eLive defaults will be used.')
                    ->schema([
                        FileUpload::make('registration_logo_path')
                            ->label('Registration Logo')
                            ->disk('public')
                            ->directory('event-registration/logos')
                            ->image()
                            ->imageEditor()
                            ->imagePreviewHeight('120')
                            ->downloadable()
                            ->openable()
                            ->maxSize(2048)
                            ->helperText('Optional event-specific logo shown on the public registration page.'),

                        FileUpload::make('registration_banner_image_path')
                            ->label('Banner Image')
                            ->disk('public')
                            ->directory('event-registration/banners')
                            ->image()
                            ->imageEditor()
                            ->imagePreviewHeight('180')
                            ->downloadable()
                            ->openable()
                            ->maxSize(4096)
                            ->helperText('Optional banner image shown at the top of the registration page.'),

                        ColorPicker::make('registration_primary_color')
                            ->label('Primary Color')
                            ->default('#233F7E'),

                        ColorPicker::make('registration_background_color')
                            ->label('Background Color')
                            ->default('#F8FAFC'),

                        ColorPicker::make('registration_button_color')
                            ->label('Button Color')
                            ->default('#233F7E'),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed(),
            ]);
    }
}