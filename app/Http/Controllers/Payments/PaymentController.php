<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\PaymentService;
use App\Services\Payments\TicketUpgradePaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Throwable;

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

        $checkout = $payment->ticket_upgrade_id
            ? $this->ticketUpgradePaymentService->start($payment)
            : $this->paymentService->start($payment);

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
                ]);
            } catch (Throwable $exception) {
                report($exception);

                $payment = $payment->fresh([
                    'event',
                    'attendee',
                    'gateway',
                    'ticketOrder',
                    'ticketUpgrade',
                ]);
            }
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
        $payment->loadMissing('ticketUpgrade');

        if ($payment->ticketUpgrade) {
            return redirect()->route(
                'tickets.upgrades.show',
                ['token' => $payment->ticketUpgrade->public_token]
            );
        }

        return redirect()->route('payments.status', $payment);
    }
}
