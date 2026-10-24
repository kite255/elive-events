<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventPayoutRequest;
use App\Models\TicketOrder;
use App\Models\User;
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

        return [
            'ticket_net_earnings' => round($earned, 2),
            'paid_out' => round($paid, 2),
            'reserved_requests' => round($reserved, 2),
            'requestable_ticket_balance' => round(max(0, $earned - $paid - $reserved), 2),
        ];
    }

    public function request(
        Event $event,
        User $user,
        float $amount,
        string $destinationType,
        string $destinationDetails
    ): EventPayoutRequest {
        if (! $user->isSuperAdmin() && ! $event->isAccessibleBy($user)) {
            throw ValidationException::withMessages(['event' => 'You cannot access this event.']);
        }

        if ($amount <= 0 || ! is_finite($amount) || round($amount, 2) !== $amount) {
            throw ValidationException::withMessages(['amount' => 'Enter a valid positive amount with at most two decimal places.']);
        }

        if (! in_array($destinationType, ['bank', 'mobile_money'], true) || trim($destinationDetails) === '') {
            throw ValidationException::withMessages(['destination' => 'A valid destination is required.']);
        }

        return DB::transaction(function () use ($event, $user, $amount, $destinationType, $destinationDetails) {
            // Lock the parent event to serialize concurrent requests for its balance.
            Event::query()->whereKey($event->getKey())->lockForUpdate()->firstOrFail();

            if ($amount > $this->ticketBalance((int) $event->getKey())['requestable_ticket_balance']) {
                throw ValidationException::withMessages(['amount' => 'Amount exceeds the remaining ticket balance.']);
            }

            return EventPayoutRequest::query()->create([
                'event_id' => $event->getKey(),
                'requested_by' => $user->getKey(),
                'reference' => 'ELV-PO-'.strtoupper(Str::random(16)),
                'amount' => $amount,
                'currency' => 'TZS',
                'destination_type' => $destinationType,
                'destination_details' => $destinationDetails,
            ]);
        });
    }
}
