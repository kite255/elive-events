<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway as PaymentGatewayContract;
use App\Models\Attendee;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\PaymentTransaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

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

        $event =
            $attendee->event;

        if (! $event) {
            throw new RuntimeException(
                'Attendee is not linked to an event.'
            );
        }

        $settings =
            $event->paymentSetting;

        if (
            ! $settings
            || ! $settings->payments_enabled
        ) {
            throw new RuntimeException(
                'Online payments are not enabled for this event.'
            );
        }

        $amount =
            (float) $settings->registration_fee;

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
                 * Reuse an existing unpaid registration payment instead
                 * of generating duplicates when the attendee retries.
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
                        $settings->currency
                        ?: 'TZS',

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

        $gatewayModel =
            $payment->gateway;

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

            DB::transaction(
                function () use (
                    $payment,
                    $response
                ): void {
                    $payment->update([
                        'status' =>
                            Payment::STATUS_PROCESSING,

                        'provider_reference' =>
                            data_get(
                                $response,
                                'merchant_reference'
                            ),

                        'provider_tracking_id' =>
                            data_get(
                                $response,
                                'order_tracking_id'
                            ),

                        'metadata' =>
                            array_merge(
                                $payment->metadata
                                    ?? [],
                                [
                                    'checkout_redirect_url' =>
                                        data_get(
                                            $response,
                                            'redirect_url'
                                        ),
                                ]
                            ),
                    ]);

                    $payment
                        ->transactions()
                        ->create([
                            'type' =>
                                PaymentTransaction::TYPE_ORDER_CREATED,

                            'provider_reference' =>
                                data_get(
                                    $response,
                                    'order_tracking_id'
                                ),

                            'response_payload' =>
                                $response,

                            'status' =>
                                'success',
                        ]);
                }
            );

            return $response;
        } catch (\Throwable $exception) {
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
     * Verify a payment directly with its gateway and synchronize eLive.
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

        if (blank($trackingId)) {
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

        $response =
            $gateway->getPaymentStatus(
                $trackingId
            );

        $gatewayStatus =
            strtoupper(
                (string) data_get(
                    $response,
                    'payment_status_description',
                    ''
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
                $attributes = [
                    'provider_tracking_id' =>
                        $trackingId,

                    'provider_reference' =>
                        data_get(
                            $response,
                            'confirmation_code'
                        )
                        ?: $payment
                            ->provider_reference,

                    'payment_method' =>
                        data_get(
                            $response,
                            'payment_method'
                        ),

                    'status' =>
                        $localStatus,

                    'metadata' =>
                        array_merge(
                            $payment->metadata
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
                                    data_get(
                                        $response,
                                        'payment_account'
                                    ),
                            ]
                        ),
                ];

                if (
                    $localStatus
                    === Payment::STATUS_COMPLETED
                ) {
                    $attributes['paid_at'] =
                        $payment->paid_at
                        ?? now();

                    $attributes['failed_at'] =
                        null;
                }

                if (
                    $localStatus
                    === Payment::STATUS_FAILED
                ) {
                    $attributes['failed_at'] =
                        $payment->failed_at
                        ?? now();
                }

                $payment->update(
                    $attributes
                );

                $payment
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
         * Attendee confirmation, badge release and communications will be
         * connected in the next payment-processing phase.
         */
        return $payment->fresh();
    }

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
