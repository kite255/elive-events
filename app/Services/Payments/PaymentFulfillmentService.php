<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\TicketOrder;
use App\Models\TicketType;
use App\Services\AutomaticCommunicationService;
use App\Services\BadgeGenerationService;
use App\Services\Tickets\TicketAccessDeliveryService;
use App\Services\Tickets\TicketAvailabilityService;
use App\Services\Tickets\TicketIssuanceService;
use App\Services\Tickets\TicketUpgradeFulfillmentService;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class PaymentFulfillmentService
{
    public function __construct(
        protected BadgeGenerationService $badgeGenerationService,
        protected AutomaticCommunicationService $communicationService,
        protected TicketIssuanceService $ticketIssuanceService,
        protected TicketAvailabilityService $ticketAvailabilityService,
        protected TicketAccessDeliveryService $ticketAccessDeliveryService,
        protected OrderFinancialCalculator $financialCalculator,
        protected TicketUpgradeFulfillmentService $ticketUpgradeFulfillmentService,
    ) {
    }

    public function fulfill(Payment $payment): Payment
    {
        try {
            // Keep the payment claim and database-only fulfillment in the same
            // transaction. A second worker must wait for the committed marker.
            $relation = DB::transaction(function () use ($payment): ?string {
                $lockedPayment = Payment::query()->lockForUpdate()->findOrFail($payment->getKey());
                if ($lockedPayment->status !== Payment::STATUS_COMPLETED) {
                    throw new RuntimeException('Payment must be completed before fulfillment.');
                }
                if ($lockedPayment->fulfilled_at) {
                    return null;
                }

                if ($lockedPayment->merchandise_order_id) {
                    $this->fulfillMerchandisePayment($lockedPayment);
                    $relation = 'merchandiseOrder';
                } elseif ($lockedPayment->ticket_upgrade_id) {
                    \App\Models\TicketUpgrade::query()->lockForUpdate()->findOrFail($lockedPayment->ticket_upgrade_id);
                    $this->assertNoPreviouslyFulfilledPayment($lockedPayment, 'ticket_upgrade_id');
                    $this->ticketUpgradeFulfillmentService->fulfill($lockedPayment);
                    $relation = 'ticketUpgrade';
                } elseif ($lockedPayment->donation_id) {
                    $this->fulfillDonationPayment($lockedPayment);
                    $relation = 'donation';
                } elseif ($lockedPayment->ticket_order_id) {
                    $this->fulfillTicketPayment($lockedPayment);
                    $relation = 'ticketOrder';
                } elseif ($lockedPayment->attendee_id) {
                    // Registration includes file generation and messaging; retain
                    // its existing separate workflow until those effects are audited.
                    return 'attendee';
                } else {
                    throw new RuntimeException('Payment has no fulfillment target.');
                }

                $lockedPayment->forceFill(['fulfilled_at' => now()])->save();

                return $relation;
            });

            if ($relation === null) {
                return $payment->fresh();
            }

            $payment = $payment->fresh([$relation]);
            if ($relation === 'attendee') {
                $this->fulfillAttendeePayment($payment);
                $this->markPaymentFulfilled($payment);
                return $payment->fresh(['attendee']);
            }

            // Notification failures must not undo committed financial changes.
            try {
                if ($relation === 'merchandiseOrder') {
                    app(\App\Services\MerchandisePurchaseNotificationService::class)->queueForPayment($payment);
                } elseif ($relation === 'ticketUpgrade') {
                    $this->ticketAccessDeliveryService->queueUpgradeCompleted($payment->ticketUpgrade);
                } elseif ($relation === 'ticketOrder') {
                    $this->queueTicketAccessLink($payment);
                    app(\App\Services\Tickets\OrganizerTicketSaleNotificationService::class)
                        ->queueForPaidOrder((int) $payment->ticket_order_id);
                }
            } catch (Throwable $notificationException) {
                report($notificationException);
            }

            return $payment;
        } catch (Throwable $exception) {
            report($exception);
            throw $exception;
        }
    }

    // Caller holds the shared fulfillment target row lock. Do not lock
    // competing payment rows: another worker may already hold one.
    private function assertNoPreviouslyFulfilledPayment(Payment $payment, string $target): void
    {
        $previous = Payment::query()
            ->where($target, $payment->{$target})
            ->where('id', '!=', $payment->id)
            ->whereNotNull('fulfilled_at')
            ->exists();

        if ($previous) {
            throw new RuntimeException('Another payment already fulfilled this target. Payment requires manual reconciliation.');
        }
    }

    private function fulfillMerchandisePayment(Payment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $order = \App\Models\MerchandiseOrder::query()
                ->lockForUpdate()->findOrFail($payment->merchandise_order_id);

            if ((int) $order->event_id !== (int) $payment->event_id
                || bccomp((string) $order->total_tzs, (string) $payment->amount, 2) !== 0
                || strtoupper((string) $payment->currency) !== 'TZS') {
                throw new RuntimeException('Merchandise payment does not match the order.');
            }

            $this->assertNoPreviouslyFulfilledPayment($payment, 'merchandise_order_id');

            // A completed gateway payment is not proof that the reservation
            // still owns stock. Late payments must be reviewed or refunded.
            if ($order->status === 'paid') {
                return;
            }

            // A late payment must not silently consume stock reallocated
            // after the customer's reservation expired.
            if ($order->status !== 'pending_payment'
                || ! $order->expires_at || $order->expires_at->isPast()) {
                throw new RuntimeException('Merchandise reservation expired. Payment requires manual review or refund.');
            }

            $order->update(['status' => 'paid']);
        }, attempts: 3);
    }

    private function fulfillDonationPayment(Payment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $donation = \App\Models\Donation::query()
                ->lockForUpdate()
                ->findOrFail($payment->donation_id);

            if ((int) $donation->event_id !== (int) $payment->event_id) {
                throw new RuntimeException('Donation does not belong to the payment event.');
            }

            if ((string) $donation->currency !== (string) $payment->currency) {
                throw new RuntimeException('Donation payment currency does not match the donation currency.');
            }

            if (bccomp((string) $donation->amount, (string) $payment->amount, 2) !== 0) {
                throw new RuntimeException('Donation payment amount does not match the donation amount.');
            }

            $this->assertNoPreviouslyFulfilledPayment($payment, 'donation_id');

            if ($donation->status === \App\Models\Donation::STATUS_COMPLETED) {
                return;
            }

            $donation->forceFill([
                'status' => \App\Models\Donation::STATUS_COMPLETED,
                'completed_at' => $donation->completed_at ?? $payment->paid_at ?? now(),
            ])->save();
        }, attempts: 3);
    }

    private function fulfillTicketPayment(Payment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $lockedOrder = TicketOrder::query()
                ->lockForUpdate()
                ->findOrFail($payment->ticket_order_id);

            if ((int) $lockedOrder->event_id !== (int) $payment->event_id) {
                throw new RuntimeException('Ticket order does not belong to the payment event.');
            }

            if (bccomp((string) $lockedOrder->total, (string) $payment->amount, 2) !== 0
                || strtoupper((string) $lockedOrder->currency) !== strtoupper((string) $payment->currency)) {
                throw new RuntimeException('Ticket payment amount or currency does not match the order.');
            }

            $this->assertNoPreviouslyFulfilledPayment($payment, 'ticket_order_id');

            if ($lockedOrder->isCancelled()) {
                throw new RuntimeException('Cancelled ticket order cannot be fulfilled.');
            }

            if ($lockedOrder->status === TicketOrder::STATUS_REFUNDED) {
                throw new RuntimeException('Refunded ticket order cannot be fulfilled.');
            }

            $lockedOrder->loadMissing(['items']);

            $reservationExpired = $lockedOrder->isExpired()
                || ($lockedOrder->expires_at !== null && $lockedOrder->expires_at->isPast());

            if ($reservationExpired && ! $lockedOrder->isPaid()) {
                $ticketTypeIds = $lockedOrder->items
                    ->pluck('ticket_type_id')
                    ->unique()
                    ->sort()
                    ->values();

                $ticketTypes = TicketType::query()
                    ->whereIn('id', $ticketTypeIds)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');

                if ($ticketTypes->count() !== $ticketTypeIds->count()) {
                    throw new RuntimeException('One or more ticket types are missing.');
                }

                foreach ($lockedOrder->items as $item) {
                    /** @var TicketType|null $ticketType */
                    $ticketType = $ticketTypes->get($item->ticket_type_id);

                    if (! $ticketType) {
                        throw new RuntimeException('Ticket type is missing.');
                    }

                    $quantity = (int) $item->quantity;

                    if (! $this->ticketAvailabilityService->canReserve($ticketType, $quantity)) {
                        $available = $this->ticketAvailabilityService->availableQuantity($ticketType);

                        throw new RuntimeException(
                            "Ticket payment completed after the reservation expired, but {$ticketType->name} no longer has enough capacity. Available: "
                            . ($available === null ? 'unknown' : $available)
                            . '. Payment requires manual review or refund.'
                        );
                    }
                }
            }

            if (! $lockedOrder->isPaid()) {
                $lockedOrder->update([
                    'status' => TicketOrder::STATUS_PAID,
                    'paid_at' => $lockedOrder->paid_at ?? $payment->paid_at ?? now(),
                ]);
            } elseif ($lockedOrder->paid_at === null) {
                $lockedOrder->update([
                    'paid_at' => $payment->paid_at ?? now(),
                ]);
            }

            if (! $lockedOrder->hasFinancialSnapshot()) {
                $lockedOrder->loadMissing(['event.paymentSetting']);

                $financialSnapshot = $this->financialCalculator->calculate(
                    $lockedOrder,
                    $lockedOrder->event?->paymentSetting
                );

                $lockedOrder->forceFill($financialSnapshot)->save();
            }

            $this->ticketIssuanceService->issueForOrder($lockedOrder->fresh());
        }, attempts: 3);
    }

    private function queueTicketAccessLink(Payment $payment): void
    {
        $order = TicketOrder::query()->find($payment->ticket_order_id);

        if (! $order || ! $order->isPaid()) {
            return;
        }

        $this->ticketAccessDeliveryService->queueAutomatic($order);
    }

    private function fulfillAttendeePayment(Payment $payment): void
    {
        $payment->loadMissing(['attendee.event.paymentSetting']);

        $attendee = $payment->attendee;

        if (! $attendee) {
            throw new RuntimeException('Payment attendee is missing.');
        }

        $attendee->loadMissing(['event.paymentSetting', 'category', 'badgeType']);
        $event = $attendee->event;

        if (! $event) {
            throw new RuntimeException('Payment attendee event is missing.');
        }

        if ((int) $event->id !== (int) $payment->event_id) {
            throw new RuntimeException('Attendee does not belong to the payment event.');
        }

        if ((int) $event->organization_id !== (int) $payment->organization_id) {
            throw new RuntimeException('Attendee event does not belong to the payment organization.');
        }

        if (! $attendee->isApproved()) {
            throw new RuntimeException('Registration is no longer eligible for fulfillment. Payment requires manual reconciliation.');
        }

        $settings = $event->paymentSetting;

        if (
            $event->registration_auto_generate_badge
            && (! $settings || $settings->payment_required_before_badge)
        ) {
            $this->badgeGenerationService->generateForAttendee($attendee);
            $attendee->refresh();
        }

        $this->communicationService->handleRegistration($attendee);
    }

    private function markPaymentFulfilled(Payment $payment): void
    {
        DB::transaction(function () use ($payment): void {
            $lockedPayment = Payment::query()
                ->lockForUpdate()
                ->findOrFail($payment->getKey());

            if ($lockedPayment->fulfilled_at) {
                return;
            }

            if ($lockedPayment->status !== Payment::STATUS_COMPLETED) {
                throw new RuntimeException(
                    'Payment must remain completed before fulfillment can be finalized.'
                );
            }

            $lockedPayment->forceFill(['fulfilled_at' => now()])->save();
        });
    }
}
