<?php

namespace App\Services;

use App\Jobs\SendAutomaticCommunicationJob;
use App\Models\Attendee;
use App\Models\CommunicationLog;
use RuntimeException;
use Throwable;

class BadgeDeliveryService
{
    public function __construct(
        protected BadgeGenerationService $badgeGenerationService,
        protected SmsService $smsService,
        protected WhatsAppService $whatsAppService,
    ) {
    }

    /**
     * Queue a fresh badge delivery without regenerating an existing QR/badge.
     *
     * If the attendee has no badge yet, generate it once first. Existing badges
     * are always reused so a resend never rotates the attendee's QR token.
     */
    public function resend(
        Attendee $attendee,
        string $channel
    ): CommunicationLog {
        $attendee->loadMissing([
            'event',
            'category',
            'badgeType',
            'qrToken',
        ]);

        if (! $attendee->event) {
            throw new RuntimeException(
                'The attendee does not belong to an event.'
            );
        }

        if (! $attendee->isApproved()) {
            throw new RuntimeException(
                'Badges can only be sent to approved or registered attendees.'
            );
        }

        if (blank($attendee->badge_path)) {
            $this->badgeGenerationService->generateForAttendee(
                $attendee
            );

            $attendee->refresh()->loadMissing([
                'event',
                'category',
                'badgeType',
                'qrToken',
            ]);
        }

        if (blank($attendee->badge_path)) {
            throw new RuntimeException(
                'The attendee badge could not be generated.'
            );
        }

        [$recipient, $subject, $message] =
            $this->deliveryPayload(
                $attendee,
                $channel
            );

        $log = CommunicationLog::create([
            'event_id' =>
                $attendee->event_id,

            'attendee_id' =>
                $attendee->id,

            'communication_campaign_id' =>
                null,

            'purpose' =>
                CommunicationLog::PURPOSE_BADGE_RESEND,

            'channel' =>
                $channel,

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

        try {
            SendAutomaticCommunicationJob::dispatch(
                $log->id
            )->onQueue(
                $this->queueForChannel(
                    $channel
                )
            );
        } catch (Throwable $exception) {
            report($exception);

            $log->markFailed(
                $exception->getMessage()
            );

            throw $exception;
        }

        return $log->fresh();
    }

    /**
     * Channels that can be used for this attendee right now.
     */
    public function availableChannels(
        Attendee $attendee
    ): array {
        $channels = [];

        $phone =
            $this->smsService->normalizeRecipient(
                (string) $attendee->phone
            );

        if (
            filled($phone)
            && $this->smsService->isValidRecipient(
                $phone
            )
        ) {
            if (
                $this->whatsAppService->isConfigured()
            ) {
                $channels[
                    CommunicationLog::CHANNEL_WHATSAPP
                ] = 'WhatsApp';
            }

            if (filled($attendee->public_token)) {
                $channels[
                    CommunicationLog::CHANNEL_SMS
                ] = 'SMS';
            }
        }

        $email =
            strtolower(
                trim(
                    (string) $attendee->email
                )
            );

        if (
            filled($email)
            && filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {
            $channels[
                CommunicationLog::CHANNEL_EMAIL
            ] = 'Email';
        }

        return $channels;
    }

    protected function deliveryPayload(
        Attendee $attendee,
        string $channel
    ): array {
        $event =
            $attendee->event;

        $publicUrl =
            filled($attendee->public_token)
                ? $attendee->publicUrl()
                : null;

        return match ($channel) {
            CommunicationLog::CHANNEL_WHATSAPP =>
                $this->whatsAppPayload(
                    $attendee
                ),

            CommunicationLog::CHANNEL_EMAIL =>
                $this->emailPayload(
                    $attendee,
                    $publicUrl
                ),

            CommunicationLog::CHANNEL_SMS =>
                $this->smsPayload(
                    $attendee,
                    $publicUrl
                ),

            default =>
                throw new RuntimeException(
                    "Unsupported badge delivery channel: {$channel}."
                ),
        };
    }

    protected function whatsAppPayload(
        Attendee $attendee
    ): array {
        $phone =
            $this->smsService->normalizeRecipient(
                (string) $attendee->phone
            );

        if (
            blank($phone)
            || ! $this->smsService->isValidRecipient(
                $phone
            )
        ) {
            throw new RuntimeException(
                'The attendee does not have a valid WhatsApp phone number.'
            );
        }

        if (! $this->whatsAppService->isConfigured()) {
            throw new RuntimeException(
                'WhatsApp Cloud API is not configured.'
            );
        }

        $template =
            config(
                'services.whatsapp.templates.registration_confirmation'
            );

        if (blank($template)) {
            throw new RuntimeException(
                'The WhatsApp badge template is not configured.'
            );
        }

        $event =
            $attendee->event;

        $category =
            $attendee->category?->name
            ?? 'Attendee';

        $venue =
            $event?->venue
            ?? '-';

        $message = implode(
            PHP_EOL,
            [
                "Hello {$attendee->full_name},",
                '',
                "Your digital badge for {$event->name} has been resent.",
                "Category: {$category}",
                "Venue: {$venue}",
                '',
                'Please keep the badge available for event check-in.',
            ]
        );

        return [
            $phone,
            (string) $template,
            $message,
        ];
    }

    protected function emailPayload(
        Attendee $attendee,
        ?string $publicUrl
    ): array {
        $email =
            strtolower(
                trim(
                    (string) $attendee->email
                )
            );

        if (
            blank($email)
            || ! filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {
            throw new RuntimeException(
                'The attendee does not have a valid email address.'
            );
        }

        $event =
            $attendee->event;

        $lines = [
            "Hello {$attendee->full_name},",
            '',
            "Your digital badge for {$event->name} has been resent.",
        ];

        if (filled($publicUrl)) {
            $lines[] = '';
            $lines[] =
                "You can also view your badge and registration status here: {$publicUrl}";
        }

        $lines[] = '';
        $lines[] =
            'Please keep your badge available for event check-in.';

        return [
            $email,
            "Your Event Badge - {$event->name}",
            implode(
                PHP_EOL,
                $lines
            ),
        ];
    }

    protected function smsPayload(
        Attendee $attendee,
        ?string $publicUrl
    ): array {
        if (blank($publicUrl)) {
            throw new RuntimeException(
                'A public badge link is required before sending the badge by SMS.'
            );
        }

        $phone =
            $this->smsService->normalizeRecipient(
                (string) $attendee->phone
            );

        if (
            blank($phone)
            || ! $this->smsService->isValidRecipient(
                $phone
            )
        ) {
            throw new RuntimeException(
                'The attendee does not have a valid SMS phone number.'
            );
        }

        $event =
            $attendee->event;

        return [
            $phone,
            null,
            "Hello {$attendee->full_name}, your badge for {$event->name} is ready: {$publicUrl}",
        ];
    }

    protected function queueForChannel(
        string $channel
    ): string {
        return match ($channel) {
            CommunicationLog::CHANNEL_EMAIL =>
                'communications-email',

            CommunicationLog::CHANNEL_SMS =>
                'communications-sms',

            CommunicationLog::CHANNEL_WHATSAPP =>
                'communications-whatsapp',

            default =>
                'default',
        };
    }
}
