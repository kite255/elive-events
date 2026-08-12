<?php

namespace App\Filament\Resources\CommunicationCampaigns\Pages;

use App\Filament\Resources\CommunicationCampaigns\CommunicationCampaignResource;
use App\Filament\Resources\CommunicationLogs\CommunicationLogResource;
use App\Jobs\RetryCommunicationLogJob;
use App\Models\CommunicationCampaign;
use App\Models\CommunicationLog;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewCommunicationCampaign extends ViewRecord
{
    protected static string $resource =
        CommunicationCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refresh')
                ->label('Refresh Counters')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function (): void {
                    /** @var CommunicationCampaign $campaign */
                    $campaign = $this->record;

                    $campaign->refreshCounters();
                    $this->record->refresh();

                    Notification::make()
                        ->title('Campaign counters refreshed')
                        ->body(
                            'Queued, sent, delivered, failed, processed, and remaining totals have been recalculated.'
                        )
                        ->success()
                        ->send();
                }),

            Action::make('view_logs')
                ->label('View Communication Logs')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('primary')
                ->url(
                    fn (): string =>
                        CommunicationLogResource::getUrl(
                            'index',
                            [
                                'tableFilters' => [
                                    'communication_campaign_id' => [
                                        'value' =>
                                            $this->record->id,
                                    ],
                                ],
                            ]
                        )
                ),

            Action::make('retry_failed')
                ->label('Retry All Failed SMS')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading(
                    'Retry all failed SMS messages?'
                )
                ->modalDescription(
                    'Only failed SMS messages in this campaign with a recipient and message will be requeued.'
                )
                ->modalSubmitActionLabel(
                    'Retry Failed SMS'
                )
                ->visible(
                    fn (): bool =>
                        $this->record->isSms()
                        && $this->record->canRetry()
                )
                ->action(function (): void {
                    /** @var CommunicationCampaign $campaign */
                    $campaign = $this->record;

                    $logs = CommunicationLog::query()
                        ->where(
                            'communication_campaign_id',
                            $campaign->id
                        )
                        ->where(
                            'channel',
                            CommunicationLog::CHANNEL_SMS
                        )
                        ->where(
                            'status',
                            CommunicationLog::STATUS_FAILED
                        )
                        ->whereNotNull('recipient')
                        ->whereNotNull('message')
                        ->get();

                    if ($logs->isEmpty()) {
                        Notification::make()
                            ->title(
                                'No failed SMS messages'
                            )
                            ->body(
                                'There are no failed SMS messages eligible for retry in this campaign.'
                            )
                            ->warning()
                            ->send();

                        return;
                    }

                    $queued = 0;

                    foreach ($logs as $log) {
                        if (! $log->canRetry()) {
                            continue;
                        }

                        $log->prepareForRetry();

                        RetryCommunicationLogJob::dispatch(
                            $log->id
                        );

                        $queued++;
                    }

                    if ($queued === 0) {
                        Notification::make()
                            ->title(
                                'No SMS messages requeued'
                            )
                            ->body(
                                'The failed messages are not currently eligible for retry.'
                            )
                            ->warning()
                            ->send();

                        return;
                    }

                    $campaign->refreshCounters();

                    if (! $campaign->isCancelled()) {
                        $campaign->forceFill([
                            'status' =>
                                CommunicationCampaign::STATUS_PROCESSING,

                            'completed_at' =>
                                null,
                        ])->save();
                    }

                    $this->record->refresh();

                    Notification::make()
                        ->title(
                            'Failed SMS messages requeued'
                        )
                        ->body(
                            number_format($queued)
                            . ' SMS message(s) were returned to the communications queue.'
                        )
                        ->success()
                        ->send();
                }),
        ];
    }
}
