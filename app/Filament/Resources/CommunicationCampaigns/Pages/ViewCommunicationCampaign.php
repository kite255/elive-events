<?php

namespace App\Filament\Resources\CommunicationCampaigns\Pages;

use App\Filament\Resources\CommunicationCampaigns\CommunicationCampaignResource;
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

            Action::make(
                'refresh'
            )
                ->label(
                    'Refresh'
                )
                ->icon(
                    'heroicon-o-arrow-path'
                )
                ->action(
                    function (): void {
                        /** @var CommunicationCampaign $campaign */
                        $campaign =
                            $this->record;

                        $campaign
                            ->refreshCounters();

                        $this->record
                            ->refresh();

                        Notification::make()
                            ->title(
                                'Campaign refreshed'
                            )
                            ->success()
                            ->send();
                    }
                ),

            Action::make(
                'retry_failed'
            )
                ->label(
                    'Retry All Failed SMS'
                )
                ->icon(
                    'heroicon-o-arrow-path'
                )
                ->color(
                    'warning'
                )
                ->requiresConfirmation()
                ->modalHeading(
                    'Retry all failed SMS messages?'
                )
                ->modalDescription(
                    'Only failed SMS messages in this campaign will be requeued.'
                )
                ->visible(
                    fn (): bool =>
                        $this->record->isSms()
                        && $this->record->canRetry()
                )
                ->action(
                    function (): void {
                        /** @var CommunicationCampaign $campaign */
                        $campaign =
                            $this->record;

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
                            ->whereNotNull(
                                'recipient'
                            )
                            ->whereNotNull(
                                'message'
                            )
                            ->get();

                        if ($logs->isEmpty()) {
                            Notification::make()
                                ->title(
                                    'No failed SMS messages'
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
                            )->onQueue(
                                'communications'
                            );

                            $queued++;
                        }

                        $campaign
                            ->refreshCounters();

                        $campaign
                            ->forceFill([
                                'status' =>
                                    CommunicationCampaign::STATUS_PROCESSING,

                                'completed_at' =>
                                    null,
                            ])
                            ->save();

                        $this->record
                            ->refresh();

                        Notification::make()
                            ->title(
                                'Failed SMS messages requeued'
                            )
                            ->body(
                                number_format(
                                    $queued
                                )
                                . ' SMS message(s) queued for retry.'
                            )
                            ->success()
                            ->send();
                    }
                ),
        ];
    }
}