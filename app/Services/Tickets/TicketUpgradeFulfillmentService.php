<?php

namespace App\Services\Tickets;

use App\Models\Payment;
use App\Models\Ticket;
use App\Models\TicketType;
use App\Models\TicketUpgrade;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TicketUpgradeFulfillmentService
{
    public function fulfill(Payment $payment): TicketUpgrade
    {
        if ($payment->status !== Payment::STATUS_COMPLETED) {
            throw new RuntimeException('Ticket upgrade payment must be completed before fulfillment.');
        }

        if (! $payment->ticket_upgrade_id) {
            throw new RuntimeException('Payment is not linked to a ticket upgrade.');
        }

        return DB::transaction(function () use ($payment): TicketUpgrade {
            $lockedPayment = Payment::query()
                ->lockForUpdate()
                ->findOrFail($payment->getKey());

            if ($lockedPayment->status !== Payment::STATUS_COMPLETED) {
                throw new RuntimeException('Ticket upgrade payment must remain completed before fulfillment.');
            }

            $upgrade = TicketUpgrade::query()
                ->with(['event.paymentSetting'])
                ->lockForUpdate()
                ->findOrFail($lockedPayment->ticket_upgrade_id);

            if ($upgrade->isCompleted()) {
                return $upgrade;
            }

            $this->assertPaymentMatchesUpgrade($lockedPayment, $upgrade);

            $ticket = Ticket::query()
                ->lockForUpdate()
                ->findOrFail($upgrade->ticket_id);

            $target = TicketType::query()
                ->lockForUpdate()
                ->findOrFail($upgrade->to_ticket_type_id);

            if ((int) $ticket->event_id !== (int) $upgrade->event_id) {
                throw new RuntimeException('Ticket upgrade event no longer matches the ticket.');
            }

            if ((int) $ticket->ticket_type_id !== (int) $upgrade->from_ticket_type_id) {
                throw new RuntimeException('Ticket type changed before the upgrade could be fulfilled.');
            }

            if ($ticket->status !== Ticket::STATUS_ISSUED || $ticket->used_at !== null) {
                throw new RuntimeException('This ticket has already been checked in or is no longer eligible for upgrade.');
            }

            if ((int) $target->event_id !== (int) $upgrade->event_id || ! $target->is_active) {
                throw new RuntimeException('Target ticket type is no longer available for this event.');
            }

            $sold = Ticket::query()
                ->where('ticket_type_id', $target->getKey())
                ->whereNotIn('status', [Ticket::STATUS_CANCELLED, Ticket::STATUS_REFUNDED])
                ->count();

            if ($target->capacity !== null && $sold >= (int) $target->capacity) {
                throw new RuntimeException(
                    'Upgrade payment completed, but the target ticket type is now sold out. Payment requires manual review or refund.'
                );
            }

            $ticket->forceFill([
                'ticket_type_id' => $target->getKey(),
                'price' => $upgrade->target_price,
            ])->save();

            $snapshot = $this->financialSnapshot($upgrade);

            $upgrade->forceFill(array_merge($snapshot, [
                'status' => TicketUpgrade::STATUS_COMPLETED,
                'completed_at' => now(),
            ]))->save();

            return $upgrade->fresh(['ticket', 'fromTicketType', 'toTicketType', 'order']);
        }, attempts: 3);
    }

    private function assertPaymentMatchesUpgrade(Payment $payment, TicketUpgrade $upgrade): void
    {
        if ((int) $payment->event_id !== (int) $upgrade->event_id) {
            throw new RuntimeException('Upgrade payment event mismatch.');
        }

        if (strtoupper((string) $payment->currency) !== strtoupper((string) $upgrade->currency)) {
            throw new RuntimeException('Upgrade payment currency mismatch.');
        }

        if (bccomp(
            number_format((float) $payment->amount, 2, '.', ''),
            number_format((float) $upgrade->upgrade_amount, 2, '.', ''),
            2
        ) !== 0) {
            throw new RuntimeException('Upgrade payment amount mismatch.');
        }
    }

    private function financialSnapshot(TicketUpgrade $upgrade): array
    {
        $settings = $upgrade->event?->paymentSetting;
        $gross = round((float) $upgrade->upgrade_amount, 2);
        $platformRate = round((float) ($settings?->platform_commission_rate ?? 0), 2);
        $gatewayRate = round((float) ($settings?->gateway_fee_rate ?? 0), 2);
        $gatewayBearer = (string) ($settings?->gateway_fee_bearer ?? 'organizer');
        $platformAmount = round($gross * ($platformRate / 100), 2);
        $gatewayAmount = round($gross * ($gatewayRate / 100), 2);
        $gatewayOrganizerDeduction = $gatewayBearer === 'organizer' ? $gatewayAmount : 0.0;
        $charges = round($platformAmount + $gatewayOrganizerDeduction, 2);

        return [
            'gross_amount' => $gross,
            'platform_commission_rate' => $platformRate,
            'platform_commission_amount' => $platformAmount,
            'gateway_fee_rate' => $gatewayRate,
            'gateway_fee_amount' => $gatewayAmount,
            'total_charges' => $charges,
            'organizer_net_amount' => round(max(0, $gross - $charges), 2),
            'financial_snapshot_at' => now(),
        ];
    }
}
