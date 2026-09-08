<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Services\AutomaticCommunicationService;
use App\Services\BadgeGenerationService;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class PaymentFulfillmentService
{
    public function __construct(
        protected BadgeGenerationService $badgeGenerationService,
        protected AutomaticCommunicationService $communicationService,
    ) {
    }

    /**
     * Fulfill a successfully completed registration payment.
     *
     * This method is idempotent:
     * - duplicate IPN/callback calls are safe
     * - already fulfilled payments are skipped
     * - communication duplicate protection is handled by
     *   AutomaticCommunicationService
     */
    public function fulfill(
        Payment $payment
    ): Payment {
        /*
         * First lock the payment and verify that fulfillment
         * has not already completed.
         */
        $payment = DB::transaction(
            function () use ($payment): Payment {
                $lockedPayment =
                    Payment::query()
                        ->with([
                            'attendee.event.paymentSetting',
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

                /*
                 * Already fulfilled:
                 * duplicate callback/IPN becomes a no-op.
                 */
                if ($lockedPayment->fulfilled_at) {
                    return $lockedPayment;
                }

                return $lockedPayment;
            }
        );

        /*
         * If another request fulfilled it while we were
         * waiting for the lock, stop here.
         */
        if ($payment->fulfilled_at) {
            return $payment->fresh();
        }

        $attendee = $payment->attendee;

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

        $event = $attendee->event;

        if (! $event) {
            throw new RuntimeException(
                'Attendee event is missing.'
            );
        }

        $settings =
            $event->paymentSetting;

        try {
            /*
             * Badge generation
             *
             * BadgeGenerationService already calls
             * AutomaticCommunicationService::handleBadgeReady()
             * after successful badge generation.
             */
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

                /*
                 * Reload attendee so badge_path and other
                 * badge state reflect the generated badge.
                 */
                $attendee->refresh();
            }

            /*
             * Registration communication
             *
             * This is still needed because:
             * - SMS may be sent here
             * - email may have been skipped previously
             * - WhatsApp may now be eligible
             *
             * AutomaticCommunicationService performs its own
             * duplicate protection, so email/WhatsApp already
             * triggered by handleBadgeReady() will not be sent
             * twice.
             */
            $this->communicationService
                ->handleRegistration(
                    $attendee
                );

            /*
             * Only mark fulfillment completed after all
             * fulfillment steps finish successfully.
             */
            DB::transaction(
                function () use ($payment): void {
                    $lockedPayment =
                        Payment::query()
                            ->lockForUpdate()
                            ->findOrFail(
                                $payment->getKey()
                            );

                    /*
                     * Another process may already have completed
                     * fulfillment while this process was running.
                     */
                    if ($lockedPayment->fulfilled_at) {
                        return;
                    }

                    $lockedPayment->forceFill([
                        'fulfilled_at' =>
                            now(),
                    ])->save();
                }
            );

            return $payment->fresh();
        } catch (Throwable $exception) {
            /*
             * fulfilled_at remains null.
             *
             * This allows a queue retry or later IPN/callback
             * to attempt fulfillment again.
             */
            report($exception);

            throw $exception;
        }
    }
}