<?php

namespace App\Services\Tickets;

use App\Jobs\SendTicketAccessLinkJob;
use App\Models\CommunicationLog;
use App\Models\TicketOrder;
use App\Models\TicketUpgrade;
use App\Services\PhoneNumberService;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TicketAccessDeliveryService
{
    public function __construct(
        private readonly PhoneNumberService $phoneNumberService,
        private readonly WhatsAppService $whatsAppService
    ) {
    }

    public function queueAutomatic(TicketOrder $order): void
    {
        /** @var array<int, CommunicationLog> $newLogs */
        $newLogs = DB::transaction(function () use ($order): array {
            $lockedOrder = TicketOrder::query()
                ->lockForUpdate()
                ->find($order->id);

            if (! $lockedOrder || ! $lockedOrder->isPaid()) {
                return [];
            }

            return $this->buildDeliveryLogs(
                $lockedOrder,
                CommunicationLog::PURPOSE_TICKET_ACCESS
            );
        }, attempts: 3);

        $this->dispatchLogs($newLogs);
    }

    public function queueUpgradeCompleted(TicketUpgrade $upgrade): void
    {
        /** @var array<int, CommunicationLog> $newLogs */
        $newLogs = DB::transaction(function () use ($upgrade): array {
            $lockedUpgrade = TicketUpgrade::query()
                ->with(['order'])
                ->lockForUpdate()
                ->find($upgrade->id);

            if (! $lockedUpgrade || ! $lockedUpgrade->isCompleted()) {
                return [];
            }

            $order = $lockedUpgrade->order;

            if (! $order || ! $order->isPaid()) {
                return [];
            }

            return $this->buildDeliveryLogs(
                $order,
                CommunicationLog::PURPOSE_TICKET_UPGRADE_COMPLETED,
                $lockedUpgrade
            );
        }, attempts: 3);

        $this->dispatchLogs($newLogs);
    }

    public function queueSmsFallback(CommunicationLog $whatsAppLog): ?CommunicationLog
    {
        if (
            ! $whatsAppLog->isWhatsApp()
            || ! in_array($whatsAppLog->purpose, [
                CommunicationLog::PURPOSE_TICKET_ACCESS,
                CommunicationLog::PURPOSE_TICKET_UPGRADE_COMPLETED,
            ], true)
        ) {
            return null;
        }

        $phone = $this->normalizedPhone($whatsAppLog->recipient);

        if ($phone === null) {
            return null;
        }

        $created = false;

        $smsLog = DB::transaction(function () use ($whatsAppLog, $phone, &$created): ?CommunicationLog {
            $order = TicketOrder::query()
                ->lockForUpdate()
                ->find($whatsAppLog->ticket_order_id);

            if (! $order || ! $order->isPaid()) {
                return null;
            }

            $query = CommunicationLog::query()
                ->where('ticket_order_id', $order->id)
                ->where('purpose', $whatsAppLog->purpose)
                ->where('channel', CommunicationLog::CHANNEL_SMS);

            if ($whatsAppLog->ticket_upgrade_id) {
                $query->where('ticket_upgrade_id', $whatsAppLog->ticket_upgrade_id);
            }

            $existing = $query->first();

            if ($existing) {
                return $existing;
            }

            $created = true;

            return CommunicationLog::query()->create([
                'event_id' => $order->event_id,
                'ticket_order_id' => $order->id,
                'ticket_upgrade_id' => $whatsAppLog->ticket_upgrade_id,
                'purpose' => $whatsAppLog->purpose,
                'channel' => CommunicationLog::CHANNEL_SMS,
                'recipient' => $phone,
                'message' => $whatsAppLog->purpose === CommunicationLog::PURPOSE_TICKET_UPGRADE_COMPLETED
                    ? "Your upgraded ticket is ready for order {$order->order_number}"
                    : "Secure ticket access link for order {$order->order_number}",
                'status' => CommunicationLog::STATUS_QUEUED,
                'queued_at' => now(),
            ]);
        }, attempts: 3);

        if ($created && $smsLog) {
            SendTicketAccessLinkJob::dispatch($smsLog->id)
                ->onQueue('communications-sms');
        }

        return $smsLog;
    }

    private function buildDeliveryLogs(
        TicketOrder $order,
        string $purpose,
        ?TicketUpgrade $upgrade = null
    ): array {
        $logs = [];

        $email = $this->validEmail($order->buyer_email);
        if ($email !== null) {
            $log = $this->createLog($order, $purpose, CommunicationLog::CHANNEL_EMAIL, $email, $upgrade);
            if ($log !== null) {
                $logs[] = $log;
            }
        }

        $phone = $this->normalizedPhone($order->buyer_phone);
        if ($phone === null) {
            return $logs;
        }

        if ($this->whatsAppService->isConfigured()) {
            $log = $this->createLog($order, $purpose, CommunicationLog::CHANNEL_WHATSAPP, $phone, $upgrade);
            if ($log !== null) {
                $logs[] = $log;
            }
        }

        $log = $this->createLog($order, $purpose, CommunicationLog::CHANNEL_SMS, $phone, $upgrade);
        if ($log !== null) {
            $logs[] = $log;
        }

        return $logs;
    }

    private function createLog(
        TicketOrder $order,
        string $purpose,
        string $channel,
        string $recipient,
        ?TicketUpgrade $upgrade = null
    ): ?CommunicationLog {
        $query = CommunicationLog::query()
            ->where('ticket_order_id', $order->id)
            ->where('purpose', $purpose)
            ->where('channel', $channel);

        if ($upgrade) {
            $query->where('ticket_upgrade_id', $upgrade->id);
        }

        if ($query->exists()) {
            return null;
        }

        $isUpgrade = $purpose === CommunicationLog::PURPOSE_TICKET_UPGRADE_COMPLETED;

        return CommunicationLog::query()->create([
            'event_id' => $order->event_id,
            'ticket_order_id' => $order->id,
            'ticket_upgrade_id' => $upgrade?->id,
            'purpose' => $purpose,
            'channel' => $channel,
            'recipient' => $recipient,
            'subject' => $channel === CommunicationLog::CHANNEL_EMAIL
                ? ($isUpgrade ? 'Your upgraded ticket is ready' : 'Ticket delivery')
                : null,
            'message' => $isUpgrade
                ? "Your upgraded ticket is ready for order {$order->order_number}"
                : "Secure ticket access link for order {$order->order_number}",
            'status' => CommunicationLog::STATUS_QUEUED,
            'queued_at' => now(),
        ]);
    }

    private function dispatchLogs(array $logs): void
    {
        foreach ($logs as $log) {
            SendTicketAccessLinkJob::dispatch($log->id)
                ->onQueue($this->queueForChannel($log->channel));
        }
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
}
