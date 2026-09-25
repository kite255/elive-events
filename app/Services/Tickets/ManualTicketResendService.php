<?php

namespace App\Services\Tickets;

use App\Jobs\SendTicketAccessLinkJob;
use App\Models\CommunicationLog;
use App\Models\TicketOrder;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\PhoneNumberService;
use App\Services\WhatsAppService;
use InvalidArgumentException;
use RuntimeException;

class ManualTicketResendService
{
    public function __construct(
        private readonly PhoneNumberService $phoneNumberService,
        private readonly WhatsAppService $whatsAppService,
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function availableChannels(TicketOrder $order): array
    {
        $channels = [];

        if ($this->validEmail($order->buyer_email) !== null) {
            $channels[CommunicationLog::CHANNEL_EMAIL] = 'Email';
        }

        if ($this->normalizedPhone($order->buyer_phone) !== null) {
            $channels[CommunicationLog::CHANNEL_SMS] = 'SMS';

            if ($this->whatsAppService->isConfigured()) {
                $channels[CommunicationLog::CHANNEL_WHATSAPP] = 'WhatsApp';
            }
        }

        return $channels;
    }

    public function queue(TicketOrder $order, array $channels, User $actor): array
    {
        $order->loadMissing('event');

        if (! $order->event || ! $this->actorCanResend($actor, $order)) {
            throw new RuntimeException('You are not allowed to resend tickets for this event.');
        }

        if (! $order->isPaid()) {
            throw new RuntimeException('Only paid ticket orders can be resent.');
        }

        $available = $this->availableChannels($order);
        $requested = array_values(array_unique(array_map('strval', $channels)));

        if ($requested === []) {
            throw new RuntimeException('Select at least one delivery channel.');
        }

        $queued = [];
        $skipped = [];

        foreach ($requested as $channel) {
            if (! array_key_exists($channel, $available)) {
                $skipped[] = $channel;
                continue;
            }

            $recipient = $channel === CommunicationLog::CHANNEL_EMAIL
                ? $this->validEmail($order->buyer_email)
                : $this->normalizedPhone($order->buyer_phone);

            if ($recipient === null) {
                $skipped[] = $channel;
                continue;
            }

            $log = CommunicationLog::query()->create([
                'event_id' => $order->event_id,
                'ticket_order_id' => $order->id,
                'purpose' => CommunicationLog::PURPOSE_TICKET_ACCESS_RECOVERY,
                'channel' => $channel,
                'recipient' => $recipient,
                'subject' => $channel === CommunicationLog::CHANNEL_EMAIL ? 'Ticket delivery' : null,
                'message' => "Secure ticket access link for order {$order->order_number}",
                'status' => CommunicationLog::STATUS_QUEUED,
                'queued_at' => now(),
            ]);

            SendTicketAccessLinkJob::dispatch($log->id)
                ->onQueue($this->queueForChannel($channel));

            $queued[] = $channel;
        }

        if ($queued !== []) {
            $this->auditLogService->record(
                'ticket.access_resent',
                $order,
                $actor,
                [
                    'channels' => $queued,
                    'order_reference' => $order->order_number,
                ]
            );
        }

        return [
            'queued' => $queued,
            'skipped' => $skipped,
        ];
    }

    public function maskedContact(TicketOrder $order): array
    {
        return [
            'email' => $this->maskEmail($order->buyer_email),
            'phone' => $this->maskPhone($order->buyer_phone),
        ];
    }

    private function actorCanResend(User $actor, TicketOrder $order): bool
    {
        if ($actor->isSuperAdmin()) {
            return true;
        }

        if ($actor->isTicketOrganizer()) {
            return $actor->assignedTicketingEvents()
                ->where('events.id', $order->event_id)
                ->exists();
        }

        return $order->event?->canBeManagedBy($actor) ?? false;
    }

    private function validEmail(?string $email): ?string
    {
        $email = mb_strtolower(trim((string) $email));

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
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

    private function maskEmail(?string $email): string
    {
        $email = trim((string) $email);

        if (! str_contains($email, '@')) {
            return '—';
        }

        [$name, $domain] = explode('@', $email, 2);
        $prefix = mb_substr($name, 0, min(2, mb_strlen($name)));

        return $prefix . str_repeat('*', max(2, mb_strlen($name) - 2)) . '@' . $domain;
    }

    private function maskPhone(?string $phone): string
    {
        $phone = preg_replace('/\D+/', '', (string) $phone) ?: '';

        if (strlen($phone) < 4) {
            return '—';
        }

        return str_repeat('*', max(0, strlen($phone) - 4)) . substr($phone, -4);
    }
}
