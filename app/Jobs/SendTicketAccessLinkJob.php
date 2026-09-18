<?php

namespace App\Jobs;

use App\Models\CommunicationLog;
use App\Models\TicketOrder;
use App\Services\PhoneNumberService;
use App\Services\SmsService;
use App\Services\Tickets\TicketAccessDeliveryService;
use App\Services\Tickets\TicketAccessMessageFactory;
use App\Services\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class SendTicketAccessLinkJob implements ShouldQueue
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
        WhatsAppService $whatsAppService,
        TicketAccessMessageFactory $messageFactory
    ): void {
        $log = CommunicationLog::query()
            ->with([
                'ticketOrder.event.organization',
                'ticketOrder.event.ticketDeliveryEmailTemplate',
                'ticketOrder.event.ticketDeliverySmsTemplate',
            ])
            ->find($this->communicationLogId);

        if (! $log || $log->isSent()) {
            return;
        }

        $order = $log->ticketOrder;

        if (! $order || ! $order->isPaid()) {
            return;
        }

        if (
            ! $this->recipientStillBelongsToOrder(
                $log,
                $order,
                $phoneNumberService
            )
        ) {
            return;
        }

        $url = route(
            'public.ticket-orders.show',
            [
                'token' => $order->public_token,
            ]
        );

        $messages = $messageFactory->make(
            $order,
            $url
        );

        try {
            $log->markSending();
            $log->increment('attempt_count');

            $providerMessageId = match ($log->channel) {
                CommunicationLog::CHANNEL_EMAIL =>
                    $this->sendEmail(
                        $log->recipient,
                        $messages['email_subject'],
                        $messages['email_body'],
                        $order,
                        $url
                    ),

                CommunicationLog::CHANNEL_SMS =>
                    $this->sendSms(
                        $log->recipient,
                        $messages['sms_body'],
                        $smsService
                    ),

                CommunicationLog::CHANNEL_WHATSAPP =>
                    $this->sendWhatsApp(
                        $log->recipient,
                        $order,
                        $whatsAppService
                    ),

                default =>
                    throw new RuntimeException(
                        'Unsupported ticket access delivery channel.'
                    ),
            };

            $log->markSent(
                $providerMessageId
            );
        } catch (Throwable $exception) {
            $log->markFailed(
                $exception->getMessage()
            );

            throw $exception;
        }
    }

    private function sendEmail(
        string $recipient,
        string $subject,
        string $body,
        TicketOrder $order,
        string $ticketsUrl
    ): ?string {
        Mail::send(
            'emails.ticket-access',
            [
                'subject' => $subject,
                'body' => $body,
                'order' => $order,
                'event' => $order->event,
                'ticketsUrl' => $ticketsUrl,
            ],
            function (Message $message) use (
                $recipient,
                $subject
            ): void {
                $message
                    ->to($recipient)
                    ->subject($subject);
            }
        );

        return null;
    }

    private function sendSms(
        string $recipient,
        string $message,
        SmsService $smsService
    ): ?string {
        $result = $smsService->send(
            $recipient,
            $message
        );

        return $result['provider_message_id']
            ?? null;
    }

    private function sendWhatsApp(
        string $recipient,
        TicketOrder $order,
        WhatsAppService $whatsAppService
    ): ?string {
        $result = $whatsAppService->sendTemplate(
            phone: $recipient,
            templateName: config(
                'services.whatsapp.templates.ticket_access',
                'concert_tickets_delivery_en'
            ),
            languageCode: config(
                'services.whatsapp.default_language',
                'en'
            ),
            bodyParameters: [
                trim((string) $order->buyer_name)
                    ?: 'Customer',
                $order->event?->name
                    ?? 'eLive Event',
                (string) ((int) $order->quantity),
                (string) $order->order_number,
            ],
            imageUrl: null,
            urlButtons: [
                [
                    'index' => 0,
                    'value' => $order->public_token,
                ],
            ],
        );

        if (! ($result['success'] ?? false)) {
            throw new RuntimeException(
                'WhatsApp provider did not confirm message submission.'
            );
        }

        return $result['provider_message_id']
            ?? null;
    }

    private function recipientStillBelongsToOrder(
        CommunicationLog $log,
        TicketOrder $order,
        PhoneNumberService $phoneNumberService
    ): bool {
        if ($log->isEmail()) {
            if (
                blank($order->buyer_email)
                || blank($log->recipient)
            ) {
                return false;
            }

            return hash_equals(
                mb_strtolower(
                    trim((string) $order->buyer_email)
                ),
                mb_strtolower(
                    trim((string) $log->recipient)
                )
            );
        }

        if (
            ! $log->isSms()
            && ! $log->isWhatsApp()
        ) {
            return false;
        }

        try {
            $savedPhone = $phoneNumberService->normalize(
                $order->buyer_phone
            );

            $logPhone = $phoneNumberService->normalize(
                $log->recipient
            );
        } catch (InvalidArgumentException) {
            return false;
        }

        return filled($savedPhone)
            && filled($logPhone)
            && hash_equals(
                $savedPhone,
                $logPhone
            );
    }

    public function failed(
        ?Throwable $exception
    ): void {
        $log = CommunicationLog::query()
            ->find($this->communicationLogId);

        if (! $log || $log->isSent()) {
            return;
        }

        $log->markFailed(
            $exception?->getMessage()
                ?: 'Ticket access delivery failed.'
        );

        if ($log->isWhatsApp()) {
            app(TicketAccessDeliveryService::class)
                ->queueSmsFallback(
                    $log
                );
        }

        if ($exception) {
            report($exception);
        }
    }
}
