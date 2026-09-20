<?php

namespace App\Services\Tickets;

use App\Jobs\SendTicketAccessLinkJob;
use App\Models\CommunicationLog;
use App\Models\TicketOrder;
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

    public function queueAutomatic(
        TicketOrder $order
    ): void {
        /** @var array<int, CommunicationLog> $newLogs */
        $newLogs = DB::transaction(
            function () use ($order): array {
                $lockedOrder = TicketOrder::query()
                    ->lockForUpdate()
                    ->find($order->id);

                if (
                    ! $lockedOrder
                    || ! $lockedOrder->isPaid()
                ) {
                    return [];
                }

                $logs = [];

                $email = $this->validEmail(
                    $lockedOrder->buyer_email
                );

                if ($email !== null) {
                    $log = $this->createAutomaticLog(
                        $lockedOrder,
                        CommunicationLog::CHANNEL_EMAIL,
                        $email
                    );

                    if ($log !== null) {
                        $logs[] = $log;
                    }
                }

                $phone = $this->normalizedPhone(
                    $lockedOrder->buyer_phone
                );

                if ($phone === null) {
                    return $logs;
                }

                if ($this->whatsAppService->isConfigured()) {
                    $whatsAppLog = $this->createAutomaticLog(
                        $lockedOrder,
                        CommunicationLog::CHANNEL_WHATSAPP,
                        $phone
                    );

                    if ($whatsAppLog !== null) {
                        $logs[] = $whatsAppLog;
                    }
                }

                $smsLog = $this->createAutomaticLog(
                    $lockedOrder,
                    CommunicationLog::CHANNEL_SMS,
                    $phone
                );

                if ($smsLog !== null) {
                    $logs[] = $smsLog;
                }

                return $logs;
            },
            attempts: 3
        );

        foreach ($newLogs as $log) {
            SendTicketAccessLinkJob::dispatch(
                $log->id
            )->onQueue(
                $this->queueForChannel(
                    $log->channel
                )
            );
        }
    }

    public function queueSmsFallback(
        CommunicationLog $whatsAppLog
    ): ?CommunicationLog {
        if (
            ! $whatsAppLog->isWhatsApp()
            || $whatsAppLog->purpose
                !== CommunicationLog::PURPOSE_TICKET_ACCESS
        ) {
            return null;
        }

        $phone = $this->normalizedPhone(
            $whatsAppLog->recipient
        );

        if ($phone === null) {
            return null;
        }

        $created = false;

        $smsLog = DB::transaction(
            function () use (
                $whatsAppLog,
                $phone,
                &$created
            ): ?CommunicationLog {
                $order = TicketOrder::query()
                    ->lockForUpdate()
                    ->find(
                        $whatsAppLog->ticket_order_id
                    );

                if (
                    ! $order
                    || ! $order->isPaid()
                ) {
                    return null;
                }

                $existing = CommunicationLog::query()
                    ->where(
                        'ticket_order_id',
                        $order->id
                    )
                    ->where(
                        'purpose',
                        CommunicationLog::PURPOSE_TICKET_ACCESS
                    )
                    ->where(
                        'channel',
                        CommunicationLog::CHANNEL_SMS
                    )
                    ->first();

                if ($existing) {
                    return $existing;
                }

                $created = true;

                return CommunicationLog::query()->create([
                    'event_id' => $order->event_id,
                    'ticket_order_id' => $order->id,
                    'purpose' =>
                        CommunicationLog::PURPOSE_TICKET_ACCESS,
                    'channel' =>
                        CommunicationLog::CHANNEL_SMS,
                    'recipient' => $phone,
                    'message' =>
                        "Secure ticket access link for order {$order->order_number}",
                    'status' =>
                        CommunicationLog::STATUS_QUEUED,
                    'queued_at' => now(),
                ]);
            },
            attempts: 3
        );

        if (
            $created
            && $smsLog
        ) {
            SendTicketAccessLinkJob::dispatch(
                $smsLog->id
            )->onQueue(
                'communications-sms'
            );
        }

        return $smsLog;
    }

    private function createAutomaticLog(
        TicketOrder $order,
        string $channel,
        string $recipient
    ): ?CommunicationLog {
        $existing = CommunicationLog::query()
            ->where(
                'ticket_order_id',
                $order->id
            )
            ->where(
                'purpose',
                CommunicationLog::PURPOSE_TICKET_ACCESS
            )
            ->where(
                'channel',
                $channel
            )
            ->first();

        if ($existing) {
            return null;
        }

        return CommunicationLog::query()->create([
            'event_id' => $order->event_id,
            'ticket_order_id' => $order->id,
            'purpose' =>
                CommunicationLog::PURPOSE_TICKET_ACCESS,
            'channel' => $channel,
            'recipient' => $recipient,
            'subject' => $channel
                === CommunicationLog::CHANNEL_EMAIL
                    ? 'Ticket delivery'
                    : null,
            'message' =>
                "Secure ticket access link for order {$order->order_number}",
            'status' => CommunicationLog::STATUS_QUEUED,
            'queued_at' => now(),
        ]);
    }

    private function validEmail(
        ?string $email
    ): ?string {
        $email = mb_strtolower(
            trim((string) $email)
        );

        return filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
            ? $email
            : null;
    }

    private function normalizedPhone(
        ?string $phone
    ): ?string {
        if (blank($phone)) {
            return null;
        }

        try {
            $phone = $this->phoneNumberService
                ->normalize($phone);
        } catch (InvalidArgumentException) {
            return null;
        }

        return filled($phone)
            ? $phone
            : null;
    }

    private function queueForChannel(
        string $channel
    ): string {
        return match ($channel) {
            CommunicationLog::CHANNEL_WHATSAPP =>
                'communications-whatsapp',

            CommunicationLog::CHANNEL_SMS =>
                'communications-sms',

            default =>
                'communications-email',
        };
    }
}
