<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway as PaymentGatewayContract;
use App\Models\Attendee;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class PaymentService
{
    public function __construct(
        protected PaymentReferenceService $referenceService,
        protected PesapalService $pesapalService
    ) {
    }

    /**
     * Create one pending registration payment for an attendee.
     */
    public function createForAttendee(
        Attendee $attendee
    ): Payment {
        $attendee->loadMissing([
            'event.paymentSetting',
            'event.organization',
        ]);

        $event = $attendee->event;

        if (! $event) {
            throw new RuntimeException(
                'Attendee is not linked to an event.'
            );
        }

        $settings = $event->paymentSetting;

        if (
            ! $settings
            || ! $settings->payments_enabled
        ) {
            throw new RuntimeException(
                'Online payments are not enabled for this event.'
            );
        }

        $amount = (float) $settings->registration_fee;

        if ($amount <= 0) {
            throw new RuntimeException(
                'The event registration fee must be greater than zero.'
            );
        }

        $gateway =
            $this->defaultGatewayForOrganization(
                (int) $event->organization_id
            );

        return DB::transaction(
            function () use (
                $attendee,
                $event,
                $settings,
                $gateway,
                $amount
            ): Payment {
                /*
                 * Reuse an existing unpaid registration payment
                 * instead of generating duplicates when the
                 * attendee retries.
                 */
                $existing =
                    Payment::query()
                        ->where(
                            'attendee_id',
                            $attendee->getKey()
                        )
                        ->where(
                            'event_id',
                            $event->getKey()
                        )
                        ->whereIn(
                            'status',
                            [
                                Payment::STATUS_PENDING,
                                Payment::STATUS_PROCESSING,
                            ]
                        )
                        ->latest('id')
                        ->first();

                if ($existing) {
                    return $existing;
                }

                return Payment::create([
                    'organization_id' =>
                        $event->organization_id,

                    'event_id' =>
                        $event->getKey(),

                    'attendee_id' =>
                        $attendee->getKey(),

                    'payment_gateway_id' =>
                        $gateway->getKey(),

                    'reference' =>
                        $this->referenceService
                            ->generate($event),

                    'amount' =>
                        $amount,

                    'currency' =>
                        strtoupper(
                            (string) (
                                $settings->currency
                                ?: 'TZS'
                            )
                        ),

                    'status' =>
                        Payment::STATUS_PENDING,

                    'description' =>
                        'Registration payment for '
                        . $event->name,

                    'initiated_at' =>
                        now(),
                ]);
            }
        );
    }

    /**
     * Submit the local payment to its configured gateway.
     */
    public function start(
        Payment $payment
    ): array {
        if ($payment->isCompleted()) {
            throw new RuntimeException(
                'This payment is already completed.'
            );
        }

        $payment->loadMissing(
            'gateway'
        );

        $gatewayModel = $payment->gateway;

        if (! $gatewayModel) {
            throw new RuntimeException(
                'No payment gateway is linked to this payment.'
            );
        }

        if (! $gatewayModel->is_enabled) {
            throw new RuntimeException(
                'The selected payment gateway is disabled.'
            );
        }

        $gateway =
            $this->gatewayFor(
                $gatewayModel
            );

        try {
            $response =
                $gateway->createPayment(
                    $payment
                );

            /*
             * Never accept an order response whose merchant
             * reference differs from our local payment.
             */
            $returnedReference =
                trim(
                    (string) data_get(
                        $response,
                        'merchant_reference',
                        ''
                    )
                );

            if (
                $returnedReference === ''
                || ! hash_equals(
                    $payment->reference,
                    $returnedReference
                )
            ) {
                throw new RuntimeException(
                    'Payment gateway returned an unexpected merchant reference.'
                );
            }

            $trackingId =
                trim(
                    (string) data_get(
                        $response,
                        'order_tracking_id',
                        ''
                    )
                );

            if ($trackingId === '') {
                throw new RuntimeException(
                    'Payment gateway did not return an order tracking ID.'
                );
            }

            $redirectUrl =
                trim(
                    (string) data_get(
                        $response,
                        'redirect_url',
                        ''
                    )
                );

            if ($redirectUrl === '') {
                throw new RuntimeException(
                    'Payment gateway did not return a checkout URL.'
                );
            }

            DB::transaction(
                function () use (
                    $payment,
                    $response,
                    $returnedReference,
                    $trackingId,
                    $redirectUrl
                ): void {
                    $lockedPayment =
                        Payment::query()
                            ->lockForUpdate()
                            ->findOrFail(
                                $payment->getKey()
                            );

                    $lockedPayment->update([
                        'status' =>
                            Payment::STATUS_PROCESSING,

                        'provider_reference' =>
                            $returnedReference,

                        'provider_tracking_id' =>
                            $trackingId,

                        'metadata' =>
                            array_merge(
                                $lockedPayment->metadata
                                    ?? [],
                                [
                                    'checkout_redirect_url' =>
                                        $redirectUrl,
                                ]
                            ),
                    ]);

                    $lockedPayment
                        ->transactions()
                        ->create([
                            'type' =>
                                PaymentTransaction::TYPE_ORDER_CREATED,

                            'provider_reference' =>
                                $trackingId,

                            'response_payload' =>
                                $response,

                            'status' =>
                                'success',
                        ]);
                }
            );

            return $response;
        } catch (Throwable $exception) {
            $payment
                ->transactions()
                ->create([
                    'type' =>
                        PaymentTransaction::TYPE_ORDER_CREATED,

                    'status' =>
                        'failed',

                    'error_message' =>
                        $exception->getMessage(),
                ]);

            report($exception);

            throw $exception;
        }
    }

    /**
     * Verify a payment directly with its gateway
     * and synchronize eLive.
     */
    public function syncFromGateway(
        Payment $payment,
        ?string $providerTrackingId = null
    ): Payment {
        $payment->loadMissing(
            'gateway'
        );

        $trackingId =
            $providerTrackingId
            ?: $payment->provider_tracking_id;

        $trackingId =
            trim(
                (string) $trackingId
            );

        if ($trackingId === '') {
            throw new RuntimeException(
                'Provider tracking ID is missing.'
            );
        }

        $gatewayModel =
            $payment->gateway;

        if (! $gatewayModel) {
            throw new RuntimeException(
                'Payment gateway is missing.'
            );
        }

        $gateway =
            $this->gatewayFor(
                $gatewayModel
            );

        /*
         * Always ask the payment gateway for the authoritative
         * transaction status.
         */
        $response =
            $gateway->getPaymentStatus(
                $trackingId
            );

        /*
         * Verify the response belongs to this payment before
         * accepting any status change.
         */
        $this->verifyGatewayResponse(
            $payment,
            $trackingId,
            $response
        );

        $gatewayStatus =
            strtoupper(
                trim(
                    (string) data_get(
                        $response,
                        'payment_status_description',
                        ''
                    )
                )
            );

        $localStatus =
            match ($gatewayStatus) {
                'COMPLETED' =>
                    Payment::STATUS_COMPLETED,

                'FAILED',
                'INVALID' =>
                    Payment::STATUS_FAILED,

                'REVERSED' =>
                    Payment::STATUS_REFUNDED,

                default =>
                    Payment::STATUS_PROCESSING,
            };

        DB::transaction(
            function () use (
                $payment,
                $trackingId,
                $response,
                $gatewayStatus,
                $localStatus
            ): void {
                /*
                 * Callback and IPN can arrive at nearly the
                 * same time. Lock the payment while updating it.
                 */
                $lockedPayment =
                    Payment::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $payment->getKey()
                        );

                /*
                 * Do not allow a previously completed payment
                 * to be downgraded by a later stale PENDING
                 * response.
                 */
                if (
                    $lockedPayment->status
                    === Payment::STATUS_COMPLETED
                    && $localStatus
                    !== Payment::STATUS_COMPLETED
                ) {
                    $lockedPayment
                        ->transactions()
                        ->create([
                            'type' =>
                                PaymentTransaction::TYPE_PAYMENT_STATUS,

                            'provider_reference' =>
                                $trackingId,

                            'response_payload' =>
                                $response,

                            'status' =>
                                Payment::STATUS_COMPLETED,
                        ]);

                    return;
                }

                $confirmationCode =
                    trim(
                        (string) data_get(
                            $response,
                            'confirmation_code',
                            ''
                        )
                    );

                $paymentMethod =
                    trim(
                        (string) data_get(
                            $response,
                            'payment_method',
                            ''
                        )
                    );

                $paymentAccount =
                    trim(
                        (string) data_get(
                            $response,
                            'payment_account',
                            ''
                        )
                    );

                $attributes = [
                    'provider_tracking_id' =>
                        $trackingId,

                    /*
                     * Keep provider_reference stable as the
                     * merchant reference. Store Pesapal's
                     * confirmation code in metadata.
                     */
                    'provider_reference' =>
                        $lockedPayment
                            ->provider_reference,

                    'payment_method' =>
                        $paymentMethod,

                    'status' =>
                        $localStatus,

                    'metadata' =>
                        array_merge(
                            $lockedPayment->metadata
                                ?? [],
                            [
                                'pesapal_status' =>
                                    $gatewayStatus,

                                'pesapal_status_code' =>
                                    data_get(
                                        $response,
                                        'status_code'
                                    ),

                                'pesapal_payment_account' =>
                                    $paymentAccount,

                                'pesapal_confirmation_code' =>
                                    $confirmationCode,
                            ]
                        ),
                ];

                if (
                    $localStatus
                    === Payment::STATUS_COMPLETED
                ) {
                    $attributes['paid_at'] =
                        $lockedPayment->paid_at
                        ?? now();

                    $attributes['failed_at'] =
                        null;

                    $attributes['cancelled_at'] =
                        null;
                }

                if (
                    $localStatus
                    === Payment::STATUS_FAILED
                ) {
                    $attributes['failed_at'] =
                        $lockedPayment->failed_at
                        ?? now();
                }

                $lockedPayment->update(
                    $attributes
                );

                $lockedPayment
                    ->transactions()
                    ->create([
                        'type' =>
                            PaymentTransaction::TYPE_PAYMENT_STATUS,

                        'provider_reference' =>
                            $trackingId,

                        'response_payload' =>
                            $response,

                        'status' =>
                            $localStatus,
                    ]);
            }
        );

        /*
         * Attendee confirmation, badge release and
         * communications will be connected after payment
         * fulfillment is implemented.
         */
        return $payment->fresh();
    }

    /**
     * Verify that a payment-gateway response belongs to
     * the exact local eLive payment being synchronized.
     */
    private function verifyGatewayResponse(
        Payment $payment,
        string $trackingId,
        array $response
    ): void {
        /*
         * 1. Verify merchant reference.
         */
        $merchantReference =
            trim(
                (string) data_get(
                    $response,
                    'merchant_reference',
                    ''
                )
            );

        if (
            $merchantReference === ''
            || ! hash_equals(
                $payment->reference,
                $merchantReference
            )
        ) {
            throw new RuntimeException(
                'Payment gateway merchant reference mismatch.'
            );
        }

        /*
         * 2. Verify order tracking ID.
         */
        $responseTrackingId =
            trim(
                (string) data_get(
                    $response,
                    'order_tracking_id',
                    ''
                )
            );

        if (
            $responseTrackingId === ''
            || ! hash_equals(
                $trackingId,
                $responseTrackingId
            )
        ) {
            throw new RuntimeException(
                'Payment gateway tracking ID mismatch.'
            );
        }

        /*
         * If this payment already has a provider tracking ID,
         * the incoming tracking ID must also match it.
         */
        if (
            filled(
                $payment->provider_tracking_id
            )
            && ! hash_equals(
                (string) $payment->provider_tracking_id,
                $trackingId
            )
        ) {
            throw new RuntimeException(
                'Payment provider tracking ID does not match the local payment.'
            );
        }

        /*
         * 3. Verify currency.
         */
        $gatewayCurrency =
            strtoupper(
                trim(
                    (string) data_get(
                        $response,
                        'currency',
                        ''
                    )
                )
            );

        $localCurrency =
            strtoupper(
                trim(
                    (string) $payment->currency
                )
            );

        if (
            $gatewayCurrency === ''
            || $gatewayCurrency
                !== $localCurrency
        ) {
            throw new RuntimeException(
                'Payment gateway currency mismatch.'
            );
        }

        /*
         * 4. Verify amount using BCMath.
         *
         * Never trust a COMPLETED status where the gateway
         * amount differs from the registration amount.
         */
        $gatewayAmount =
            data_get(
                $response,
                'amount'
            );

        if (
            $gatewayAmount === null
            || $gatewayAmount === ''
        ) {
            throw new RuntimeException(
                'Payment gateway amount is missing.'
            );
        }

        $normalizedGatewayAmount =
            number_format(
                (float) $gatewayAmount,
                2,
                '.',
                ''
            );

        $normalizedLocalAmount =
            number_format(
                (float) $payment->amount,
                2,
                '.',
                ''
            );

        if (
            bccomp(
                $normalizedGatewayAmount,
                $normalizedLocalAmount,
                2
            ) !== 0
        ) {
            throw new RuntimeException(
                'Payment gateway amount mismatch.'
            );
        }
    }

    /**
     * Resolve the gateway service implementation.
     */
    public function gatewayFor(
        PaymentGateway $gateway
    ): PaymentGatewayContract {
        return match (
            strtolower(
                $gateway->code
            )
        ) {
            'pesapal' =>
                $this->pesapalService,

            default =>
                throw new RuntimeException(
                    'Unsupported payment gateway: '
                    . $gateway->code
                ),
        };
    }

    /**
     * Get the enabled default gateway for an organization.
     *
     * Organization-specific gateway takes priority over
     * the platform-wide default gateway.
     */
    protected function defaultGatewayForOrganization(
        int $organizationId
    ): PaymentGateway {
        $gateway =
            PaymentGateway::query()
                ->where(
                    'is_enabled',
                    true
                )
                ->where(
                    'is_default',
                    true
                )
                ->where(
                    function (
                        $query
                    ) use (
                        $organizationId
                    ): void {
                        $query
                            ->where(
                                'organization_id',
                                $organizationId
                            )
                            ->orWhereNull(
                                'organization_id'
                            );
                    }
                )
                ->orderByRaw(
                    'CASE WHEN organization_id = ? THEN 0 ELSE 1 END',
                    [$organizationId]
                )
                ->first();

        if (! $gateway) {
            throw new RuntimeException(
                'No enabled default payment gateway is configured.'
            );
        }

        return $gateway;
    }
}