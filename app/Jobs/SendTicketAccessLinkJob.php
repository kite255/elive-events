<?php

namespace App\Jobs;

use App\Models\TicketOrder;
use App\Services\PhoneNumberService;
use App\Services\SmsService;
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
        public int $ticketOrderId,
        public string $channel,
        public string $recipient
    ) {
        $this->onQueue(
            $channel === 'sms'
                ? 'communications-sms'
                : 'communications-email'
        );
    }

    public function handle(
        SmsService $smsService,
        PhoneNumberService $phoneNumberService
    ): void {
        $order = TicketOrder::query()
            ->with([
                'event.organization',
            ])
            ->find(
                $this->ticketOrderId
            );

        if (
            ! $order
            || ! $order->isPaid()
        ) {
            return;
        }

        /*
         * Defense in depth:
         *
         * Even though the controller only queues a job after a valid
         * match, re-check the recipient against the order at execution
         * time so a delayed job cannot be redirected to another contact.
         */
        if (
            ! $this->recipientStillBelongsToOrder(
                $order,
                $phoneNumberService
            )
        ) {
            return;
        }

        $url = route(
            'public.ticket-orders.show',
            [
                'token' =>
                    $order->public_token,
            ]
        );

        match ($this->channel) {
            'email' =>
                $this->sendEmail(
                    $order,
                    $url
                ),

            'sms' =>
                $this->sendSms(
                    $order,
                    $url,
                    $smsService
                ),

            default =>
                throw new RuntimeException(
                    'Unsupported ticket access delivery channel.'
                ),
        };
    }

    private function sendEmail(
        TicketOrder $order,
        string $url
    ): void {
        $eventName =
            $order->event?->name
            ?? 'eLive Events';

        $buyerName =
            trim(
                (string) $order->buyer_name
            );

        $body = implode(
            PHP_EOL,
            [
                filled($buyerName)
                    ? "Hello {$buyerName},"
                    : 'Hello,',
                '',
                "Your tickets for {$eventName} are ready.",
                '',
                'Open your secure My Tickets page using the link below:',
                $url,
                '',
                "Order: {$order->order_number}",
                '',
                'Keep this link private because it provides access to your tickets.',
                '',
                'eLive Events',
            ]
        );

        Mail::raw(
            $body,
            function (
                Message $message
            ) use (
                $eventName
            ): void {
                $message
                    ->to(
                        $this->recipient
                    )
                    ->subject(
                        "Your tickets - {$eventName}"
                    );
            }
        );
    }

    private function sendSms(
        TicketOrder $order,
        string $url,
        SmsService $smsService
    ): void {
        $eventName =
            $order->event?->name
            ?? 'your event';

        $message =
            "eLive Events: Your tickets for {$eventName} are ready. "
            . "Order: {$order->order_number}. "
            . "View My Tickets: {$url}";

        $smsService->send(
            $this->recipient,
            $message
        );
    }

    private function recipientStillBelongsToOrder(
        TicketOrder $order,
        PhoneNumberService $phoneNumberService
    ): bool {
        if ($this->channel === 'email') {
            if (blank($order->buyer_email)) {
                return false;
            }

            return hash_equals(
                mb_strtolower(
                    trim(
                        (string) $order->buyer_email
                    )
                ),
                mb_strtolower(
                    trim(
                        $this->recipient
                    )
                )
            );
        }

        if ($this->channel !== 'sms') {
            return false;
        }

        try {
            $savedPhone =
                $phoneNumberService
                    ->normalize(
                        $order->buyer_phone
                    );

            $jobPhone =
                $phoneNumberService
                    ->normalize(
                        $this->recipient
                    );
        } catch (InvalidArgumentException) {
            return false;
        }

        return filled($savedPhone)
            && filled($jobPhone)
            && hash_equals(
                $savedPhone,
                $jobPhone
            );
    }

    public function failed(
        Throwable $exception
    ): void {
        report(
            $exception
        );
    }
}
