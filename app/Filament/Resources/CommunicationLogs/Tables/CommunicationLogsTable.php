<?php

namespace App\Filament\Resources\CommunicationLogs\Tables;

use App\Filament\Resources\CommunicationLogs\CommunicationLogResource;
use App\Models\CommunicationLog;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CommunicationLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->paginated([
                25,
                50,
                100,
            ])
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('event.name')
                    ->label('Event')
                    ->searchable()
                    ->sortable()
                    ->limit(28)
                    ->tooltip(
                        fn (CommunicationLog $record): ?string =>
                            $record->event?->name
                    ),

                TextColumn::make('attendee.full_name')
                    ->label('Attendee')
                    ->placeholder('Unknown attendee')
                    ->searchable()
                    ->sortable()
                    ->limit(28),

                TextColumn::make('recipient')
                    ->label('Recipient')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Recipient copied')
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

                TextColumn::make('campaign.name')
                    ->label('Campaign')
                    ->placeholder('Automatic / Direct')
                    ->searchable()
                    ->limit(28)
                    ->toggleable(),

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

                TextColumn::make('provider_message_id')
                    ->label('Provider ID')
                    ->placeholder('—')
                    ->copyable()
                    ->limit(24)
                    ->tooltip(
                        fn (CommunicationLog $record): ?string =>
                            $record->provider_message_id
                    )
                    ->toggleable(isToggledHiddenByDefault: true),

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

                TextColumn::make('error')
                    ->label('Error')
                    ->placeholder('—')
                    ->limit(35)
                    ->tooltip(
                        fn (CommunicationLog $record): ?string =>
                            $record->error
                    )
                    ->color('danger')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('event_id')
                    ->label('Event')
                    ->relationship(
                        'event',
                        'name'
                    )
                    ->searchable()
                    ->preload(),

                SelectFilter::make('communication_campaign_id')
                    ->label('Campaign')
                    ->relationship(
                        'campaign',
                        'name'
                    )
                    ->searchable()
                    ->preload(),

                SelectFilter::make('channel')
                    ->options([
                        CommunicationLog::CHANNEL_SMS =>
                            'SMS',

                        CommunicationLog::CHANNEL_WHATSAPP =>
                            'WhatsApp',

                        CommunicationLog::CHANNEL_EMAIL =>
                            'Email',
                    ]),

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

                Filter::make('failed_only')
                    ->label('Failed only')
                    ->query(
                        fn (Builder $query): Builder =>
                            $query->where(
                                'status',
                                CommunicationLog::STATUS_FAILED
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
                ViewAction::make(),

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
                    ->visible(
                        fn (CommunicationLog $record): bool =>
                            $record->canRetry()
                    )
                    ->requiresConfirmation()
                    ->modalHeading('Retry failed communication?')
                    ->modalDescription(
                        'The failed log will be reset and requeued. The retry job must exist in the application for delivery to restart.'
                    )
                    ->modalSubmitActionLabel('Retry')
                    ->action(
                        function (
                            CommunicationLog $record
                        ): void {
                            $jobClass =
                                \App\Jobs\RetryCommunicationLogJob::class;

                            if (! class_exists($jobClass)) {
                                Notification::make()
                                    ->title('Retry job not installed')
                                    ->body(
                                        'Create App\\Jobs\\RetryCommunicationLogJob before enabling automatic retries from this page.'
                                    )
                                    ->warning()
                                    ->send();

                                return;
                            }

                            $record->prepareForRetry();

                            $jobClass::dispatch(
                                $record->getKey()
                            );

                            Notification::make()
                                ->title('Communication requeued')
                                ->body(
                                    'The failed communication has been sent back to the communications queue.'
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
            ->emptyStateHeading(
                'No communication logs'
            )
            ->emptyStateDescription(
                'SMS, WhatsApp, and email activity will appear here.'
            )
            ->emptyStateIcon(
                'heroicon-o-chat-bubble-left-right'
            );
    }
}
