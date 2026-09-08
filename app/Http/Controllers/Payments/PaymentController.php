<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\PaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Throwable;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService
    ) {
    }

    /**
     * Start or retry checkout for an existing eLive payment.
     */
    public function pay(
        Payment $payment
    ): RedirectResponse {
        /*
         * Completed payments must never be submitted
         * to the gateway again.
         */
        if ($payment->isCompleted()) {
            return redirect()
                ->route(
                    'payments.status',
                    $payment
                );
        }

        $checkout =
            $this->paymentService
                ->start(
                    $payment
                );

        $redirectUrl =
            data_get(
                $checkout,
                'redirect_url'
            );

        abort_if(
            blank($redirectUrl),
            502,
            'Payment gateway did not return a checkout URL.'
        );

        return redirect()
            ->away(
                $redirectUrl
            );
    }

    /**
     * Public attendee-facing payment status page.
     */
    public function status(
        Payment $payment
    ): View {
        $payment->loadMissing([
            'event',
            'attendee',
            'gateway',
        ]);

        /*
         * If the payment is still unresolved and we already
         * have a provider tracking ID, check Pesapal once more.
         */
        if (
            (
                $payment->isPending()
                || $payment->isProcessing()
            )
            && filled(
                $payment->provider_tracking_id
            )
        ) {
            try {
                $payment =
                    $this->paymentService
                        ->syncFromGateway(
                            $payment
                        );

                $payment->loadMissing([
                    'event',
                    'attendee',
                    'gateway',
                ]);
            } catch (Throwable $exception) {
                /*
                 * Do not make the public payment status page
                 * unavailable just because Pesapal cannot be
                 * reached temporarily.
                 */
                report($exception);

                $payment =
                    $payment->fresh([
                        'event',
                        'attendee',
                        'gateway',
                    ]);
            }
        }

        return view(
            'public.payments.status',
            [
                'payment' => $payment,
            ]
        );
    }
}
