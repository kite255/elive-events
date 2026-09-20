<?php

namespace App\Services;

use App\Models\CommunicationCampaign;
use App\Models\CommunicationTemplate;
use App\Models\EventCommunication;
use Carbon\CarbonInterface;
use RuntimeException;

class EventCommunicationCampaignService
{
    public function __construct(
        protected CommunicationCampaignService $campaignService
    ) {
    }

    public function preview(
        EventCommunication $communication,
        string $channel,
        string $audience = 'all',
        ?int $categoryId = null
    ): array {
        $communication->loadMissing('event');

        if (! $communication->event) {
            return [
                'total' => 0,
                'valid' => 0,
                'invalid' => 0,
            ];
        }

        return $this->campaignService->preview(
            $communication->event,
            $audience,
            $categoryId,
            $channel
        );
    }

    public function sendNow(
        EventCommunication $communication,
        string $channel,
        string $audience = 'all',
        ?int $categoryId = null,
        ?int $createdBy = null
    ): CommunicationCampaign {
        $communication->loadMissing([
            'event.organization',
        ]);

        $this->validateCommunication(
            $communication
        );

        $event = $communication->event;

        if (! $event) {
            throw new RuntimeException(
                'The event for this communication could not be found.'
            );
        }

        $channel = strtolower(
            trim($channel)
        );

        $campaignName =
            $event->name
            . ' - '
            . $communication->title;

        $publicUrl =
            $communication->publicUrl();

        $summary =
            trim(
                strip_tags(
                    (string) (
                        $communication->summary
                        ?: $communication->body
                    )
                )
            );

        $summary =
            preg_replace(
                '/\s+/u',
                ' ',
                $summary
            ) ?: '';

        return match ($channel) {
            CommunicationTemplate::CHANNEL_EMAIL =>
                $this->campaignService
                    ->queueEmailCampaign(
                        event: $event,
                        name: $campaignName,
                        subject: $this->emailSubject(
                            $communication
                        ),
                        message: $this->emailMessage(
                            $communication,
                            $summary,
                            $publicUrl
                        ),
                        audience: $audience,
                        categoryId: $categoryId,
                        template: null,
                        createdBy: $createdBy
                    ),

            CommunicationTemplate::CHANNEL_SMS =>
                $this->campaignService
                    ->queueSmsCampaign(
                        event: $event,
                        name: $campaignName,
                        message: $this->smsMessage(
                            $communication,
                            $summary,
                            $publicUrl
                        ),
                        audience: $audience,
                        categoryId: $categoryId,
                        template: null,
                        createdBy: $createdBy
                    ),

            CommunicationTemplate::CHANNEL_WHATSAPP =>
                throw new RuntimeException(
                    'WhatsApp Event Communications are not enabled yet. '
                    . 'The current WhatsApp job is dedicated to the approved '
                    . 'registration-confirmation template. Add a dedicated '
                    . 'Meta template for event updates before enabling this channel.'
                ),

            default =>
                throw new RuntimeException(
                    'Unsupported Event Communication channel.'
                ),
        };
    }

    public function schedule(
        EventCommunication $communication,
        string $channel,
        string $audience,
        ?int $categoryId,
        CarbonInterface $scheduledAt,
        ?int $createdBy = null
    ): void {
        $this->validateCommunication(
            $communication
        );

        if (
            $scheduledAt->lessThanOrEqualTo(
                now()
            )
        ) {
            throw new RuntimeException(
                'Scheduled time must be in the future.'
            );
        }

        \App\Jobs\QueueEventCommunicationCampaignJob::dispatch(
            communicationId:
                (int) $communication->getKey(),
            channel:
                $channel,
            audience:
                $audience,
            categoryId:
                $categoryId,
            createdBy:
                $createdBy
        )
            ->delay(
                $scheduledAt
            )
            ->onQueue(
                'default'
            );
    }

    protected function validateCommunication(
        EventCommunication $communication
    ): void {
        if (
            ! $communication->isPublished()
        ) {
            throw new RuntimeException(
                'Publish the Event Communication before sending it.'
            );
        }

        if (
            ! $communication->is_public
        ) {
            throw new RuntimeException(
                'Enable the public page before sending this communication.'
            );
        }

        if (
            blank(
                $communication->publicUrl()
            )
        ) {
            throw new RuntimeException(
                'The public communication link could not be generated.'
            );
        }
    }

    protected function emailSubject(
        EventCommunication $communication
    ): string {
        return trim(
            $communication->title
        );
    }

    protected function emailMessage(
        EventCommunication $communication,
        string $summary,
        string $publicUrl
    ): string {
        $eventName =
            $communication->event?->name
            ?? 'Event';

        $lines = [
            'Hello #NAME#,',
            '',
        ];

        if ($summary !== '') {
            $lines[] = $summary;
            $lines[] = '';
        }

        $lines[] =
            'View the full update, photos, links, and handouts:';

        $lines[] =
            $publicUrl;

        $lines[] = '';
        $lines[] =
            'Thank you for being part of '
            . $eventName
            . '.';

        return implode(
            PHP_EOL,
            $lines
        );
    }

    protected function smsMessage(
        EventCommunication $communication,
        string $summary,
        string $publicUrl
    ): string {
        $eventName =
            $communication->event?->name
            ?? 'Event';

        $message =
            $eventName
            . ': '
            . (
                $summary !== ''
                    ? $summary
                    : $communication->title
            );

        /*
         * Keep SMS compact. The public page contains the full content.
         */
        if (
            mb_strlen($message)
            > 220
        ) {
            $message =
                mb_substr(
                    $message,
                    0,
                    217
                )
                . '...';
        }

        return $message
            . ' View: '
            . $publicUrl;
    }
}
