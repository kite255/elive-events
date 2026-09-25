<?php

namespace App\Services\Tickets;

use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AdminTicketLookupService
{
    public function __construct(
        protected TicketUpgradeService $ticketUpgradeService
    ) {
    }

    public function search(User $user, ?string $term, int $limit = 25): array
    {
        $term = trim((string) $term);

        if ($term === '') {
            return [];
        }

        $search = '%' . $term . '%';

        return Ticket::query()
            ->with([
                'event:id,organization_id,name',
                'order:id,event_id,order_number,public_token,buyer_name,buyer_phone,buyer_email,total,currency,status,paid_at',
                'ticketType:id,event_id,name,price,currency',
            ])
            ->where(function (Builder $query) use ($search): void {
                $query
                    ->whereLike('ticket_number', $search)
                    ->orWhereHas('order', function (Builder $orderQuery) use ($search): void {
                        $orderQuery
                            ->whereLike('order_number', $search)
                            ->orWhereLike('buyer_name', $search)
                            ->orWhereLike('buyer_phone', $search)
                            ->orWhereLike('buyer_email', $search);
                    });
            })
            ->latest('id')
            ->limit($limit)
            ->get()
            ->filter(fn (Ticket $ticket): bool => $this->canViewTicket($user, $ticket))
            ->map(fn (Ticket $ticket): array => $this->mapTicket($ticket))
            ->values()
            ->all();
    }

    public function findAuthorizedTicket(User $user, int $ticketId): Ticket
    {
        $ticket = Ticket::query()
            ->with(['event', 'order', 'ticketType'])
            ->findOrFail($ticketId);

        abort_unless($this->canViewTicket($user, $ticket), 403);

        return $ticket;
    }

    private function canViewTicket(User $user, Ticket $ticket): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $event = $ticket->event;

        if (! $event) {
            return false;
        }

        if ($user->isTicketOrganizer()) {
            return $user
                ->assignedTicketingEvents()
                ->where('events.id', $event->id)
                ->exists();
        }

        return $user->canViewEventReports($event);
    }

    private function mapTicket(Ticket $ticket): array
    {
        $order = $ticket->order;
        $event = $ticket->event;
        $ticketType = $ticket->ticketType;

        $upgradeTargets = [];
        $canUpgrade = false;

        if (
            $order?->status === TicketOrder::STATUS_PAID
            && $ticket->status === Ticket::STATUS_ISSUED
            && $ticket->used_at === null
        ) {
            try {
                $upgradeTargets = $this->ticketUpgradeService
                    ->eligibleTargets($ticket)
                    ->map(fn ($target): array => [
                        'id' => $target->id,
                        'name' => $target->name,
                        'price' => (float) $target->price,
                        'balance' => round((float) $target->price - (float) $ticket->price, 2),
                        'currency' => $target->currency,
                    ])
                    ->values()
                    ->all();

                $canUpgrade = count($upgradeTargets) > 0;
            } catch (\RuntimeException) {
                $upgradeTargets = [];
            }
        }

        return [
            'ticket_id' => $ticket->id,
            'event_id' => $event?->id,
            'event_name' => $event?->name ?? '-',
            'order_id' => $order?->id,
            'order_number' => $order?->order_number ?? '-',
            'ticket_number' => $ticket->ticket_number,
            'ticket_type' => $ticketType?->name ?? '-',
            'ticket_price' => (float) ($ticket->price ?? 0),
            'buyer_name' => $order?->buyer_name ?? $ticket->holder_name ?? '-',
            'buyer_phone' => $order?->buyer_phone ?? $ticket->holder_phone,
            'buyer_email' => $order?->buyer_email ?? $ticket->holder_email,
            'amount' => (float) ($order?->total ?? $ticket->price ?? 0),
            'currency' => (string) ($order?->currency ?? $ticket->currency ?? 'TZS'),
            'order_status' => $order?->status ?? 'unknown',
            'ticket_status' => $ticket->status ?? 'unknown',
            'is_checked_in' => $ticket->used_at !== null || $ticket->isUsed(),
            'used_at' => $ticket->used_at,
            'paid_at' => $order?->paid_at,
            'can_upgrade' => $canUpgrade,
            'upgrade_targets' => $upgradeTargets,
            'order_url' => $order?->public_token
                ? route('public.ticket-orders.show', ['token' => $order->public_token])
                : null,
            'ticket_url' => $ticket->public_token
                ? route('public.tickets.show', ['token' => $ticket->public_token])
                : null,
        ];
    }
}
