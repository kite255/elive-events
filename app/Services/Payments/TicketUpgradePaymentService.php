<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\TicketUpgrade;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TicketUpgradePaymentService extends PaymentService
{
    public function createForTicketUpgrade(TicketUpgrade $upgrade): Payment
    {
        $upgrade->loadMissing(['event.organization', 'order']);

        if (! $upgrade->event || ! $upgrade->order) {
            throw new RuntimeException('Ticket upgrade is missing its event or order.');
        }

        if ($upgrade->isCompleted()) {
            throw new RuntimeException('This ticket upgrade is already completed.');
        }

        if ($upgrade->isExpired()) {
            throw new RuntimeException('This ticket upgrade has expired.');
        }

        if (! $upgrade->isUnresolved()) {
            throw new RuntimeException('This ticket upgrade cannot be paid in its current state.');
        }

        $amount = round((float) $upgrade->upgrade_amount, 2);

        if ($amount <= 0) {
            throw new RuntimeException('Ticket upgrade amount must be greater than zero.');
        }

        $gateway = $this->defaultGatewayForOrganization(
            (int) $upgrade->event->organization_id
        );

        return DB::transaction(function () use ($upgrade, $gateway, $amount): Payment {
            $lockedUpgrade = TicketUpgrade::query()
                ->lockForUpdate()
                ->findOrFail($upgrade->getKey());

            if ($lockedUpgrade->isCompleted()) {
                throw new RuntimeException('This ticket upgrade is already completed.');
            }

            if ($lockedUpgrade->isExpired()) {
                if ($lockedUpgrade->status !== TicketUpgrade::STATUS_EXPIRED) {
                    $lockedUpgrade->update(['status' => TicketUpgrade::STATUS_EXPIRED]);
                }

                throw new RuntimeException('This ticket upgrade has expired.');
            }

            $existing = Payment::query()
                ->where('ticket_upgrade_id', $lockedUpgrade->getKey())
                ->whereIn('status', [Payment::STATUS_PENDING, Payment::STATUS_PROCESSING])
                ->latest('id')
                ->first();

            if ($existing) {
                return $existing;
            }

            return Payment::query()->create([
                'organization_id' => $lockedUpgrade->event->organization_id,
                'event_id' => $lockedUpgrade->event_id,
                'attendee_id' => null,
                'ticket_order_id' => null,
                'ticket_upgrade_id' => $lockedUpgrade->getKey(),
                'payment_gateway_id' => $gateway->getKey(),
                'reference' => $this->referenceService->generate($lockedUpgrade->event),
                'amount' => $amount,
                'currency' => strtoupper((string) $lockedUpgrade->currency),
                'status' => Payment::STATUS_PENDING,
                'description' => 'Ticket upgrade for ' . $lockedUpgrade->event->name
                    . ' - ' . $lockedUpgrade->reference,
                'initiated_at' => now(),
                'metadata' => [
                    'payment_purpose' => 'ticket_upgrade',
                    'ticket_upgrade_reference' => $lockedUpgrade->reference,
                    'ticket_order_number' => $lockedUpgrade->order->order_number,
                ],
            ]);
        });
    }

    public function start(Payment $payment): array
    {
        if (! $payment->ticket_upgrade_id) {
            return parent::start($payment);
        }

        $payment->loadMissing('ticketUpgrade');
        $upgrade = $payment->ticketUpgrade;

        if (! $upgrade) {
            throw new RuntimeException('Ticket upgrade linked to this payment is missing.');
        }

        if ($upgrade->isCompleted()) {
            throw new RuntimeException('This ticket upgrade is already completed.');
        }

        if ($upgrade->isExpired()) {
            throw new RuntimeException('This ticket upgrade has expired.');
        }

        $response = parent::start($payment);

        TicketUpgrade::query()
            ->whereKey($upgrade->getKey())
            ->where('status', TicketUpgrade::STATUS_PENDING)
            ->update(['status' => TicketUpgrade::STATUS_PROCESSING]);

        return $response;
    }
}
