<?php

namespace App\Http\Controllers;

use App\Jobs\SendTicketAccessLinkJob;
use App\Models\CommunicationLog;
use App\Models\TicketOrder;
use App\Services\PhoneNumberService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class PublicTicketRecoveryController extends Controller
{
    public const GENERIC_SUCCESS_MESSAGE =
        'If the information matches a paid ticket order, we have sent the ticket access link to the contact used during purchase.';

    public function __construct(
        protected PhoneNumberService $phoneNumberService
    ) {
    }

    public function show(): View
    {
        return view(
            'public.tickets.find'
        );
    }

    public function store(
        Request $request
    ): RedirectResponse {
        $validated = $request->validate([
            'order_number' => [
                'required',
                'string',
                'max:50',
            ],

            'contact' => [
                'required',
                'string',
                'max:255',
            ],
        ]);

        $orderNumber = strtoupper(
            trim(
                (string) $validated['order_number']
            )
        );

        $contact = trim(
            (string) $validated['contact']
        );

        /*
         * Only PAID ticket orders are eligible for recovery.
         *
         * Do not use firstOrFail() here because a missing order must
         * produce the same public response as every other non-match.
         */
        $order = TicketOrder::query()
            ->where(
                'order_number',
                $orderNumber
            )
            ->where(
                'status',
                TicketOrder::STATUS_PAID
            )
            ->first();

        if ($order) {
            $this->queueMatchingAccessLink(
                $order,
                $contact
            );
        }

        /*
         * Always return the same response.
         *
         * This prevents attackers from determining whether an order,
         * email address, or phone number exists in the system.
         */
        return redirect()
            ->route(
                'public.ticket-recovery.show'
            )
            ->with(
                'status',
                self::GENERIC_SUCCESS_MESSAGE
            );
    }

    private function queueMatchingAccessLink(
        TicketOrder $order,
        string $contact
    ): void {
        if (
            filter_var(
                $contact,
                FILTER_VALIDATE_EMAIL
            )
        ) {
            $submittedEmail = mb_strtolower(
                trim($contact)
            );

            $savedEmail = filled($order->buyer_email)
                ? mb_strtolower(
                    trim(
                        (string) $order->buyer_email
                    )
                )
                : null;

            if (
                $savedEmail !== null
                && hash_equals(
                    $savedEmail,
                    $submittedEmail
                )
            ) {
                $this->queueRecoveryLog(
                    $order,
                    CommunicationLog::CHANNEL_EMAIL,
                    $savedEmail
                );
            }

            return;
        }

        try {
            $submittedPhone =
                $this->phoneNumberService
                    ->normalize(
                        $contact
                    );

            $savedPhone =
                $this->phoneNumberService
                    ->normalize(
                        $order->buyer_phone
                    );
        } catch (InvalidArgumentException) {
            return;
        }

        if (
            filled($savedPhone)
            && filled($submittedPhone)
            && hash_equals(
                $savedPhone,
                $submittedPhone
            )
        ) {
            $this->queueRecoveryLog(
                $order,
                CommunicationLog::CHANNEL_SMS,
                $savedPhone
            );
        }
    }

    private function queueRecoveryLog(
        TicketOrder $order,
        string $channel,
        string $recipient
    ): void {
        $log = CommunicationLog::query()->create([
            'event_id' => $order->event_id,
            'ticket_order_id' => $order->id,
            'purpose' =>
                CommunicationLog::PURPOSE_TICKET_ACCESS_RECOVERY,
            'channel' => $channel,
            'recipient' => $recipient,
            'subject' => $channel
                === CommunicationLog::CHANNEL_EMAIL
                    ? 'Ticket access recovery'
                    : null,
            'message' =>
                "Secure ticket access recovery for order {$order->order_number}",
            'status' =>
                CommunicationLog::STATUS_QUEUED,
            'queued_at' => now(),
        ]);

        SendTicketAccessLinkJob::dispatch(
            $log->id
        )->onQueue(
            $channel === CommunicationLog::CHANNEL_SMS
                ? 'communications-sms'
                : 'communications-email'
        );
    }
}
