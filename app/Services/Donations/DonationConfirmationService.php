<?php

namespace App\Services\Donations;

use App\Jobs\SendDonationConfirmationJob;
use App\Models\CommunicationLog;
use App\Models\Donation;
use App\Services\PhoneNumberService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DonationConfirmationService
{
    public function __construct(
        protected PhoneNumberService $phoneNumberService
    ) {
    }

    public function availableChannels(Donation $donation): array
    {
        $donation->loadMissing('campaign');

        $settings = $donation->campaign?->notification_settings ?? [];
        $channels = [];

        if (
            (bool) ($settings['email'] ?? false)
            && $this->validEmail($donation->donor_email) !== null
        ) {
            $channels[] = CommunicationLog::CHANNEL_EMAIL;
        }

        if (
            (bool) ($settings['sms'] ?? false)
            && $this->normalizedPhone($donation->donor_phone) !== null
        ) {
            $channels[] = CommunicationLog::CHANNEL_SMS;
        }

        if (
            (bool) ($settings['whatsapp'] ?? false)
            && $this->normalizedPhone($donation->donor_phone) !== null
        ) {
            $channels[] = CommunicationLog::CHANNEL_WHATSAPP;
        }

        return $channels;
    }

    public function queue(
        Donation $donation,
        ?array $requestedChannels = null
    ): Collection {
        $donation->loadMissing('campaign');

        if (! $donation->isCompleted()) {
            return collect();
        }

        $available = $this->availableChannels($donation);

        if ($requestedChannels !== null) {
            $available = array_values(array_intersect(
                $available,
                $requestedChannels
            ));
        }

        $logs = collect();

        foreach ($available as $channel) {
            $log = DB::transaction(function () use ($donation, $channel): ?CommunicationLog {
                $lockedDonation = Donation::query()
                    ->with('campaign')
                    ->lockForUpdate()
                    ->find($donation->id);

                if (! $lockedDonation || ! $lockedDonation->isCompleted()) {
                    return null;
                }

                $existing = CommunicationLog::query()
                    ->where('donation_id', $lockedDonation->id)
                    ->where('purpose', CommunicationLog::PURPOSE_DONATION_CONFIRMATION)
                    ->where('channel', $channel)
                    ->first();

                if ($existing) {
                    return null;
                }

                $recipient = $channel === CommunicationLog::CHANNEL_EMAIL
                    ? $this->validEmail($lockedDonation->donor_email)
                    : $this->normalizedPhone($lockedDonation->donor_phone);

                if ($recipient === null) {
                    return null;
                }

                return CommunicationLog::query()->create([
                    'event_id' => $lockedDonation->campaign?->event_id,
                    'donation_id' => $lockedDonation->id,
                    'purpose' => CommunicationLog::PURPOSE_DONATION_CONFIRMATION,
                    'channel' => $channel,
                    'recipient' => $recipient,
                    'subject' => $channel === CommunicationLog::CHANNEL_EMAIL
                        ? 'Donation confirmation'
                        : null,
                    'message' => $this->message($lockedDonation),
                    'status' => CommunicationLog::STATUS_QUEUED,
                    'queued_at' => now(),
                ]);
            }, attempts: 3);

            if (! $log) {
                continue;
            }

            SendDonationConfirmationJob::dispatch($log->id)
                ->onQueue($this->queueForChannel($channel));

            $logs->push($log);
        }

        return $logs;
    }

    private function message(Donation $donation): string
    {
        $campaignTitle = $donation->campaign?->title ?? 'our campaign';

        return sprintf(
            'Thank you for your donation of %s %s to %s. Reference: %s. Status: Completed.',
            strtoupper((string) $donation->currency),
            number_format((float) $donation->amount, 2, '.', ','),
            $campaignTitle,
            $donation->reference
        );
    }

    private function validEmail(?string $email): ?string
    {
        $email = mb_strtolower(trim((string) $email));

        return filter_var($email, FILTER_VALIDATE_EMAIL)
            ? $email
            : null;
    }

    private function normalizedPhone(?string $phone): ?string
    {
        if (blank($phone)) {
            return null;
        }

        try {
            $phone = $this->phoneNumberService->normalize($phone);
        } catch (InvalidArgumentException) {
            return null;
        }

        return filled($phone) ? $phone : null;
    }

    private function queueForChannel(string $channel): string
    {
        return match ($channel) {
            CommunicationLog::CHANNEL_WHATSAPP => 'communications-whatsapp',
            CommunicationLog::CHANNEL_SMS => 'communications-sms',
            default => 'communications-email',
        };
    }
}
