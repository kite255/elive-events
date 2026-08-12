<?php

namespace App\Filament\Resources\CommunicationLogs\Pages;

use App\Filament\Resources\CommunicationLogs\CommunicationLogResource;
use App\Models\CommunicationLog;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class ViewCommunicationLog extends ViewRecord
{
    protected static string $resource =
        CommunicationLogResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Communication')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('event.name')
                            ->label('Event')
                            ->placeholder('—'),

                        TextEntry::make('attendee.full_name')
                            ->label('Attendee')
                            ->placeholder('Unknown attendee'),

                        TextEntry::make('campaign.name')
                            ->label('Campaign')
                            ->placeholder('Automatic / Direct'),

                        TextEntry::make('channel')
                            ->label('Channel')
                            ->badge()
                            ->formatStateUsing(
                                fn (?string $state): string =>
                                    str((string) $state)
                                        ->headline()
                                        ->toString()
                            ),

                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(
                                fn (?string $state): string =>
                                    str((string) $state)
                                        ->replace('_', ' ')
                                        ->headline()
                                        ->toString()
                            ),

                        TextEntry::make('recipient')
                            ->label('Recipient')
                            ->copyable()
                            ->placeholder('—'),

                        TextEntry::make('subject')
                            ->label('Subject')
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('message')
                            ->label('Message')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),

                Section::make('Provider & Delivery')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('provider_message_id')
                            ->label('Provider Message ID')
                            ->copyable()
                            ->placeholder('—')
                            ->columnSpanFull(),

                        TextEntry::make('queued_at')
                            ->label('Queued At')
                            ->dateTime('d M Y, H:i:s')
                            ->placeholder('—'),

                        TextEntry::make('sent_at')
                            ->label('Sent At')
                            ->dateTime('d M Y, H:i:s')
                            ->placeholder('—'),

                        TextEntry::make('delivered_at')
                            ->label('Delivered At')
                            ->dateTime('d M Y, H:i:s')
                            ->placeholder('—'),

                        TextEntry::make('failed_at')
                            ->label('Failed At')
                            ->dateTime('d M Y, H:i:s')
                            ->placeholder('—'),

                        TextEntry::make('created_at')
                            ->label('Created At')
                            ->dateTime('d M Y, H:i:s')
                            ->placeholder('—'),

                        TextEntry::make('updated_at')
                            ->label('Updated At')
                            ->dateTime('d M Y, H:i:s')
                            ->placeholder('—'),

                        TextEntry::make('error')
                            ->label('Error')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('retry')
                ->label('Retry Failed Message')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->visible(
                    fn (): bool =>
                        $this->getRecord()->canRetry()
                )
                ->requiresConfirmation()
                ->modalHeading('Retry failed communication?')
                ->modalDescription(
                    'The failed communication will be reset and requeued.'
                )
                ->modalSubmitActionLabel('Retry')
                ->action(function (): void {
                    /** @var CommunicationLog $record */
                    $record = $this->getRecord();

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

                    $this->refreshFormData([
                        'status',
                        'provider_message_id',
                        'error',
                        'queued_at',
                        'sent_at',
                        'delivered_at',
                        'failed_at',
                    ]);
                }),
        ];
    }
}
