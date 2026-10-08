<?php

namespace App\Filament\Resources\Events\RelationManagers;

use App\Models\Donation;
use App\Models\Event;
use App\Services\EventPresetService;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class DonationsRelationManager extends RelationManager
{
    protected static string $relationship = 'donations';

    protected static ?string $title = 'Donation Records';

    protected static ?string $modelLabel = 'Donation';

    protected static ?string $pluralModelLabel = 'Donation Records';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Event
            && EventPresetService::usesDonations($ownerRecord->event_type);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('reference')
                    ->label('Reference')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('campaign.title')
                    ->label('Campaign')
                    ->searchable(),

                TextColumn::make('donor_name')
                    ->label('Donor')
                    ->placeholder('Anonymous')
                    ->searchable(),

                TextColumn::make('donor_phone')
                    ->label('Phone')
                    ->toggleable(),

                TextColumn::make('amount')
                    ->money(
                        fn (Donation $record): string =>
                            strtolower((string) $record->currency)
                    )
                    ->sortable(),

                TextColumn::make('payment_type')
                    ->label('Payment Type')
                    ->badge(),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('manualSubmission.paymentMethod.provider_name')
                    ->label('Payment Method')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('completed_at')
                    ->label('Completed')
                    ->dateTime('d M Y, H:i')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        Donation::STATUS_PENDING => 'Pending',
                        Donation::STATUS_AWAITING_PAYMENT => 'Awaiting Payment',
                        Donation::STATUS_AWAITING_VERIFICATION => 'Awaiting Verification',
                        Donation::STATUS_COMPLETED => 'Completed',
                        Donation::STATUS_REJECTED => 'Rejected',
                        Donation::STATUS_FAILED => 'Failed',
                        Donation::STATUS_CANCELLED => 'Cancelled',
                    ]),

                SelectFilter::make('payment_type')
                    ->options([
                        'platform' => 'Platform',
                        'client_direct' => 'Client Direct',
                    ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
