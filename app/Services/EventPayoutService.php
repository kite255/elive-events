<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventPayoutRequest;
use App\Models\Payment;
use App\Models\TicketOrder;
use App\Models\User;
use App\Notifications\EventPayoutStatusNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EventPayoutService
{
    /**
     * Ticket-only balance. This is NOT a cash-on-hand or payment-provider settlement guarantee.
     * A later reconciliation component must confirm eligible funds before payout execution.
     */
    public function ticketBalance(int $eventId): array
    {
        $earned = (float) TicketOrder::query()
            ->where('event_id', $eventId)
            ->where('status', TicketOrder::STATUS_PAID)
            ->sum('organizer_net_amount');

        $paid = (float) EventPayoutRequest::query()
            ->where('event_id', $eventId)
            ->where('status', EventPayoutRequest::PAID)
            ->sum('amount');

        $reserved = (float) EventPayoutRequest::query()
            ->where('event_id', $eventId)
            ->whereIn('status', [EventPayoutRequest::REQUESTED, EventPayoutRequest::APPROVED])
            ->sum('amount');

        $cleared = (float) DB::table('event_finance_clearance_entries')
            ->where('event_id', $eventId)->where('entry_type', 'settlement')->sum('amount');
        $reversals = (float) DB::table('event_finance_clearance_entries')
            ->where('event_id', $eventId)->whereIn('entry_type', ['refund', 'chargeback'])->sum('amount');

        $exceptions = TicketOrder::query()->where('event_id', $eventId)
            ->whereIn('status', [TicketOrder::STATUS_REFUNDED, TicketOrder::STATUS_PARTIALLY_REFUNDED])->count();

        $paymentExceptions = Payment::query()->where('event_id', $eventId)
            ->whereNotNull('ticket_order_id')
            ->whereIn('status', [Payment::STATUS_REFUNDED, Payment::STATUS_PARTIALLY_REFUNDED])->count();
        $exceptions += $paymentExceptions;

        $available = max(0, min($earned, $cleared - $reversals) - $paid - $reserved);

        return [
            'ticket_net_earnings' => round($earned, 2),
            'verified_cleared_funds' => round($cleared, 2),
            'verified_reversals' => round($reversals, 2),
            'refund_exception_count' => $exceptions,
            'paid_out' => round($paid, 2),
            'reserved_requests' => round($reserved, 2),
            'requestable_ticket_balance' => round($available, 2),
        ];
    }

    public function request(
        Event $event,
        User $user,
        float $amount,
        string $destinationType,
        string $destinationDetails
    ): EventPayoutRequest {
        if (! $user->isSuperAdmin() && ! ($user->canManageOrganization((int) $event->organization_id) || $user->assignedTicketingEvents()->where('events.id', $event->getKey())->exists())) {
            throw ValidationException::withMessages(['event' => 'You cannot access this event.']);
        }

        if ($amount <= 0 || ! is_finite($amount) || round($amount, 2) !== $amount) {
            throw ValidationException::withMessages(['amount' => 'Enter a valid positive amount with at most two decimal places.']);
        }

        if (! in_array($destinationType, ['bank', 'mobile_money'], true) || trim($destinationDetails) === '') {
            throw ValidationException::withMessages(['destination' => 'A valid destination is required.']);
        }

        if (! config('finance.payout_requests_enabled', false)) {
            throw ValidationException::withMessages([
                'amount' => 'Payout requests are disabled pending independent settlement review.',
            ]);
        }

        return DB::transaction(function () use ($event, $user, $amount, $destinationType, $destinationDetails): EventPayoutRequest {
            Event::query()->whereKey($event->getKey())->lockForUpdate()->firstOrFail();
            $balance = $this->ticketBalance((int) $event->getKey());

            if ($balance['refund_exception_count'] > 0) {
                throw ValidationException::withMessages(['amount' => 'Refund exceptions must be reconciled before payouts.']);
            }

            if ($amount > $balance['requestable_ticket_balance']) {
                throw ValidationException::withMessages(['amount' => 'Amount exceeds reconciled available balance.']);
            }

            $request = EventPayoutRequest::query()->create([
                'event_id' => $event->getKey(),
                'requested_by' => $user->getKey(),
                'reference' => 'ELV-PO-'.strtoupper(Str::random(16)),
                'amount' => $amount,
                'currency' => 'TZS',
                'destination_type' => $destinationType,
                'destination_details' => trim($destinationDetails),
            ]);
            DB::table('event_payout_audits')->insert([
                'event_payout_request_id' => $request->getKey(),
                'actor_id' => $user->getKey(),
                'action' => 'requested',
                'from_status' => null,
                'to_status' => EventPayoutRequest::REQUESTED,
                'created_at' => now(),
            ]);

            DB::afterCommit(function () use ($request, $event, $user): void {
                try {
                    $user->notify(new EventPayoutStatusNotification(
                        $request->reference, $event->name, EventPayoutRequest::REQUESTED,
                        $request->currency.' '.number_format((float) $request->amount, 2)
                    ));
                } catch (\Throwable $exception) {
                    Log::warning('Payout request notification failed', [
                        'payout_request_id' => $request->getKey(),
                        'error' => $exception->getMessage(),
                    ]);
                }
            });

            return $request;
        });
    }
}
