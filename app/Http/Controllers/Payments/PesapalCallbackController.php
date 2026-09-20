<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\PaymentService;
use Illuminate\Http\RedirectResponse;
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
     * The callback itself is not proof of payment.
     * eLive verifies the transaction directly with Pesapal
     * before redirecting the attendee to the public payment
     * status page.
     */
    public function __invoke(
        Request $request
    ): RedirectResponse {
        $trackingId =
            trim(
                (string) $request->query(
                    'OrderTrackingId',
                    ''
                )
            );

        $merchantReference =
            trim(
                (string) $request->query(
                    'OrderMerchantReference',
                    ''
                )
            );

        abort_if(
            $trackingId === ''
            || $merchantReference === '',
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
        } catch (Throwable $exception) {
            /*
             * Keep the attendee experience available even if
             * Pesapal verification temporarily fails.
             *
             * The payment status page will display the most
             * recent locally stored payment state.
             */
            report($exception);

            $payment =
                $payment->fresh();
        }

        return redirect()
            ->route(
                'payments.status',
                $payment
            );
    }
}
