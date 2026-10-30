<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\PaymentService;
use App\Services\Payments\TicketUpgradePaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Throwable;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentService $paymentService,
        protected TicketUpgradePaymentService $ticketUpgradePaymentService
    ) {
    }

    public function pay(Payment $payment): RedirectResponse
    {
        if ($payment->isCompleted()) {
            return $this->redirectAfterPayment($payment);
        }

        try {
            $checkout = $payment->ticket_upgrade_id
                ? $this->ticketUpgradePaymentService->start($payment)
                : $this->paymentService->start($payment);
        } catch (Throwable $exception) {
            // PaymentService records the failed gateway attempt. Do not expose
            // provider responses to the buyer or change the payment status.
            Log::warning('Payment checkout initialization failed', [
                'payment_id' => $payment->getKey(),
                'exception_class' => get_class($exception),
            ]);

            return redirect()->route('payments.status', $payment)
                ->with('error', 'Checkout is temporarily unavailable. Your payment has not been confirmed. Please try again later or contact support.');
        }

        $redirectUrl = data_get($checkout, 'redirect_url');

        abort_if(
            blank($redirectUrl),
            502,
            'Payment gateway did not return a checkout URL.'
        );

        return redirect()->away($redirectUrl);
    }

    public function status(Payment $payment): View|RedirectResponse
    {
        $payment->loadMissing([
            'event',
            'attendee',
            'gateway',
            'ticketOrder',
            'ticketUpgrade',
            'merchandiseOrder',
        ]);

        if (
            ($payment->isPending() || $payment->isProcessing())
            && filled($payment->provider_tracking_id)
        ) {
            try {
                $payment = $this->paymentService->syncFromGateway($payment);
                $payment->loadMissing([
                    'event',
                    'attendee',
                    'gateway',
                    'ticketOrder',
                    'ticketUpgrade',
                    'merchandiseOrder',
                ]);
            } catch (Throwable $exception) {
                report($exception);

                $payment = $payment->fresh([
                    'event',
                    'attendee',
                    'gateway',
                    'ticketOrder',
                    'ticketUpgrade',
                    'merchandiseOrder',
                ]);
            }
        }

        if ($payment->merchandiseOrder) {
            return redirect()->route('public.merchandise.orders.show', $payment->merchandiseOrder->public_token);
        }

        if ($payment->ticketUpgrade) {
            return redirect()->route(
                'tickets.upgrades.show',
                ['token' => $payment->ticketUpgrade->public_token]
            );
        }

        return view('public.payments.status', ['payment' => $payment]);
    }

    private function redirectAfterPayment(Payment $payment): RedirectResponse
    {
        $payment->loadMissing(['ticketUpgrade', 'merchandiseOrder']);

        if ($payment->ticketUpgrade) {
            return redirect()->route(
                'tickets.upgrades.show',
                ['token' => $payment->ticketUpgrade->public_token]
            );
        }

        return redirect()->route('payments.status', $payment);
    }
}
