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
    ) {
    }

    public function fulfill(
        Payment $payment
    ): Payment {
        $payment = DB::transaction(
            function () use ($payment): Payment {
                $lockedPayment =
                    Payment::query()
                        ->with([
                            'attendee.event.paymentSetting',
                            'ticketOrder',
                        ])
                        ->lockForUpdate()
                        ->findOrFail(
                            $payment->getKey()
                        );

                if (
                    $lockedPayment->status
                    !== Payment::STATUS_COMPLETED
                ) {
                    throw new RuntimeException(
                        'Payment must be completed before fulfillment.'
                    );
                }

                if ($lockedPayment->fulfilled_at) {
                    return $lockedPayment;
                }

                return $lockedPayment;
            }
        );

        /*
         * Payment fulfillment is idempotent.
         *
         * A payment that already has fulfilled_at set must not:
         * - issue duplicate tickets
         * - regenerate badges
         * - resend My Tickets delivery
         * - recreate financial snapshots
         */
        if ($payment->fulfilled_at) {
            return $payment->fresh();
        }

        try {
            if ($payment->ticket_order_id) {
                $this->fulfillTicketPayment(
                    $payment
                );

                $this->markPaymentFulfilled(
                    $payment
                );

                /*
                 * Queue the buyer's secure My Tickets access only after:
                 *
                 * 1. payment is completed,
                 * 2. order is marked PAID,
                 * 3. financial snapshot is frozen,
                 * 4. tickets have been issued,
                 * 5. payment fulfillment has been finalized.
                 *
                 * Because fulfilled_at is checked at the beginning of this
                 * method, reconciliation/IPN retries will not enqueue a
                 * second access message.
                 */
                $this->queueTicketAccessLink(
                    $payment
                );

                return $payment->fresh([
                    'ticketOrder',
                ]);
            }

            if ($payment->attendee_id) {
                $this->fulfillAttendeePayment(
                    $payment
                );

                $this->markPaymentFulfilled(
                    $payment
                );

                return $payment->fresh([
                    'attendee',
                ]);
            }

            throw new RuntimeException(
                'Payment has no attendee or ticket order to fulfill.'
            );
        } catch (Throwable $exception) {
            report($exception);

            throw $exception;
        }
    }

    /**
     * Fulfill a ticket payment.
     *
     * If the reservation has already expired, inventory
     * must be checked again while the ticket-type rows
     * are locked.
     */
    private function fulfillTicketPayment(
        Payment $payment
    ): void {
        DB::transaction(
            function () use ($payment): void {
                $lockedOrder =
                    TicketOrder::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $payment->ticket_order_id
                        );

                if (
                    (int) $lockedOrder->event_id
                    !== (int) $payment->event_id
                ) {
                    throw new RuntimeException(
                        'Ticket order does not belong to the payment event.'
                    );
                }

                if ($lockedOrder->isCancelled()) {
                    throw new RuntimeException(
                        'Cancelled ticket order cannot be fulfilled.'
                    );
                }

                if (
                    $lockedOrder->status
                    === TicketOrder::STATUS_REFUNDED
                ) {
                    throw new RuntimeException(
                        'Refunded ticket order cannot be fulfilled.'
                    );
                }

                $lockedOrder->loadMissing([
                    'items',
                ]);

                /*
                 * A reservation stops protecting inventory when:
                 *
                 * - the order has STATUS_EXPIRED, or
                 * - expires_at has passed.
                 *
                 * In that case we must reacquire the inventory.
                 */
                $reservationExpired =
                    $lockedOrder->isExpired()
                    || (
                        $lockedOrder->expires_at !== null
                        && $lockedOrder->expires_at->isPast()
                    );

                if (
                    $reservationExpired
                    && ! $lockedOrder->isPaid()
                ) {
                    /*
                     * Lock every involved ticket type in a
                     * deterministic order.
                     *
                     * TicketOrderService uses the same rows for
                     * reservation creation, preventing another
                     * buyer from taking capacity between this
                     * check and issuance.
                     */
                    $ticketTypeIds =
                        $lockedOrder
                            ->items
                            ->pluck('ticket_type_id')
                            ->unique()
                            ->sort()
                            ->values();

                    $ticketTypes =
                        TicketType::query()
                            ->whereIn(
                                'id',
                                $ticketTypeIds
                            )
                            ->orderBy('id')
                            ->lockForUpdate()
                            ->get()
                            ->keyBy('id');

                    if (
                        $ticketTypes->count()
                        !== $ticketTypeIds->count()
                    ) {
                        throw new RuntimeException(
                            'One or more ticket types are missing.'
                        );
                    }

                    foreach (
                        $lockedOrder->items as $item
                    ) {
                        /** @var TicketType|null $ticketType */
                        $ticketType =
                            $ticketTypes->get(
                                $item->ticket_type_id
                            );

                        if (! $ticketType) {
                            throw new RuntimeException(
                                'Ticket type is missing.'
                            );
                        }

                        $quantity =
                            (int) $item->quantity;

                        if (
                            ! $this
                                ->ticketAvailabilityService
                                ->canReserve(
                                    $ticketType,
                                    $quantity
                                )
                        ) {
                            $available =
                                $this
                                    ->ticketAvailabilityService
                                    ->availableQuantity(
                                        $ticketType
                                    );

                            throw new RuntimeException(
                                "Ticket payment completed after the reservation expired, but {$ticketType->name} no longer has enough capacity. Available: "
                                . (
                                    $available === null
                                        ? 'unknown'
                                        : $available
                                )
                                . '. Payment requires manual review or refund.'
                            );
                        }
                    }
                }

                /*
                 * Inventory is now either:
                 *
                 * 1. still protected by the original reservation, or
                 * 2. successfully reacquired after expiry.
                 */
                if (! $lockedOrder->isPaid()) {
                    $lockedOrder->update([
                        'status' =>
                            TicketOrder::STATUS_PAID,

                        'paid_at' =>
                            $lockedOrder->paid_at
                            ?? $payment->paid_at
                            ?? now(),
                    ]);
                } elseif (
                    $lockedOrder->paid_at === null
                ) {
                    $lockedOrder->update([
                        'paid_at' =>
                            $payment->paid_at
                            ?? now(),
                    ]);
                }

                /*
                 * Freeze the financial snapshot exactly once.
                 *
                 * Historical financial values must never change
                 * when the event commission or gateway fee is
                 * changed later.
                 *
                 * This snapshot becomes the accounting source
                 * of truth for organizer and admin dashboards.
                 */
                if (
                    ! $lockedOrder->hasFinancialSnapshot()
                ) {
                    $lockedOrder->loadMissing([
                        'event.paymentSetting',
                    ]);

                    $financialSnapshot =
                        $this
                            ->financialCalculator
                            ->calculate(
                                $lockedOrder,
                                $lockedOrder
                                    ->event
                                    ?->paymentSetting
                            );

                    $lockedOrder->forceFill(
                        $financialSnapshot
                    )->save();
                }

                /*
                 * Keep issuance inside the same outer database
                 * transaction.
                 *
                 * TicketIssuanceService is also idempotent.
                 */
                $this->ticketIssuanceService
                    ->issueForOrder(
                        $lockedOrder->fresh()
                    );
            },
            attempts: 3
        );
    }

    /**
     * Queue the secure My Tickets link after successful
     * ticket payment fulfillment.
     *
     * The delivery service selects WhatsApp/email and
     * the SMS fallback, creates auditable logs, and
     * prevents duplicate automatic delivery.
     */
    private function queueTicketAccessLink(
        Payment $payment
    ): void {
        $order =
            TicketOrder::query()
                ->find(
                    $payment->ticket_order_id
                );

        if (
            ! $order
            || ! $order->isPaid()
        ) {
            return;
        }

        $this->ticketAccessDeliveryService
            ->queueAutomatic(
                $order
            );
    }

    private function fulfillAttendeePayment(
        Payment $payment
    ): void {
        $payment->loadMissing([
            'attendee.event.paymentSetting',
        ]);

        $attendee =
            $payment->attendee;

        if (! $attendee) {
            throw new RuntimeException(
                'Payment attendee is missing.'
            );
        }

        $attendee->loadMissing([
            'event.paymentSetting',
            'category',
            'badgeType',
        ]);

        $event =
            $attendee->event;

        if (! $event) {
            throw new RuntimeException(
                'Payment attendee event is missing.'
            );
        }

        $settings =
            $event->paymentSetting;

        if (
            $event->registration_auto_generate_badge
            && (
                ! $settings
                || $settings->payment_required_before_badge
            )
        ) {
            $this->badgeGenerationService
                ->generateForAttendee(
                    $attendee
                );

            $attendee->refresh();
        }

        $this->communicationService
            ->handleRegistration(
                $attendee
            );
    }

    private function markPaymentFulfilled(
        Payment $payment
    ): void {
        DB::transaction(
            function () use ($payment): void {
                $lockedPayment =
                    Payment::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $payment->getKey()
                        );

                if ($lockedPayment->fulfilled_at) {
                    return;
                }

                if (
                    $lockedPayment->status
                    !== Payment::STATUS_COMPLETED
                ) {
                    throw new RuntimeException(
                        'Payment must remain completed before fulfillment can be finalized.'
                    );
                }

                $lockedPayment->forceFill([
                    'fulfilled_at' => now(),
                ])->save();
            }
        );
    }
}
