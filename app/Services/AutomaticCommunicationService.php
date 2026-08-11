<?php

namespace App\Services;

use App\Jobs\SendAutomaticCommunicationJob;
use App\Models\Attendee;
use App\Models\CommunicationLog;
use App\Models\CommunicationTemplate;
use Illuminate\Support\Collection;

class AutomaticCommunicationService
{
    public const TRIGGER_REGISTRATION_RECEIVED =
        'registration_received';

    public const TRIGGER_REGISTRATION_CONFIRMED =
        'registration_confirmed';

    public function __construct(
        protected MessagePlaceholderService $placeholderService
    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | Registration communication
    |--------------------------------------------------------------------------
    */

    public function handleRegistration(
        Attendee $attendee
    ): Collection {
        $attendee->loadMissing([
            'event.organization',
            'category',
            'badgeType',
        ]);

        if (! $attendee->event) {
            return collect();
        }

        if ($attendee->isPendingApproval()) {
            return $this->dispatchTrigger(
                $attendee,
                self::TRIGGER_REGISTRATION_RECEIVED
            );
        }

        if ($attendee->isApproved()) {
            return $this->dispatchTrigger(
                $attendee,
                self::TRIGGER_REGISTRATION_CONFIRMED
            );
        }

        return collect();
    }

    /*
    |--------------------------------------------------------------------------
    | Trigger dispatcher
    |--------------------------------------------------------------------------
    */

    public function dispatchTrigger(
        Attendee $attendee,
        string $trigger
    ): Collection {
        $attendee->loadMissing([
            'event.organization',
            'category',
            'badgeType',
        ]);

        $event = $attendee->event;

        if (! $event) {
            return collect();
        }

        $organizationId = $event->organization_id;

        if (! $organizationId) {
            return collect();
        }

        $templates = $this->templatesForTrigger(
            $organizationId,
            $trigger
        );

        if ($templates->isEmpty()) {
            return collect();
        }

        $logs = collect();

        foreach ($templates as $template) {
            $log = $this->prepareCommunication(
                $attendee,
                $template
            );

            if (! $log) {
                continue;
            }

            SendAutomaticCommunicationJob::dispatch(
                $log->id
            )->onQueue('communications');

            $logs->push($log);
        }

        return $logs;
    }

    /*
    |--------------------------------------------------------------------------
    | Template lookup
    |--------------------------------------------------------------------------
    */

    protected function templatesForTrigger(
        int $organizationId,
        string $trigger
    ): Collection {
        return CommunicationTemplate::query()
            ->where(
                'organization_id',
                $organizationId
            )
            ->where(
                'is_active',
                true
            )
            ->whereIn(
                'channel',
                [
                    CommunicationTemplate::CHANNEL_SMS,
                    CommunicationTemplate::CHANNEL_EMAIL,
                    CommunicationTemplate::CHANNEL_WHATSAPP,
                ]
            )
            ->whereIn(
                'name',
                [
                    "{$trigger}_sms",
                    "{$trigger}_email",
                    "{$trigger}_whatsapp",
                ]
            )
            ->orderBy('channel')
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | Prepare log
    |--------------------------------------------------------------------------
    */

    protected function prepareCommunication(
        Attendee $attendee,
        CommunicationTemplate $template
    ): ?CommunicationLog {
        $recipient = $this->recipientForChannel(
            $attendee,
            $template->channel
        );

        if (blank($recipient)) {
            return null;
        }

        $subject = $this->placeholderService
            ->renderNullable(
                $template->subject,
                $attendee
            );

        $message = $this->placeholderService
            ->render(
                $template->body,
                $attendee
            );

        if (blank($message)) {
            return null;
        }

        return CommunicationLog::create([
            'event_id' => $attendee->event_id,
            'attendee_id' => $attendee->id,
            'communication_campaign_id' => null,

            'channel' => $template->channel,
            'recipient' => $recipient,

            'subject' => $subject,
            'message' => $message,

            'status' => CommunicationLog::STATUS_QUEUED,
            'queued_at' => now(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Recipient resolution
    |--------------------------------------------------------------------------
    */

    protected function recipientForChannel(
        Attendee $attendee,
        string $channel
    ): ?string {
        return match ($channel) {
            CommunicationTemplate::CHANNEL_EMAIL =>
                $this->emailRecipient($attendee),

            CommunicationTemplate::CHANNEL_SMS,
            CommunicationTemplate::CHANNEL_WHATSAPP =>
                $this->phoneRecipient($attendee),

            default => null,
        };
    }

    protected function phoneRecipient(
        Attendee $attendee
    ): ?string {
        $phone = trim(
            (string) $attendee->phone
        );

        return $phone !== ''
            ? $phone
            : null;
    }

    protected function emailRecipient(
        Attendee $attendee
    ): ?string {
        $email = strtolower(
            trim(
                (string) $attendee->email
            )
        );

        if (
            $email === ''
            || ! filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {
            return null;
        }

        return $email;
    }
}