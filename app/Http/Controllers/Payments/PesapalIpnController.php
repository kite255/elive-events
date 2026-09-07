<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class PesapalIpnController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {
    }

    /**
     * Pesapal server-to-server Instant Payment Notification endpoint.
     *
     * IPN does not contain the final payment status. eLive must independently
     * call GetTransactionStatus before updating the payment.
     */
    public function __invoke(
        Request $request
    ): JsonResponse {
        $trackingId =
            (string) (
                $request->input(
                    'OrderTrackingId'
                )
                ?: $request->query(
                    'OrderTrackingId',
                    ''
                )
            );

        $merchantReference =
            (string) (
                $request->input(
                    'OrderMerchantReference'
                )
                ?: $request->query(
                    'OrderMerchantReference',
                    ''
                )
            );

        $notificationType =
            (string) (
                $request->input(
                    'OrderNotificationType'
                )
                ?: $request->query(
                    'OrderNotificationType',
                    'IPNCHANGE'
                )
            );

        if (
            blank($trackingId)
            || blank($merchantReference)
        ) {
            return response()->json(
                [
                    'orderNotificationType' =>
                        $notificationType,

                    'orderTrackingId' =>
                        $trackingId,

                    'orderMerchantReference' =>
                        $merchantReference,

                    'status' =>
                        500,
                ],
                422
            );
        }

        $payment =
            Payment::query()
                ->where(
                    'reference',
                    $merchantReference
                )
                ->first();

        if (! $payment) {
            return response()->json([
                'orderNotificationType' =>
                    $notificationType,

                'orderTrackingId' =>
                    $trackingId,

                'orderMerchantReference' =>
                    $merchantReference,

                'status' =>
                    500,
            ]);
        }

        try {
            $this->paymentService
                ->syncFromGateway(
                    $payment,
                    $trackingId
                );

            return response()->json([
                'orderNotificationType' =>
                    $notificationType,

                'orderTrackingId' =>
                    $trackingId,

                'orderMerchantReference' =>
                    $merchantReference,

                'status' =>
                    200,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json([
                'orderNotificationType' =>
                    $notificationType,

                'orderTrackingId' =>
                    $trackingId,

                'orderMerchantReference' =>
                    $merchantReference,

                'status' =>
                    500,
            ]);
        }
    }
}
