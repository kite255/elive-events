<?php

namespace App\Filament\Resources\CommunicationTemplates\Schemas;

use App\Models\Organization;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

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
                        'Create reusable SMS, email, and WhatsApp message templates.'
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
                            ->native(false),

                        TextInput::make(
                            'name'
                        )
                            ->label(
                                'Template Name'
                            )
                            ->placeholder(
                                'Registration Confirmation SMS'
                            )
                            ->required()
                            ->maxLength(
                                255
                            ),

                        Select::make(
                            'channel'
                        )
                            ->label(
                                'Channel'
                            )
                            ->options([
                                'sms' =>
                                    'SMS',

                                'email' =>
                                    'Email',

                                'whatsapp' =>
                                    'WhatsApp',
                            ])
                            ->default(
                                'sms'
                            )
                            ->required()
                            ->live()
                            ->native(
                                false
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
                                'Registration confirmed for #EVENT_NAME#'
                            )
                            ->maxLength(
                                255
                            )
                            ->visible(
                                fn (
                                    callable $get
                                ): bool =>
                                    $get('channel')
                                    === 'email'
                            ),

                        Textarea::make(
                            'body'
                        )
                            ->label(
                                'Message Body'
                            )
                            ->placeholder(
                                "Hello #NAME#,\n\nYour registration for #EVENT_NAME# has been completed successfully.\n\nCategory: #CATEGORY#\nVenue: #EVENT_VENUE#\n\nThank you,\neLive Events"
                            )
                            ->required()
                            ->rows(
                                10
                            )
                            ->columnSpanFull(),

                        \Filament\Forms\Components\Placeholder::make(
                            'available_placeholders'
                        )
                            ->label(
                                'Available Placeholders'
                            )
                            ->content(
                                '#NAME#, #PHONE#, #EMAIL#, #ORGANIZATION#, #POSITION#, #CATEGORY#, #BADGE_NUMBER#, #EVENT_NAME#, #EVENT_VENUE#, #EVENT_DATE#, #EVENT_TIME#'
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
        | Normal organization users
        |--------------------------------------------------------------------------
        |
        | We use the organizations relationship when available.
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