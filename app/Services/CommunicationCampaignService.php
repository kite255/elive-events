<?php

namespace App\Services;

use App\Jobs\SendAutomaticCommunicationJob;
use App\Models\Attendee;
use App\Models\CommunicationCampaign;
use App\Models\CommunicationCampaignRecipient;
use App\Models\CommunicationLog;
use App\Models\CommunicationTemplate;
use App\Models\Event;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CommunicationCampaignService
{
    public function preview(
        Event $event,
        string $audience = 'all',
        ?int $categoryId = null
    ): array {
        $query = $this->audienceQuery(
            $event,
            $audience,
            $categoryId
        );

        $total = (clone $query)->count();

        $valid = 0;
        $invalid = 0;

        (clone $query)
            ->select([
                'id',
                'phone',
            ])
            ->orderBy('id')
            ->chunkById(
                500,
                function (Collection $attendees) use (
                    &$valid,
                    &$invalid
                ): void {
                    foreach ($attendees as $attendee) {
                        if (
                            app(PhoneNumberService::class)
                                ->isValid($attendee->phone)
                        ) {
                            $valid++;
                        } else {
                            $invalid++;
                        }
                    }
                }
            );

        return [
            'total' => $total,
            'valid' => $valid,
            'invalid' => $invalid,
        ];
    }

    public function queueSmsCampaign(
        Event $event,
        string $name,
        string $message,
        string $audience = 'all',
        ?int $categoryId = null,
        ?CommunicationTemplate $template = null,
        ?int $createdBy = null
    ): CommunicationCampaign {
        $message = trim($message);

        if ($message === '') {
            throw new RuntimeException(
                'Campaign message cannot be empty.'
            );
        }

        if ($template && ! $template->isUsable()) {
            throw new RuntimeException(
                'The selected communication template is not usable.'
            );
        }

        if (
            $template
            && $template->channel !== CommunicationTemplate::CHANNEL_SMS
        ) {
            throw new RuntimeException(
                'The selected template is not an SMS template.'
            );
        }

        $phoneService = app(PhoneNumberService::class);

        $campaign = DB::transaction(
            function () use (
                $event,
                $name,
                $message,
                $audience,
                $categoryId,
                $template,
                $createdBy,
                $phoneService
            ): CommunicationCampaign {
                $campaign = CommunicationCampaign::create([
                    'event_id' => $event->id,
                    'communication_template_id' => $template?->id,
                    'created_by' => $createdBy,
                    'name' => trim($name),
                    'channel' => CommunicationCampaign::CHANNEL_SMS,
                    'type' => 'manual_campaign',
                    'subject' => null,
                    'message' => $message,
                    'status' => CommunicationCampaign::STATUS_QUEUED,
                    'recipient_filter' => [
                        'audience' => $audience,
                        'category_id' => $categoryId,
                    ],
                    'total_recipients' => 0,
                    'queued_count' => 0,
                    'sent_count' => 0,
                    'delivered_count' => 0,
                    'failed_count' => 0,
                    'started_at' => now(),
                ]);

                $totalRecipients = 0;
                $queuedCount = 0;
                $failedCount = 0;

                $this->audienceQuery(
                    $event,
                    $audience,
                    $categoryId
                )
                    ->with([
                        'event:id,name,venue,starts_at',
                        'category:id,name',
                    ])
                    ->orderBy('id')
                    ->chunkById(
                        250,
                        function (Collection $attendees) use (
                            $campaign,
                            $message,
                            $phoneService,
                            &$totalRecipients,
                            &$queuedCount,
                            &$failedCount
                        ): void {
                            foreach ($attendees as $attendee) {
                                $totalRecipients++;

                                if (
                                    ! $phoneService->isValid(
                                        $attendee->phone
                                    )
                                ) {
                                    CommunicationCampaignRecipient::create([
                                        'communication_campaign_id' =>
                                            $campaign->id,
                                        'attendee_id' =>
                                            $attendee->id,
                                        'status' =>
                                            CommunicationCampaignRecipient::STATUS_SKIPPED,
                                        'recipient' =>
                                            $attendee->phone,
                                        'rendered_message' =>
                                            $this->renderMessage(
                                                $message,
                                                $attendee
                                            ),
                                        'error_message' =>
                                            'Missing or invalid mobile number.',
                                        'metadata' => [
                                            'reason' =>
                                                'invalid_phone',
                                        ],
                                    ]);

                                    $failedCount++;

                                    continue;
                                }

                                $recipient = $phoneService->normalize(
                                    $attendee->phone
                                );

                                $renderedMessage =
                                    $this->renderMessage(
                                        $message,
                                        $attendee
                                    );

                                $log = CommunicationLog::create([
                                    'event_id' =>
                                        $campaign->event_id,
                                    'attendee_id' =>
                                        $attendee->id,
                                    'communication_campaign_id' =>
                                        $campaign->id,
                                    'channel' =>
                                        CommunicationLog::CHANNEL_SMS,
                                    'recipient' =>
                                        $recipient,
                                    'subject' =>
                                        null,
                                    'message' =>
                                        $renderedMessage,
                                    'status' =>
                                        CommunicationLog::STATUS_QUEUED,
                                    'queued_at' =>
                                        now(),
                                ]);

                                CommunicationCampaignRecipient::create([
                                    'communication_campaign_id' =>
                                        $campaign->id,
                                    'attendee_id' =>
                                        $attendee->id,
                                    'communication_log_id' =>
                                        $log->id,
                                    'status' =>
                                        CommunicationCampaignRecipient::STATUS_QUEUED,
                                    'recipient' =>
                                        $recipient,
                                    'rendered_message' =>
                                        $renderedMessage,
                                    'attempts' =>
                                        0,
                                    'queued_at' =>
                                        now(),
                                ]);

                                SendAutomaticCommunicationJob::dispatch(
                                    $log->id
                                )->onQueue('communications');

                                $queuedCount++;
                            }
                        }
                    );

                $campaign->update([
                    'total_recipients' => $totalRecipients,
                    'queued_count' => $queuedCount,
                    'failed_count' => $failedCount,
                    'status' => $queuedCount > 0
                        ? CommunicationCampaign::STATUS_QUEUED
                        : CommunicationCampaign::STATUS_FAILED,
                    'completed_at' => $queuedCount > 0
                        ? null
                        : now(),
                ]);

                return $campaign->fresh();
            }
        );

        return $campaign;
    }

    public function audienceQuery(
        Event $event,
        string $audience = 'all',
        ?int $categoryId = null
    ): Builder {
        return Attendee::query()
            ->where('event_id', $event->id)
            ->whereNotIn('status', [
                'rejected',
                'cancelled',
            ])
            ->when(
                $categoryId,
                fn (Builder $query): Builder =>
                    $query->where(
                        'category_id',
                        $categoryId
                    )
            )
            ->when(
                $audience === 'registered',
                fn (Builder $query): Builder =>
                    $query->where(
                        'status',
                        'registered'
                    )
            )
            ->when(
                $audience === 'confirmed',
                fn (Builder $query): Builder =>
                    $query->where(
                        'status',
                        'confirmed'
                    )
            )
            ->when(
                $audience === 'approved',
                fn (Builder $query): Builder =>
                    $query->whereIn(
                        'status',
                        [
                            'registered',
                            'confirmed',
                            'approved',
                            'checked_in',
                        ]
                    )
            )
            ->when(
                $audience === 'pending_approval',
                fn (Builder $query): Builder =>
                    $query->where(
                        'status',
                        'pending_approval'
                    )
            )
            ->when(
                $audience === 'waitlisted',
                fn (Builder $query): Builder =>
                    $query->where(
                        'status',
                        'waitlisted'
                    )
            )
            ->when(
                $audience === 'checked_in',
                fn (Builder $query): Builder =>
                    $query->where(
                        function (Builder $query): void {
                            $query
                                ->where(
                                    'status',
                                    'checked_in'
                                )
                                ->orWhereNotNull(
                                    'checked_in_at'
                                );
                        }
                    )
            )
            ->when(
                $audience === 'not_checked_in',
                fn (Builder $query): Builder =>
                    $query->whereNull(
                        'checked_in_at'
                    )->where(
                        'status',
                        '!=',
                        'checked_in'
                    )
            );
    }

    public function renderMessage(
        string $message,
        Attendee $attendee
    ): string {
        $event = $attendee->event;

        $replacements = [
            '#NAME#' =>
                $attendee->full_name ?? '',
            '#PHONE#' =>
                $attendee->phone ?? '',
            '#EMAIL#' =>
                $attendee->email ?? '',
            '#ORGANIZATION#' =>
                $attendee->organization_name ?? '',
            '#POSITION#' =>
                $attendee->position ?? '',
            '#CATEGORY#' =>
                $attendee->category?->name ?? '',
            '#BADGE_NUMBER#' =>
                $attendee->badge_number ?? '',
            '#EVENT_NAME#' =>
                $event?->name ?? '',
            '#EVENT_VENUE#' =>
                $event?->venue ?? '',
            '#EVENT_DATE#' =>
                $event?->starts_at?->format(
                    'd M Y'
                ) ?? '',
            '#EVENT_TIME#' =>
                $event?->starts_at?->format(
                    'H:i'
                ) ?? '',
        ];

        return strtr(
            $message,
            $replacements
        );
    }
}
