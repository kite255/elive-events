<?php

namespace App\Filament\Resources\CommunicationTemplates\Schemas;

use App\Models\CommunicationTemplate;
use App\Models\Organization;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CommunicationTemplateForm
{
    public static function configure(
        Schema $schema
    ): Schema {
        return $schema
            ->components([
                Section::make(
                    'Template Details'
                )
                    ->description(
                        'Create reusable SMS, email, and WhatsApp message templates using friendly template types.'
                    )
                    ->schema([
                        Select::make(
                            'organization_id'
                        )
                            ->label(
                                'Organization'
                            )
                            ->options(
                                fn (): array =>
                                    self::organizationOptions()
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(
                                false
                            ),

                        Select::make(
                            'channel'
                        )
                            ->label(
                                'Channel'
                            )
                            ->options(
                                CommunicationTemplate::channelOptions()
                            )
                            ->default(
                                CommunicationTemplate::CHANNEL_EMAIL
                            )
                            ->required()
                            ->live()
                            ->native(
                                false
                            )
                            ->afterStateUpdated(
                                function (
                                    callable $set
                                ): void {
                                    $set(
                                        'key',
                                        null
                                    );

                                    $set(
                                        'name',
                                        null
                                    );
                                }
                            ),

                        Select::make(
                            'key'
                        )
                            ->label(
                                'Template Type'
                            )
                            ->options(
                                fn (
                                    callable $get
                                ): array =>
                                    CommunicationTemplate::templateTypesForChannel(
                                        $get(
                                            'channel'
                                        )
                                    )
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->native(
                                false
                            )
                            ->helperText(
                                'Choose the purpose of this communication. eLive Events will keep the internal system key automatically.'
                            )
                            ->afterStateUpdated(
                                function (
                                    $state,
                                    callable $set
                                ): void {
                                    $friendlyName =
                                        CommunicationTemplate::friendlyNameForKey(
                                            $state
                                        );

                                    if (
                                        filled(
                                            $friendlyName
                                        )
                                    ) {
                                        $set(
                                            'name',
                                            $friendlyName
                                        );
                                    }

                                    $channel =
                                        CommunicationTemplate::channelForKey(
                                            $state
                                        );

                                    if (
                                        filled(
                                            $channel
                                        )
                                    ) {
                                        $set(
                                            'channel',
                                            $channel
                                        );
                                    }
                                }
                            ),

                        TextInput::make(
                            'name'
                        )
                            ->label(
                                'Template Name'
                            )
                            ->helperText(
                                'This user-friendly name is set automatically from the selected template type.'
                            )
                            ->disabled()
                            ->dehydrated()
                            ->required()
                            ->maxLength(
                                255
                            ),

                        Toggle::make(
                            'is_active'
                        )
                            ->label(
                                'Active'
                            )
                            ->default(
                                true
                            ),
                    ])
                    ->columns(
                        2
                    ),

                Section::make(
                    'Message Content'
                )
                    ->description(
                        'Use the supported eLive Events placeholders to personalize each message.'
                    )
                    ->schema([
                        TextInput::make(
                            'subject'
                        )
                            ->label(
                                'Email Subject'
                            )
                            ->placeholder(
                                'Registration Confirmed – #EVENT_NAME#'
                            )
                            ->helperText(
                                'Only used for email templates.'
                            )
                            ->maxLength(
                                255
                            )
                            ->required(
                                fn (
                                    callable $get
                                ): bool =>
                                    $get(
                                        'channel'
                                    )
                                    === CommunicationTemplate::CHANNEL_EMAIL
                            )
                            ->visible(
                                fn (
                                    callable $get
                                ): bool =>
                                    $get(
                                        'channel'
                                    )
                                    === CommunicationTemplate::CHANNEL_EMAIL
                            ),

                        Textarea::make(
                            'body'
                        )
                            ->label(
                                'Message Body'
                            )
                            ->placeholder(
                                "Hello #NAME#,\n\nYour registration for #EVENT_NAME# has been completed successfully.\n\nCategory: #CATEGORY#\nVenue: #EVENT_VENUE#\nDate: #EVENT_DATE#\nTime: #EVENT_TIME#\n\nThank you,\neLive Events"
                            )
                            ->required()
                            ->rows(
                                10
                            )
                            ->columnSpanFull(),

                        Placeholder::make(
                            'available_placeholders'
                        )
                            ->label(
                                'Available Placeholders'
                            )
                            ->content(
                                '#NAME#, #PHONE#, #EMAIL#, #ORGANIZATION#, #POSITION#, #CATEGORY#, #PARTICIPANT_TYPE#, #BADGE_TYPE#, #BADGE_NUMBER#, #BADGE_LINK#, #EVENT_NAME#, #EVENT_VENUE#, #EVENT_DATE#, #EVENT_TIME#, #PUBLIC_LINK#, #REGISTRATION_LINK#'
                            )
                            ->columnSpanFull(),
                    ])
                    ->columns(
                        1
                    ),
            ]);
    }

    private static function organizationOptions(): array
    {
        $user =
            auth()->user();

        if (! $user) {
            return [];
        }

        $query =
            Organization::query()
                ->where(
                    'status',
                    'active'
                )
                ->orderBy(
                    'name'
                );

        /*
        |--------------------------------------------------------------------------
        | Super Admin
        |--------------------------------------------------------------------------
        */

        if (
            method_exists(
                $user,
                'isSuperAdmin'
            )
            && $user->isSuperAdmin()
        ) {
            return $query
                ->pluck(
                    'name',
                    'id'
                )
                ->all();
        }

        /*
        |--------------------------------------------------------------------------
        | Normal Organization Users
        |--------------------------------------------------------------------------
        */

        if (
            method_exists(
                $user,
                'organizations'
            )
        ) {
            $organizationIds =
                $user->organizations()
                    ->pluck(
                        'organizations.id'
                    );

            return $query
                ->whereIn(
                    'id',
                    $organizationIds
                )
                ->pluck(
                    'name',
                    'id'
                )
                ->all();
        }

        /*
        |--------------------------------------------------------------------------
        | Fallback
        |--------------------------------------------------------------------------
        */

        if (
            filled(
                $user->organization_id
                ?? null
            )
        ) {
            return $query
                ->where(
                    'id',
                    $user->organization_id
                )
                ->pluck(
                    'name',
                    'id'
                )
                ->all();
        }

        return [];
    }
}
