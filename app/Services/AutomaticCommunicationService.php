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
        protected PhoneNumberService $phoneNumberService,
        protected WhatsAppService $whatsAppService,
    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | Registration Communication
    |--------------------------------------------------------------------------
    |
    | Called after public registration completes.
    |
    | Current automatic channels:
    |
    | 1. SMS
    |    - Event must have registration SMS enabled.
    |    - Event must have a registration SMS template selected.
    |
    | 2. Email
    |    - Attendee must be approved.
    |    - Attendee must have a valid email address.
    |    - Organization must have an active template key:
    |      registration_confirmed_email
    |    - If a badge already exists it can be attached by the email job.
    |
    | 3. WhatsApp
    |    - Attendee must be approved.
    |    - WhatsApp must be configured.
    |    - Attendee must have a valid phone number.
    |    - Badge must already exist.
    |    - Uses Meta template:
    |      event_registration_confirmation
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
        | Only approved / confirmed attendees
        |--------------------------------------------------------------------------
        */

        if (! $attendee->isApproved()) {
            return collect();
        }

        $logs = collect();

        /*
        |--------------------------------------------------------------------------
        | Automatic SMS
        |--------------------------------------------------------------------------
        */

        $smsLog =
            $this->prepareRegistrationSms(
                $attendee
            );

        if ($smsLog) {
            $logs->push(
                $this->dispatchLog(
                    $smsLog
                )
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Automatic Email
        |--------------------------------------------------------------------------
        |
        | Email can be queued immediately after approval.
        |
        | If the attendee badge already exists, the email sending job can attach
        | it. If the badge is generated asynchronously, handleBadgeReady() will
        | safely attempt the registration email again; duplicate protection
        | prevents a second copy from being sent.
        |
        */

        $emailLog =
            $this->prepareRegistrationEmail(
                $attendee
            );

        if ($emailLog) {
            $logs->push(
                $this->dispatchLog(
                    $emailLog
                )
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Automatic WhatsApp
        |--------------------------------------------------------------------------
        |
        | Do not send the WhatsApp confirmation before the badge exists,
        | because the approved Meta template uses the badge as its image
        | header.
        |
        */

        $whatsAppLog =
            $this->prepareRegistrationWhatsApp(
                $attendee
            );

        if ($whatsAppLog) {
            $logs->push(
                $this->dispatchLog(
                    $whatsAppLog
                )
            );
        }

        return $logs
            ->filter()
            ->values();
    }

    /*
    |--------------------------------------------------------------------------
    | Badge Ready Communication
    |--------------------------------------------------------------------------
    |
    | Call this after a badge has successfully been generated.
    |
    | This is important when badge generation happens asynchronously.
    |
    | Registration may happen first:
    |
    | Registration
    |     ↓
    | Badge job
    |     ↓
    | handleBadgeReady()
    |     ↓
    | Email registration confirmation (if not already sent)
    | WhatsApp registration confirmation
    |
    |--------------------------------------------------------------------------
    */

    public function handleBadgeReady(
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

        if (! $attendee->isApproved()) {
            return collect();
        }

        if (blank($attendee->badge_path)) {
            return collect();
        }

        $logs = collect();

        /*
        |--------------------------------------------------------------------------
        | Email
        |--------------------------------------------------------------------------
        |
        | This second attempt is intentional. If registration email was already
        | queued or sent, prepareCommunication() duplicate protection returns
        | null. If it was not sent earlier, it can now be queued with the badge
        | available for attachment by SendAutomaticCommunicationJob.
        |
        */

        $emailLog =
            $this->prepareRegistrationEmail(
                $attendee
            );

        if ($emailLog) {
            $logs->push(
                $this->dispatchLog(
                    $emailLog
                )
            );
        }

        /*
        |--------------------------------------------------------------------------
        | WhatsApp
        |--------------------------------------------------------------------------
        */

        $whatsAppLog =
            $this->prepareRegistrationWhatsApp(
                $attendee
            );

        if ($whatsAppLog) {
            $logs->push(
                $this->dispatchLog(
                    $whatsAppLog
                )
            );
        }

        return $logs
            ->filter()
            ->values();
    }

    /*
    |--------------------------------------------------------------------------
    | Prepare Registration SMS
    |--------------------------------------------------------------------------
    */

    protected function prepareRegistrationSms(
        Attendee $attendee
    ): ?CommunicationLog {
        $event = $attendee->event;

        if (! $event) {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | Event-level automatic SMS setting
        |--------------------------------------------------------------------------
        */

        if (! $event->shouldSendRegistrationSms()) {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | Selected SMS template
        |--------------------------------------------------------------------------
        */

        $template =
            $event->registrationSmsTemplate;

        if (! $template) {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | Validate SMS template
        |--------------------------------------------------------------------------
        */

        if (! $this->isValidRegistrationSmsTemplate(
            $attendee,
            $template
        )) {
            return null;
        }

        return $this->prepareCommunication(
            $attendee,
            $template
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Prepare Registration Email
    |--------------------------------------------------------------------------
    |
    | Uses the active organization communication template key:
    |
    | registration_confirmed_email
    |
    |--------------------------------------------------------------------------
    */

    protected function prepareRegistrationEmail(
        Attendee $attendee
    ): ?CommunicationLog {
        $event = $attendee->event;

        if (! $event) {
            return null;
        }

        if (! $attendee->isApproved()) {
            return null;
        }

        if (! $event->organization_id) {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | Valid email address required
        |--------------------------------------------------------------------------
        */

        $recipient =
            $this->emailRecipient(
                $attendee
            );

        if (blank($recipient)) {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | Registration confirmation email template
        |--------------------------------------------------------------------------
        */

        $template =
            CommunicationTemplate::query()
                ->where(
                    'organization_id',
                    $event->organization_id
                )
                ->where(
                    'channel',
                    CommunicationTemplate::CHANNEL_EMAIL
                )
                ->where(
                    'key',
                    self::TRIGGER_REGISTRATION_CONFIRMED
                        . '_email'
                )
                ->where(
                    'is_active',
                    true
                )
                ->first();

        if (! $template) {
            return null;
        }

        if (blank($template->body)) {
            return null;
        }

        return $this->prepareCommunication(
            $attendee,
            $template
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Prepare Registration WhatsApp
    |--------------------------------------------------------------------------
    |
    | This does not depend on a CommunicationTemplate database record.
    |
    | The approved Meta template is configured through:
    |
    | WHATSAPP_TEMPLATE_REGISTRATION_CONFIRMATION
    |
    |--------------------------------------------------------------------------
    */

    protected function prepareRegistrationWhatsApp(
        Attendee $attendee
    ): ?CommunicationLog {
        $event = $attendee->event;

        if (! $event) {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | WhatsApp must be configured
        |--------------------------------------------------------------------------
        */

        if (! $this->whatsAppService->isConfigured()) {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | Badge must exist
        |--------------------------------------------------------------------------
        */

        if (blank($attendee->badge_path)) {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | Valid phone number required
        |--------------------------------------------------------------------------
        */

        $recipient =
            $this->phoneRecipient(
                $attendee
            );

        if (blank($recipient)) {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | Approved Meta template
        |--------------------------------------------------------------------------
        */

        $templateName =
            config(
                'services.whatsapp.templates.registration_confirmation'
            );

        if (blank($templateName)) {
            return null;
        }

        /*
        |--------------------------------------------------------------------------
        | Human-readable log message
        |--------------------------------------------------------------------------
        |
        | The actual WhatsApp payload will be built by WhatsAppService.
        |
        | Meta variables:
        |
        | {{1}} Full name
        | {{2}} Event name
        | {{3}} Category
        | {{4}} Venue
        |
        |--------------------------------------------------------------------------
        */

        $category =
            $attendee->category?->name
            ?? 'Attendee';

        $venue =
            $event->venue
            ?? '-';

        $message = implode(
            PHP_EOL,
            [
                "Hello {$attendee->full_name},",
                '',
                "Your registration for {$event->name} has been completed successfully.",
                "Category: {$category}",
                "Venue: {$venue}",
                '',
                'Your digital badge is attached. Please keep it available for check-in during the event.',
                '',
                'Thank you.',
            ]
        );

        /*
        |--------------------------------------------------------------------------
        | Duplicate Protection
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
                    CommunicationTemplate::CHANNEL_WHATSAPP
                )
                ->where(
                    'recipient',
                    $recipient
                )
                ->where(
                    'subject',
                    $templateName
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
        | Create WhatsApp communication log
        |--------------------------------------------------------------------------
        |
        | subject stores the Meta template name.
        |
        | SendAutomaticCommunicationJob will use this when channel=whatsapp.
        |
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
                CommunicationTemplate::CHANNEL_WHATSAPP,

            'recipient' =>
                $recipient,

            'subject' =>
                $templateName,

            'message' =>
                trim($message),

            'status' =>
                CommunicationLog::STATUS_QUEUED,

            'queued_at' =>
                now(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Queue Communication
    |--------------------------------------------------------------------------
    */

    protected function dispatchLog(
        CommunicationLog $log
    ): CommunicationLog {
        try {
            $queue = match ($log->channel) {
                CommunicationTemplate::CHANNEL_EMAIL =>
                    'communications-email',

                CommunicationTemplate::CHANNEL_SMS =>
                    'communications-sms',

                CommunicationTemplate::CHANNEL_WHATSAPP =>
                    'communications-whatsapp',

                default =>
                    'default',
            };

            SendAutomaticCommunicationJob::dispatch(
                $log->id
            )->onQueue(
                $queue
            );

            return $log;
        } catch (Throwable $exception) {
            report(
                $exception
            );

            $log->markFailed(
                $exception->getMessage()
            );

            return $log->fresh();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Generic Trigger Dispatcher
    |--------------------------------------------------------------------------
    |
    | Reserved for future automatic communication:
    |
    | registration_received
    | registration_confirmed
    | registration_approved
    | waitlist
    | reminder
    | badge_ready
    | certificate_ready
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

            $logs->push(
                $this->dispatchLog(
                    $log
                )
            );
        }

        return $logs
            ->filter()
            ->values();
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
         * Template must belong to same organization.
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
         * Registration SMS must be SMS channel.
         */

        if (
            $template->channel
            !== CommunicationTemplate::CHANNEL_SMS
        ) {
            return false;
        }

        /*
         * Template must have message body.
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
                'key',
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
        | Resolve Recipient
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
        | Render Template
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
        | Create Communication Log
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