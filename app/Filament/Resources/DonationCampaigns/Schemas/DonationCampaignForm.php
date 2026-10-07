<?php

namespace App\Filament\Resources\DonationCampaigns\Schemas;

use App\Models\DonationCampaign;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class DonationCampaignForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Campaign')
                ->schema([
                    Select::make('organization_id')
                        ->label('Organization')
                        ->options(fn (): array => self::organizationOptions())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live(),

                    Select::make('event_id')
                        ->label('Linked Event')
                        ->options(fn (Get $get): array => self::eventOptions(
                            $get('organization_id') ? (int) $get('organization_id') : null
                        ))
                        ->searchable()
                        ->preload()
                        ->nullable(),

                    TextInput::make('title')
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (string $operation, $state, callable $set): void {
                            if ($operation === 'create' && filled($state)) {
                                $set('slug', Str::slug((string) $state));
                            }
                        }),

                    TextInput::make('slug')
                        ->required()
                        ->maxLength(255),

                    Textarea::make('description')
                        ->rows(5),

                    FileUpload::make('banner_image_path')
                        ->label('Banner')
                        ->image()
                        ->disk('public')
                        ->directory('donations/campaigns')
                        ->maxSize(4096),

                    Select::make('status')
                        ->options([
                            DonationCampaign::STATUS_DRAFT => 'Draft',
                            DonationCampaign::STATUS_ACTIVE => 'Active',
                            DonationCampaign::STATUS_PAUSED => 'Paused',
                            DonationCampaign::STATUS_COMPLETED => 'Completed',
                            DonationCampaign::STATUS_ARCHIVED => 'Archived',
                        ])
                        ->default(DonationCampaign::STATUS_DRAFT)
                        ->required(),

                    Toggle::make('is_public')
                        ->label('Public Campaign')
                        ->default(false),
                ])
                ->columns(2),

            Section::make('Payment')
                ->schema([
                    Select::make('payment_mode')
                        ->options([
                            DonationCampaign::PAYMENT_MODE_PLATFORM => 'eLive Online Payment',
                            DonationCampaign::PAYMENT_MODE_CLIENT_DIRECT => 'Client Direct Payment',
                            DonationCampaign::PAYMENT_MODE_HYBRID => 'Hybrid',
                        ])
                        ->required()
                        ->live(),

                    Select::make('direct_payment_behavior')
                        ->options([
                            DonationCampaign::DIRECT_BEHAVIOR_DISPLAY_ONLY => 'Display Only',
                            DonationCampaign::DIRECT_BEHAVIOR_TRACKED => 'Display + Tracking',
                        ])
                        ->visible(fn (Get $get): bool => in_array(
                            $get('payment_mode'),
                            [
                                DonationCampaign::PAYMENT_MODE_CLIENT_DIRECT,
                                DonationCampaign::PAYMENT_MODE_HYBRID,
                            ],
                            true
                        ))
                        ->nullable()
                        ->live(),

                    TextInput::make('currency')
                        ->default('TZS')
                        ->required()
                        ->maxLength(3),

                    TextInput::make('minimum_amount')
                        ->numeric()
                        ->minValue(0)
                        ->visible(fn (Get $get): bool => self::trackingFieldsVisible(
                            $get('payment_mode'),
                            $get('direct_payment_behavior')
                        )),

                    TagsInput::make('suggested_amounts')
                        ->label('Suggested Amounts')
                        ->visible(fn (Get $get): bool => self::trackingFieldsVisible(
                            $get('payment_mode'),
                            $get('direct_payment_behavior')
                        )),

                    Toggle::make('allow_custom_amount')
                        ->default(true)
                        ->visible(fn (Get $get): bool => self::trackingFieldsVisible(
                            $get('payment_mode'),
                            $get('direct_payment_behavior')
                        )),
                ])
                ->columns(2),

            Section::make('Public Progress')
                ->schema([
                    TextInput::make('goal_amount')
                        ->numeric()
                        ->minValue(0),

                    Toggle::make('show_goal')->default(false),
                    Toggle::make('show_amount_raised')->default(false),
                    Toggle::make('show_percentage')->default(false),
                    Toggle::make('show_donor_count')->default(false),
                    Toggle::make('donor_wall_enabled')->default(false),
                ])
                ->columns(2),
        ]);
    }

    public static function organizationOptions(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        $query = Organization::query()->orderBy('name');

        if (! $user->isSuperAdmin()) {
            $managedIds = $user->managedOrganizations()->pluck('organizations.id');
            $eventOrganizationIds = $user->eventManagerEvents()->pluck('events.organization_id');

            $ids = $managedIds
                ->merge($eventOrganizationIds)
                ->unique()
                ->values();

            if ($ids->isEmpty()) {
                return [];
            }

            $query->whereIn('id', $ids);
        }

        return $query->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id) => [(int) $id => $name])
            ->toArray();
    }

    public static function eventOptions(?int $organizationId): array
    {
        $user = auth()->user();

        if (! $user instanceof User || ! $organizationId) {
            return [];
        }

        $query = Event::query()
            ->where('organization_id', $organizationId)
            ->orderBy('name');

        if (! $user->isSuperAdmin() && ! $user->canManageOrganization($organizationId)) {
            $eventIds = $user->eventManagerEvents()->pluck('events.id');

            if ($eventIds->isEmpty()) {
                return [];
            }

            $query->whereIn('id', $eventIds);
        }

        return $query->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id) => [(int) $id => $name])
            ->toArray();
    }

    public static function trackingFieldsVisible(
        ?string $paymentMode,
        ?string $directPaymentBehavior
    ): bool {
        if ($paymentMode === DonationCampaign::PAYMENT_MODE_PLATFORM) {
            return true;
        }

        if ($paymentMode === DonationCampaign::PAYMENT_MODE_HYBRID) {
            return true;
        }

        return $paymentMode === DonationCampaign::PAYMENT_MODE_CLIENT_DIRECT
            && $directPaymentBehavior === DonationCampaign::DIRECT_BEHAVIOR_TRACKED;
    }
}
