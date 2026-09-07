<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\PaymentService;
use Illuminate\Http\RedirectResponse;

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
}
