<?php

namespace App\Filament\Resources\DonationCampaigns\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DonationCampaignsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable()->sortable()->weight('bold'),
                TextColumn::make('organization.name')->label('Organization')->sortable(),
                TextColumn::make('event.name')->label('Event')->placeholder('—')->sortable(),
                TextColumn::make('payment_mode')->label('Payment Mode')->badge(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('currency')->sortable(),
                TextColumn::make('created_at')->dateTime('d M Y, H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'active' => 'Active',
                        'paused' => 'Paused',
                        'completed' => 'Completed',
                        'archived' => 'Archived',
                    ]),
                SelectFilter::make('payment_mode')
                    ->options([
                        'platform' => 'eLive Online Payment',
                        'client_direct' => 'Client Direct Payment',
                        'hybrid' => 'Hybrid',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
