<?php

namespace App\Filament\Resources\CommunicationCampaigns\RelationManagers;

use App\Filament\Resources\CommunicationLogs\CommunicationLogResource;
use App\Jobs\RetryCommunicationLogJob;
use App\Models\CommunicationLog;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LogsRelationManager extends RelationManager
{
    protected static string $relationship = 'logs';

    protected static ?string $title = 'Message Logs';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->paginated([
                25,
                50,
                100,
            ])
            ->modifyQueryUsing(
                fn (Builder $query): Builder =>
                    $query->with([
                        'attendee',
                        'campaignRecipient',
                    ])
            )
            ->columns([
                TextColumn::make('attendee.full_name')
                    ->label('Attendee')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Unknown attendee')
                    ->limit(28),

                TextColumn::make('recipient')
                    ->label('Recipient')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Recipient copied')
                    ->placeholder('—')
                    ->limit(26),

                TextColumn::make('channel')
                    ->label('Channel')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string =>
                            match ($state) {
                                CommunicationLog::CHANNEL_SMS =>
                                    'SMS',

                                CommunicationLog::CHANNEL_WHATSAPP =>
                                    'WhatsApp',

                                CommunicationLog::CHANNEL_EMAIL =>
                                    'Email',

                                default =>
                                    str((string) $state)
                                        ->headline()
                                        ->toString(),
                            }
                    )
                    ->color(
                        fn (?string $state): string =>
                            match ($state) {
                                CommunicationLog::CHANNEL_SMS =>
                                    'info',

                                CommunicationLog::CHANNEL_WHATSAPP =>
                                    'success',

                                CommunicationLog::CHANNEL_EMAIL =>
                                    'primary',

                                default =>
                                    'gray',
                            }
                    ),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string =>
                            str((string) $state)
                                ->replace('_', ' ')
                                ->headline()
                                ->toString()
                    )
                    ->color(
                        fn (?string $state): string =>
                            match ($state) {
                                CommunicationLog::STATUS_DELIVERED =>
                                    'success',

                                CommunicationLog::STATUS_SENT =>
                                    'success',

                                CommunicationLog::STATUS_SENDING =>
                                    'info',

                                CommunicationLog::STATUS_QUEUED,
                                CommunicationLog::STATUS_PENDING =>
                                    'warning',

                                CommunicationLog::STATUS_FAILED =>
                                    'danger',

                                default =>
                                    'gray',
                            }
                    )
                    ->sortable(),

                TextColumn::make('campaignRecipient.attempts')
                    ->label('Attempts')
                    ->numeric()
                    ->placeholder('0')
                    ->toggleable(),

                TextColumn::make('provider_message_id')
                    ->label('Provider ID')
                    ->copyable()
                    ->copyMessage('Provider ID copied')
                    ->placeholder('—')
                    ->limit(24)
                    ->tooltip(
                        fn (CommunicationLog $record): ?string =>
                            $record->provider_message_id
                    )
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('message')
                    ->label('Message')
                    ->limit(45)
                    ->tooltip(
                        fn (CommunicationLog $record): ?string =>
                            $record->message
                    )
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('error')
                    ->label('Error')
                    ->limit(45)
                    ->tooltip(
                        fn (CommunicationLog $record): ?string =>
                            $record->error
                    )
                    ->placeholder('—')
                    ->color('danger')
                    ->toggleable(),

                TextColumn::make('queued_at')
                    ->label('Queued')
                    ->dateTime('d M Y, H:i')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('sent_at')
                    ->label('Sent')
                    ->dateTime('d M Y, H:i')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('delivered_at')
                    ->label('Delivered')
                    ->dateTime('d M Y, H:i')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('failed_at')
                    ->label('Failed')
                    ->dateTime('d M Y, H:i')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        CommunicationLog::STATUS_PENDING =>
                            'Pending',

                        CommunicationLog::STATUS_QUEUED =>
                            'Queued',

                        CommunicationLog::STATUS_SENDING =>
                            'Sending',

                        CommunicationLog::STATUS_SENT =>
                            'Sent',

                        CommunicationLog::STATUS_DELIVERED =>
                            'Delivered',

                        CommunicationLog::STATUS_FAILED =>
                            'Failed',
                    ]),

                SelectFilter::make('channel')
                    ->options([
                        CommunicationLog::CHANNEL_SMS =>
                            'SMS',

                        CommunicationLog::CHANNEL_WHATSAPP =>
                            'WhatsApp',

                        CommunicationLog::CHANNEL_EMAIL =>
                            'Email',
                    ]),

                Filter::make('failed_only')
                    ->label('Failed only')
                    ->query(
                        fn (Builder $query): Builder =>
                            $query->where(
                                'status',
                                CommunicationLog::STATUS_FAILED
                            )
                    ),

                Filter::make('incomplete_only')
                    ->label('Pending / queued / sending')
                    ->query(
                        fn (Builder $query): Builder =>
                            $query->whereIn(
                                'status',
                                [
                                    CommunicationLog::STATUS_PENDING,
                                    CommunicationLog::STATUS_QUEUED,
                                    CommunicationLog::STATUS_SENDING,
                                ]
                            )
                    ),

                Filter::make('queued_over_10_minutes')
                    ->label('Queued over 10 minutes')
                    ->query(
                        fn (Builder $query): Builder =>
                            $query
                                ->where(
                                    'status',
                                    CommunicationLog::STATUS_QUEUED
                                )
                                ->whereNotNull('queued_at')
                                ->where(
                                    'queued_at',
                                    '<=',
                                    now()->subMinutes(10)
                                )
                    ),

                Filter::make('today')
                    ->label('Today')
                    ->query(
                        fn (Builder $query): Builder =>
                            $query->whereDate(
                                'created_at',
                                today()
                            )
                    ),
            ])
            ->recordActions([
                Action::make('view_log')
                    ->label('View')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->url(
                        fn (CommunicationLog $record): string =>
                            CommunicationLogResource::getUrl(
                                'view',
                                [
                                    'record' =>
                                        $record,
                                ]
                            )
                    ),

                Action::make('view_error')
                    ->label('View Error')
                    ->icon('heroicon-o-exclamation-triangle')
                    ->color('danger')
                    ->visible(
                        fn (CommunicationLog $record): bool =>
                            $record->isFailed()
                            && filled($record->error)
                    )
                    ->modalHeading('Communication Failure')
                    ->modalDescription(
                        fn (CommunicationLog $record): string =>
                            $record->errorLabel()
                    )
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),

                Action::make('retry')
                    ->label('Retry')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Retry failed SMS?')
                    ->modalDescription(
                        'This SMS will be requeued using the communications queue.'
                    )
                    ->modalSubmitActionLabel('Retry SMS')
                    ->visible(
                        fn (CommunicationLog $record): bool =>
                            $record->isSms()
                            && $record->canRetry()
                    )
                    ->action(
                        function (
                            CommunicationLog $record
                        ): void {
                            $record->prepareForRetry();

                            RetryCommunicationLogJob::dispatch(
                                $record->id
                            );

                            Notification::make()
                                ->title('SMS queued for retry')
                                ->body(
                                    "Recipient: {$record->recipient}"
                                )
                                ->success()
                                ->send();
                        }
                    ),
            ])
            ->recordUrl(
                fn (CommunicationLog $record): string =>
                    CommunicationLogResource::getUrl(
                        'view',
                        [
                            'record' =>
                                $record,
                        ]
                    )
            )
            ->emptyStateHeading('No message logs')
            ->emptyStateDescription(
                'Campaign recipient activity will appear here as messages are queued and processed.'
            )
            ->emptyStateIcon(
                'heroicon-o-chat-bubble-left-right'
            );
    }
}
