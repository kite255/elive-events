<?php

namespace App\Filament\Resources\Donations\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DonationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('campaign.title')
                    ->label('Campaign')
                    ->searchable(),

                TextColumn::make('campaign.event.name')
                    ->label('Event')
                    ->placeholder('—'),

                TextColumn::make('donor_name')
                    ->label('Donor')
                    ->placeholder('Anonymous')
                    ->searchable(),

                TextColumn::make('amount')
                    ->money(
                        fn ($record): string =>
                            strtolower((string) $record->currency)
                    )
                    ->sortable(),

                TextColumn::make('payment_type')
                    ->badge(),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'awaiting_payment' => 'Awaiting Payment',
                        'awaiting_verification' => 'Awaiting Verification',
                        'completed' => 'Completed',
                        'rejected' => 'Rejected',
                        'failed' => 'Failed',
                        'cancelled' => 'Cancelled',
                    ]),

                SelectFilter::make('payment_type')
                    ->options([
                        'platform' => 'Platform',
                        'client_direct' => 'Client Direct',
                    ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
