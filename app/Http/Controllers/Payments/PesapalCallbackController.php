<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class PesapalCallbackController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {
    }

    /**
     * Browser callback after the customer leaves Pesapal.
     *
     * Pesapal does not send the payment status in this callback, therefore
     * eLive verifies the transaction using GetTransactionStatus first.
     */
    public function __invoke(
        Request $request
    ): JsonResponse {
        $trackingId =
            (string) $request->query(
                'OrderTrackingId',
                ''
            );

        $merchantReference =
            (string) $request->query(
                'OrderMerchantReference',
                ''
            );

        abort_if(
            blank($trackingId)
            || blank($merchantReference),
            422,
            'Invalid Pesapal callback.'
        );

        $payment =
            Payment::query()
                ->where(
                    'reference',
                    $merchantReference
                )
                ->firstOrFail();

        try {
            $payment =
                $this->paymentService
                    ->syncFromGateway(
                        $payment,
                        $trackingId
                    );

            /*
             * Temporary development response.
             * In the next step this will redirect to a branded
             * success / pending / failed payment page.
             */
            return response()->json([
                'payment_reference' =>
                    $payment->reference,

                'status' =>
                    $payment->status,

                'amount' =>
                    $payment->amount,

                'currency' =>
                    $payment->currency,

                'provider_tracking_id' =>
                    $payment
                        ->provider_tracking_id,
            ]);
        } catch (Throwable $exception) {
            report($exception);

            return response()->json(
                [
                    'message' =>
                        'Payment verification could not be completed.',

                    'payment_reference' =>
                        $payment->reference,
                ],
                502
            );
        }
    }
}
