<?php

namespace App\Filament\Resources\DonationPaymentMethods\Schemas;

use App\Models\DonationCampaign;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class DonationPaymentMethodForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('donation_campaign_id')
                ->label('Campaign')
                ->options(fn (): array => self::campaignOptions())
                ->required()
                ->searchable()
                ->preload(),

            Select::make('type')
                ->options([
                    'mobile_money' => 'Mobile Money',
                    'bank' => 'Bank',
                    'custom' => 'Custom',
                ])
                ->required()
                ->live(),

            TextInput::make('provider_name')
                ->label('Provider / Bank')
                ->required()
                ->maxLength(255),

            TextInput::make('account_name')
                ->maxLength(255),

            Select::make('account_identifier_type')
                ->label('Custom payment detail type')
                ->options([
                    'account' => 'Account Number',
                    'phone' => 'Phone Number',
                    'lipa' => 'LIPA Number',
                ])
                ->default('account')
                ->visible(fn (Get $get): bool => $get('type') === 'custom')
                ->required(fn (Get $get): bool => $get('type') === 'custom')
                ->live(),

            TextInput::make('account_number_or_phone')
                ->label(fn (Get $get): string => match ($get('type')) {
                    'bank' => 'Account Number',
                    'mobile_money' => 'Phone Number',
                    'custom' => match ($get('account_identifier_type')) {
                        'phone' => 'Phone Number',
                        'lipa' => 'LIPA Number',
                        default => 'Account Number',
                    },
                    default => 'Account Number / Phone Number',
                })
                ->maxLength(255),

            Textarea::make('instructions')
                ->rows(4),

            Toggle::make('enabled')
                ->default(true),

            TextInput::make('sort_order')
                ->numeric()
                ->default(0)
                ->minValue(0),
        ]);
    }

    public static function campaignOptions(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        return DonationCampaign::query()
            ->accessibleBy($user)
            ->orderBy('title')
            ->pluck('title', 'id')
            ->mapWithKeys(fn ($title, $id) => [(int) $id => $title])
            ->toArray();
    }
}
