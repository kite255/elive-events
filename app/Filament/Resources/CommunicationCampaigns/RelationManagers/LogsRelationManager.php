<?php

namespace App\Filament\Resources\CommunicationCampaigns\RelationManagers;

use App\Jobs\RetryCommunicationLogJob;
use App\Models\CommunicationLog;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LogsRelationManager extends RelationManager
{
    protected static string $relationship =
        'logs';

    protected static ?string $title =
        'Message Logs';

    public function table(
        Table $table
    ): Table {
        return $table
            ->defaultSort(
                'id',
                'desc'
            )
            ->columns([

                TextColumn::make(
                    'attendee.full_name'
                )
                    ->label(
                        'Attendee'
                    )
                    ->searchable()
                    ->placeholder(
                        '—'
                    ),

                TextColumn::make(
                    'recipient'
                )
                    ->label(
                        'Recipient'
                    )
                    ->searchable()
                    ->copyable(),

                TextColumn::make(
                    'status'
                )
                    ->badge()
                    ->formatStateUsing(
                        fn (
                            string $state
                        ): string =>
                            str(
                                $state
                            )->headline()
                    )
                    ->color(
                        fn (
                            string $state
                        ): string =>
                            match ($state) {
                                CommunicationLog::STATUS_DELIVERED =>
                                    'success',

                                CommunicationLog::STATUS_SENT =>
                                    'success',

                                CommunicationLog::STATUS_FAILED =>
                                    'danger',

                                CommunicationLog::STATUS_SENDING =>
                                    'warning',

                                CommunicationLog::STATUS_QUEUED =>
                                    'info',

                                default =>
                                    'gray',
                            }
                    ),

                TextColumn::make(
                    'provider_message_id'
                )
                    ->label(
                        'Provider ID'
                    )
                    ->copyable()
                    ->placeholder(
                        '—'
                    )
                    ->toggleable(),

                TextColumn::make(
                    'error'
                )
                    ->label(
                        'Error'
                    )
                    ->limit(
                        55
                    )
                    ->tooltip(
                        fn (
                            CommunicationLog $record
                        ): ?string =>
                            $record->error
                    )
                    ->placeholder(
                        '—'
                    ),

                TextColumn::make(
                    'queued_at'
                )
                    ->label(
                        'Queued'
                    )
                    ->dateTime()
                    ->placeholder(
                        '—'
                    )
                    ->toggleable(
                        isToggledHiddenByDefault: true
                    ),

                TextColumn::make(
                    'sent_at'
                )
                    ->label(
                        'Sent'
                    )
                    ->dateTime()
                    ->placeholder(
                        '—'
                    ),

                TextColumn::make(
                    'failed_at'
                )
                    ->label(
                        'Failed'
                    )
                    ->dateTime()
                    ->placeholder(
                        '—'
                    ),
            ])
            ->filters([

                SelectFilter::make(
                    'status'
                )
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
            ])
            ->recordActions([

                Action::make(
                    'retry'
                )
                    ->label(
                        'Retry'
                    )
                    ->icon(
                        'heroicon-o-arrow-path'
                    )
                    ->color(
                        'warning'
                    )
                    ->requiresConfirmation()
                    ->modalHeading(
                        'Retry failed SMS?'
                    )
                    ->modalDescription(
                        'This will queue this SMS again using the communications queue.'
                    )
                    ->visible(
                        fn (
                            CommunicationLog $record
                        ): bool =>
                            $record->isSms()
                            && $record->canRetry()
                    )
                    ->action(
                        function (
                            CommunicationLog $record
                        ): void {
                            $record
                                ->prepareForRetry();

                            RetryCommunicationLogJob::dispatch(
                                $record->id
                            )->onQueue(
                                'communications'
                            );

                            Notification::make()
                                ->title(
                                    'SMS queued for retry'
                                )
                                ->body(
                                    "Recipient: {$record->recipient}"
                                )
                                ->success()
                                ->send();
                        }
                    ),
            ]);
    }
}