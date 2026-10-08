<?php

namespace App\Filament\Resources\Events\RelationManagers;

use App\Models\DonationCampaign;
use App\Models\Event;
use App\Services\EventPresetService;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class DonationCampaignsRelationManager extends RelationManager
{
    protected static string $relationship = 'donationCampaigns';

    protected static ?string $title = 'Donations';

    protected static ?string $modelLabel = 'Donation Campaign';

    protected static ?string $pluralModelLabel = 'Donations';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Event
            && EventPresetService::usesDonations($ownerRecord->event_type);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Hidden::make('organization_id')
                ->default(fn (): ?int => $this->getOwnerRecord()->organization_id)
                ->required(),

            Section::make('Campaign')
                ->description('The event and organization are inherited automatically from this Event.')
                ->schema([
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
                        ->label('Campaign Story')
                        ->rows(5)
                        ->columnSpanFull(),

                    FileUpload::make('banner_image_path')
                        ->label('Campaign Banner')
                        ->image()
                        ->disk('public')
                        ->directory('donations/campaigns')
                        ->maxSize(4096),

                    FileUpload::make('gallery_image_paths')
                        ->label('Campaign Gallery / Wishlist')
                        ->helperText('Upload campaign posters, wishlists, translated artwork, or other fundraising images.')
                        ->image()
                        ->multiple()
                        ->reorderable()
                        ->appendFiles()
                        ->disk('public')
                        ->directory('donations/campaigns/gallery')
                        ->maxFiles(8)
                        ->maxSize(4096)
                        ->columnSpanFull(),

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
                        ->label('Show Donation Section Publicly')
                        ->default(false),
                ])
                ->columns(2),

            Section::make('Donation Flow')
                ->schema([
                    Select::make('payment_mode')
                        ->label('Payment Mode')
                        ->options([
                            DonationCampaign::PAYMENT_MODE_PLATFORM => 'eLive Online Payment',
                            DonationCampaign::PAYMENT_MODE_CLIENT_DIRECT => 'Client Direct Payment',
                            DonationCampaign::PAYMENT_MODE_HYBRID => 'Hybrid',
                        ])
                        ->required()
                        ->live(),

                    Select::make('direct_payment_behavior')
                        ->label('Direct Payment Behavior')
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
                        ->label('Minimum Donation')
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
                        ->label('Allow Custom Amount')
                        ->default(true)
                        ->visible(fn (Get $get): bool => self::trackingFieldsVisible(
                            $get('payment_mode'),
                            $get('direct_payment_behavior')
                        )),
                ])
                ->columns(2),

            Section::make('Progress & Public Display')
                ->schema([
                    TextInput::make('goal_amount')
                        ->label('Fundraising Goal')
                        ->numeric()
                        ->minValue(0),

                    Toggle::make('show_goal')->label('Show Goal')->default(false),
                    Toggle::make('show_amount_raised')->label('Show Amount Raised')->default(false),
                    Toggle::make('show_percentage')->label('Show Percentage')->default(false),
                    Toggle::make('show_donor_count')->label('Show Donor Count')->default(false),
                    Toggle::make('donor_wall_enabled')->label('Enable Donor Wall')->default(false),
                ])
                ->columns(2),

            Section::make('Direct Payment Methods')
                ->description('Configure bank, mobile money, or custom payment instructions for this event campaign.')
                ->schema([
                    Repeater::make('paymentMethods')
                        ->relationship()
                        ->label('Payment Methods')
                        ->schema([
                            Select::make('type')
                                ->options([
                                    'mobile_money' => 'Mobile Money',
                                    'bank' => 'Bank',
                                    'custom' => 'Custom',
                                ])
                                ->required(),

                            TextInput::make('provider_name')
                                ->label('Provider / Bank')
                                ->required()
                                ->maxLength(255),

                            TextInput::make('account_name')
                                ->label('Account Name')
                                ->maxLength(255),

                            TextInput::make('account_number_or_phone')
                                ->label('Account Number / Phone')
                                ->maxLength(255),

                            Textarea::make('instructions')
                                ->label('Instructions')
                                ->rows(3)
                                ->columnSpanFull(),

                            Toggle::make('enabled')
                                ->default(true),

                            TextInput::make('sort_order')
                                ->numeric()
                                ->default(0)
                                ->minValue(0),
                        ])
                        ->columns(2)
                        ->defaultItems(0)
                        ->addActionLabel('Add Payment Method')
                        ->reorderable('sort_order')
                        ->collapsible()
                        ->columnSpanFull(),
                ])
                ->visible(fn (Get $get): bool => in_array(
                    $get('payment_mode'),
                    [
                        DonationCampaign::PAYMENT_MODE_CLIENT_DIRECT,
                        DonationCampaign::PAYMENT_MODE_HYBRID,
                    ],
                    true
                )),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->columns([
                TextColumn::make('title')
                    ->label('Campaign')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('payment_mode')
                    ->label('Payment Mode')
                    ->badge(),

                TextColumn::make('goal_amount')
                    ->label('Goal')
                    ->money(
                        fn (DonationCampaign $record): string =>
                            strtolower((string) $record->currency)
                    )
                    ->placeholder('—'),

                TextColumn::make('donations_count')
                    ->label('Donations')
                    ->counts('donations')
                    ->badge(),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                IconColumn::make('is_public')
                    ->label('Public')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Donation Campaign'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->requiresConfirmation(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    private static function trackingFieldsVisible(
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
