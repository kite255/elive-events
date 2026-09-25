<?php

namespace App\Services\Tickets;

use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketType;
use App\Models\TicketUpgrade;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class TicketUpgradeService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {
    }

    public function eligibleTargets(Ticket $ticket)
    {
        $ticket->loadMissing(['order', 'ticketType']);

        $this->assertTicketEligible($ticket);

        return TicketType::query()
            ->where('event_id', $ticket->event_id)
            ->where('is_active', true)
            ->where('id', '!=', $ticket->ticket_type_id)
            ->where('price', '>', (float) $ticket->price)
            ->where('currency', strtoupper((string) $ticket->currency))
            ->orderBy('price')
            ->get()
            ->filter(fn (TicketType $type): bool => ! $type->isSoldOut())
            ->values();
    }

    public function create(
        Ticket $ticket,
        TicketType $target,
        ?User $initiator = null
    ): TicketUpgrade {
        return DB::transaction(function () use ($ticket, $target, $initiator): TicketUpgrade {
            $lockedTicket = Ticket::query()
                ->with(['order', 'ticketType'])
                ->lockForUpdate()
                ->findOrFail($ticket->getKey());

            $this->assertTicketEligible($lockedTicket);

            $lockedTarget = TicketType::query()
                ->lockForUpdate()
                ->findOrFail($target->getKey());

            $this->assertTargetEligible($lockedTicket, $lockedTarget);

            $existing = TicketUpgrade::query()
                ->where('ticket_id', $lockedTicket->getKey())
                ->whereIn('status', TicketUpgrade::UNRESOLVED_STATUSES)
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($existing) {
                if ((int) $existing->to_ticket_type_id !== (int) $lockedTarget->getKey()) {
                    throw new RuntimeException('This ticket already has a pending upgrade.');
                }

                return $existing;
            }

            $originalPrice = round((float) $lockedTicket->price, 2);
            $targetPrice = round((float) $lockedTarget->price, 2);
            $amount = round($targetPrice - $originalPrice, 2);

            if ($amount <= 0) {
                throw new RuntimeException('The target ticket type must cost more than the current ticket.');
            }

            $upgrade = TicketUpgrade::query()->create([
                'event_id' => $lockedTicket->event_id,
                'ticket_order_id' => $lockedTicket->ticket_order_id,
                'ticket_id' => $lockedTicket->getKey(),
                'from_ticket_type_id' => $lockedTicket->ticket_type_id,
                'to_ticket_type_id' => $lockedTarget->getKey(),
                'reference' => $this->generateReference($lockedTicket),
                'original_price' => $originalPrice,
                'target_price' => $targetPrice,
                'upgrade_amount' => $amount,
                'currency' => strtoupper((string) $lockedTicket->currency),
                'status' => TicketUpgrade::STATUS_PENDING,
                'initiated_by' => $initiator?->getKey(),
                'initiated_at' => now(),
                'expires_at' => now()->addDay(),
            ]);

            $this->auditLogService->record(
                'ticket.upgrade.created',
                $upgrade,
                $initiator,
                [
                    'ticket_number' => $lockedTicket->ticket_number,
                    'from_ticket_type_id' => $upgrade->from_ticket_type_id,
                    'to_ticket_type_id' => $upgrade->to_ticket_type_id,
                    'amount' => (float) $upgrade->upgrade_amount,
                    'currency' => $upgrade->currency,
                ]
            );

            return $upgrade;
        });
    }

    public function publicUrl(TicketUpgrade $upgrade): string
    {
        return route('tickets.upgrades.show', ['token' => $upgrade->public_token]);
    }

    private function assertTicketEligible(Ticket $ticket): void
    {
        if (! $ticket->order) {
            throw new RuntimeException('This ticket is not linked to an order.');
        }

        if (! $ticket->order->isPaid()) {
            throw new RuntimeException('Only tickets from paid orders can be upgraded.');
        }

        if ($ticket->status !== Ticket::STATUS_ISSUED || $ticket->used_at !== null) {
            throw new RuntimeException('Only issued, unused tickets can be upgraded.');
        }

        if ($ticket->isCancelled() || $ticket->isRefunded()) {
            throw new RuntimeException('Cancelled or refunded tickets cannot be upgraded.');
        }
    }

    private function assertTargetEligible(Ticket $ticket, TicketType $target): void
    {
        if ((int) $target->event_id !== (int) $ticket->event_id) {
            throw new RuntimeException('The target ticket type must belong to the same event.');
        }

        if (! $target->is_active) {
            throw new RuntimeException('The target ticket type is not active.');
        }

        if (strtoupper((string) $target->currency) !== strtoupper((string) $ticket->currency)) {
            throw new RuntimeException('The target ticket type uses a different currency.');
        }

        if ((float) $target->price <= (float) $ticket->price) {
            throw new RuntimeException('The target ticket type must cost more than the current ticket.');
        }

        if ($target->isSoldOut()) {
            throw new RuntimeException('The target ticket type is sold out.');
        }
    }

    private function generateReference(Ticket $ticket): string
    {
        $eventCode = strtoupper((string) ($ticket->event?->event_code ?? 'EVT'));
        $eventCode = preg_replace('/[^A-Z0-9]/', '', $eventCode) ?: 'EVT';

        do {
            $reference = 'ELV-UPG-' . substr($eventCode, 0, 8) . '-' . Str::upper(Str::random(8));
        } while (TicketUpgrade::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
