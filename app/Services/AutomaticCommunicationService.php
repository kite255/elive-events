<?php

namespace App\Services;

use App\Jobs\SendAutomaticCommunicationJob;
use App\Models\Attendee;
use App\Models\CommunicationLog;
use App\Models\CommunicationTemplate;
use Illuminate\Support\Collection;
use Throwable;

class AutomaticCommunicationService
{
    public const TRIGGER_REGISTRATION_RECEIVED =
        'registration_received';

    public const TRIGGER_REGISTRATION_CONFIRMED =
        'registration_confirmed';

    public function __construct(
        protected MessagePlaceholderService $placeholderService,
        protected PhoneNumberService $phoneNumberService
    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | Registration Communication
    |--------------------------------------------------------------------------
    |
    | This method is called after public registration has completed.
    |
    | For the current MVP:
    |
    | - Only approved / registered attendees receive the automatic SMS.
    | - The event must have registration_sms_enabled = true.
    | - The event must have registration_sms_template_id selected.
    | - The selected template must be active.
    | - The selected template must be an SMS template.
    | - The attendee must have a valid phone number.
    |
    |--------------------------------------------------------------------------
    */

    public function handleRegistration(
        Attendee $attendee
    ): Collection {
        $attendee->loadMissing([
            'event.organization',
            'event.registrationSmsTemplate',
            'category',
            'badgeType',
        ]);

        $event = $attendee->event;

        if (! $event) {
            return collect();
        }

        /*
        |--------------------------------------------------------------------------
        | Only confirmed registration for now
        |--------------------------------------------------------------------------
        */

        if (! $attendee->isApproved()) {
            return collect();
        }

        /*
        |--------------------------------------------------------------------------
        | Event-level automatic SMS setting
        |--------------------------------------------------------------------------
        */

        if (! $event->shouldSendRegistrationSms()) {
            return collect();
        }

        /*
        |--------------------------------------------------------------------------
        | Selected registration SMS template
        |--------------------------------------------------------------------------
        */

        $template =
            $event->registrationSmsTemplate;

        if (! $template) {
            return collect();
        }

        /*
        |--------------------------------------------------------------------------
        | Validate template
        |--------------------------------------------------------------------------
        */

        if (! $this->isValidRegistrationSmsTemplate(
            $attendee,
            $template
        )) {
            return collect();
        }

        /*
        |--------------------------------------------------------------------------
        | Prepare communication
        |--------------------------------------------------------------------------
        */

        $log = $this->prepareCommunication(
            $attendee,
            $template
        );

        if (! $log) {
            return collect();
        }

        /*
        |--------------------------------------------------------------------------
        | Dispatch queued job
        |--------------------------------------------------------------------------
        */

        try {
            SendAutomaticCommunicationJob::dispatch(
                $log->id
            )->onQueue(
                'communications'
            );
        } catch (Throwable $exception) {
            /*
             * If dispatch itself fails, mark the log failed.
             */
            report($exception);

            $log->markFailed(
                $exception->getMessage()
            );

            return collect([
                $log->fresh(),
            ]);
        }

        return collect([
            $log,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Generic Trigger Dispatcher
    |--------------------------------------------------------------------------
    |
    | Keep this method for future automatic communication such as:
    |
    | registration_received
    | registration_approved
    | waitlist
    | reminder
    | badge_ready
    | certificate_ready
    |
    | The registration confirmation flow above does NOT depend on template
    | naming. It uses the template selected directly on the event.
    |
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

        $organizationId =
            $event->organization_id;

        if (! $organizationId) {
            return collect();
        }

        $templates =
            $this->templatesForTrigger(
                $organizationId,
                $trigger
            );

        if ($templates->isEmpty()) {
            return collect();
        }

        $logs = collect();

        foreach ($templates as $template) {
            $log =
                $this->prepareCommunication(
                    $attendee,
                    $template
                );

            if (! $log) {
                continue;
            }

            try {
                SendAutomaticCommunicationJob::dispatch(
                    $log->id
                )->onQueue(
                    'communications'
                );

                $logs->push(
                    $log
                );
            } catch (Throwable $exception) {
                report($exception);

                $log->markFailed(
                    $exception->getMessage()
                );

                $logs->push(
                    $log->fresh()
                );
            }
        }

        return $logs;
    }

    /*
    |--------------------------------------------------------------------------
    | Registration SMS Template Validation
    |--------------------------------------------------------------------------
    */

    protected function isValidRegistrationSmsTemplate(
        Attendee $attendee,
        CommunicationTemplate $template
    ): bool {
        $event = $attendee->event;

        if (! $event) {
            return false;
        }

        /*
         * Template must belong to the same organization as the event.
         */
        if (
            (int) $template->organization_id
            !== (int) $event->organization_id
        ) {
            return false;
        }

        /*
         * Template must be active.
         */
        if (! $template->is_active) {
            return false;
        }

        /*
         * Registration automatic communication currently uses SMS.
         */
        if (
            $template->channel
            !== CommunicationTemplate::CHANNEL_SMS
        ) {
            return false;
        }

        /*
         * Template must contain a message body.
         */
        if (blank($template->body)) {
            return false;
        }

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Future Trigger Template Lookup
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
            ->orderBy(
                'channel'
            )
            ->get();
    }

    /*
    |--------------------------------------------------------------------------
    | Prepare Communication Log
    |--------------------------------------------------------------------------
    */

    protected function prepareCommunication(
        Attendee $attendee,
        CommunicationTemplate $template
    ): ?CommunicationLog {
        /*
        |--------------------------------------------------------------------------
        | Resolve recipient
        |--------------------------------------------------------------------------
        */

        $recipient =
            $this->recipientForChannel(
                $attendee,
                $template->channel
            );

        if (blank($recipient)) {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | Render template
        |--------------------------------------------------------------------------
        */

        $subject =
            $this->placeholderService
                ->renderNullable(
                    $template->subject,
                    $attendee
                );

        $message =
            $this->placeholderService
                ->render(
                    $template->body,
                    $attendee
                );

        $message =
            trim(
                $message
            );

        if (blank($message)) {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | Duplicate Protection
        |--------------------------------------------------------------------------
        |
        | Public registration should trigger this once.
        |
        | This also prevents accidental duplicate dispatch if the automatic
        | communication method is called again for the same attendee with
        | exactly the same automatic message.
        |
        |--------------------------------------------------------------------------
        */

        $existingLog =
            CommunicationLog::query()
                ->where(
                    'event_id',
                    $attendee->event_id
                )
                ->where(
                    'attendee_id',
                    $attendee->id
                )
                ->whereNull(
                    'communication_campaign_id'
                )
                ->where(
                    'channel',
                    $template->channel
                )
                ->where(
                    'recipient',
                    $recipient
                )
                ->where(
                    'message',
                    $message
                )
                ->whereIn(
                    'status',
                    [
                        CommunicationLog::STATUS_PENDING,
                        CommunicationLog::STATUS_QUEUED,
                        CommunicationLog::STATUS_SENDING,
                        CommunicationLog::STATUS_SENT,
                        CommunicationLog::STATUS_DELIVERED,
                    ]
                )
                ->first();

        if ($existingLog) {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | Create communication log
        |--------------------------------------------------------------------------
        */

        return CommunicationLog::create([
            'event_id' =>
                $attendee->event_id,

            'attendee_id' =>
                $attendee->id,

            'communication_campaign_id' =>
                null,

            'channel' =>
                $template->channel,

            'recipient' =>
                $recipient,

            'subject' =>
                $subject,

            'message' =>
                $message,

            'status' =>
                CommunicationLog::STATUS_QUEUED,

            'queued_at' =>
                now(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Recipient Resolution
    |--------------------------------------------------------------------------
    */

    protected function recipientForChannel(
        Attendee $attendee,
        string $channel
    ): ?string {
        return match ($channel) {
            CommunicationTemplate::CHANNEL_EMAIL =>
                $this->emailRecipient(
                    $attendee
                ),

            CommunicationTemplate::CHANNEL_SMS,
            CommunicationTemplate::CHANNEL_WHATSAPP =>
                $this->phoneRecipient(
                    $attendee
                ),

            default =>
                null,
        };
    }

    /*
    |--------------------------------------------------------------------------
    | Phone Recipient
    |--------------------------------------------------------------------------
    */

    protected function phoneRecipient(
        Attendee $attendee
    ): ?string {
        $phone =
            $this->phoneNumberService
                ->normalize(
                    $attendee->phone
                );

        if (blank($phone)) {
            return null;
        }

        if (
            ! $this->phoneNumberService
                ->isValid(
                    $phone
                )
        ) {
            return null;
        }

        return $phone;
    }

    /*
    |--------------------------------------------------------------------------
    | Email Recipient
    |--------------------------------------------------------------------------
    */

    protected function emailRecipient(
        Attendee $attendee
    ): ?string {
        $email =
            strtolower(
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