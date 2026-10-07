<?php

namespace App\Filament\Resources\DonationPaymentMethods\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DonationPaymentMethodsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('campaign.title')->label('Campaign')->searchable()->sortable(),
                TextColumn::make('provider_name')->label('Provider / Bank')->searchable()->sortable(),
                TextColumn::make('type')->badge()->sortable(),
                TextColumn::make('account_name')->searchable(),
                TextColumn::make('account_number_or_phone')->label('Account / Phone')->copyable(),
                IconColumn::make('enabled')->boolean(),
                TextColumn::make('sort_order')->numeric()->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order');
    }
}
