<?php

namespace App\Jobs;

use App\Models\EventCommunication;
use App\Services\EventCommunicationCampaignService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class QueueEventCommunicationCampaignJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    public array $backoff = [
        60,
        300,
        900,
    ];

    public function __construct(
        public int $communicationId,
        public string $channel,
        public string $audience = 'all',
        public ?int $categoryId = null,
        public ?int $createdBy = null
    ) {
    }

    public function handle(
        EventCommunicationCampaignService $service
    ): void {
        $communication =
            EventCommunication::query()
                ->with([
                    'event.organization',
                ])
                ->find(
                    $this->communicationId
                );

        if (! $communication) {
            return;
        }

        $campaign =
            $service->sendNow(
                communication:
                    $communication,
                channel:
                    $this->channel,
                audience:
                    $this->audience,
                categoryId:
                    $this->categoryId,
                createdBy:
                    $this->createdBy
            );

        Log::info(
            'Scheduled Event Communication campaign queued.',
            [
                'event_communication_id' =>
                    $communication->id,

                'communication_campaign_id' =>
                    $campaign->id,

                'channel' =>
                    $this->channel,

                'audience' =>
                    $this->audience,

                'category_id' =>
                    $this->categoryId,
            ]
        );
    }

    public function failed(
        ?Throwable $exception
    ): void {
        Log::error(
            'Scheduled Event Communication campaign failed.',
            [
                'event_communication_id' =>
                    $this->communicationId,

                'channel' =>
                    $this->channel,

                'error' =>
                    $exception?->getMessage(),
            ]
        );
    }
}
