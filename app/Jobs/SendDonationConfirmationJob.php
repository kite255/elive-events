<?php

namespace App\Jobs;

use App\Models\CommunicationLog;
use App\Services\PhoneNumberService;
use App\Services\SmsService;
use App\Services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class SendDonationConfirmationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(
        public int $communicationLogId
    ) {
    }

    public function handle(
        SmsService $smsService,
        PhoneNumberService $phoneNumberService,
        WhatsAppService $whatsAppService
    ): void {
        $log = CommunicationLog::query()
            ->with('donation.campaign')
            ->find($this->communicationLogId);

        if (
            ! $log
            || $log->isSent()
            || $log->purpose !== CommunicationLog::PURPOSE_DONATION_CONFIRMATION
        ) {
            return;
        }

        $donation = $log->donation;

        if (! $donation || ! $donation->isCompleted()) {
            return;
        }

        if (! $this->recipientStillBelongsToDonation(
            $log,
            $phoneNumberService
        )) {
            return;
        }

        try {
            $log->markSending();
            $log->increment('attempt_count');

            $providerMessageId = match ($log->channel) {
                CommunicationLog::CHANNEL_EMAIL => $this->sendEmail($log),
                CommunicationLog::CHANNEL_SMS => $this->sendSms(
                    $log,
                    $smsService
                ),
                CommunicationLog::CHANNEL_WHATSAPP => $this->sendWhatsApp(
                    $log,
                    $whatsAppService
                ),
                default => throw new RuntimeException(
                    'Unsupported donation confirmation channel.'
                ),
            };

            $log->markSent($providerMessageId);
        } catch (Throwable $exception) {
            $log->markFailed($exception->getMessage());
            throw $exception;
        }
    }

    private function sendEmail(
        CommunicationLog $log
    ): ?string {
        $subject = $log->subject ?: 'Donation confirmation';
        $body = (string) $log->message;

        Mail::raw(
            $body,
            function (Message $message) use ($log, $subject): void {
                $message
                    ->to($log->recipient)
                    ->subject($subject);
            }
        );

        return null;
    }

    private function sendSms(
        CommunicationLog $log,
        SmsService $smsService
    ): ?string {
        $result = $smsService->send(
            $log->recipient,
            (string) $log->message
        );

        return $result['provider_message_id'] ?? null;
    }

    private function sendWhatsApp(
        CommunicationLog $log,
        WhatsAppService $whatsAppService
    ): ?string {
        $donation = $log->donation;

        $result = $whatsAppService->sendTemplate(
            phone: $log->recipient,
            templateName: config(
                'services.whatsapp.templates.donation_confirmation',
                'donation_confirmation_en'
            ),
            languageCode: config(
                'services.whatsapp.default_language',
                'en'
            ),
            bodyParameters: [
                trim((string) $donation?->donor_name) ?: 'Donor',
                $donation?->campaign?->title ?? 'Donation campaign',
                strtoupper((string) $donation?->currency)
                    . ' '
                    . number_format((float) $donation?->amount, 2, '.', ','),
                (string) $donation?->reference,
            ],
        );

        if (! ($result['success'] ?? false)) {
            throw new RuntimeException(
                'WhatsApp provider did not confirm donation message submission.'
            );
        }

        return $result['provider_message_id'] ?? null;
    }

    private function recipientStillBelongsToDonation(
        CommunicationLog $log,
        PhoneNumberService $phoneNumberService
    ): bool {
        $donation = $log->donation;

        if (! $donation) {
            return false;
        }

        if ($log->isEmail()) {
            if (blank($donation->donor_email) || blank($log->recipient)) {
                return false;
            }

            return hash_equals(
                mb_strtolower(trim((string) $donation->donor_email)),
                mb_strtolower(trim((string) $log->recipient))
            );
        }

        if (! $log->isSms() && ! $log->isWhatsApp()) {
            return false;
        }

        try {
            $savedPhone = $phoneNumberService->normalize(
                $donation->donor_phone
            );
            $logPhone = $phoneNumberService->normalize(
                $log->recipient
            );
        } catch (InvalidArgumentException) {
            return false;
        }

        return filled($savedPhone)
            && filled($logPhone)
            && hash_equals($savedPhone, $logPhone);
    }

    public function failed(?Throwable $exception): void
    {
        $log = CommunicationLog::query()
            ->find($this->communicationLogId);

        if (! $log || $log->isSent()) {
            return;
        }

        $log->markFailed(
            $exception?->getMessage()
            ?: 'Donation confirmation delivery failed.'
        );

        if ($exception) {
            report($exception);
        }
    }
}
