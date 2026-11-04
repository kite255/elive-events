<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\PaymentService;
use App\Services\Payments\TicketUpgradePaymentService;
use Illuminate\Http\Response;
use Illuminate\Http\Request;
use App\Services\Payments\SelcomBrandedCheckoutService;
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

    public function pay(Payment $payment, SelcomBrandedCheckoutService $branded): Response|RedirectResponse
    {
        if ($payment->isCompleted()) {
            return $this->redirectAfterPayment($payment);
        }

        $payment->loadMissing('gateway');
        if ($payment->gateway?->code === 'selcom') {
            $details = $payment->isProcessing()
                ? $branded->qrDetails($payment)
                : ['payment_token' => '', 'qr_image' => null];

            return response()->view('public.payments.checkout', [
                'payment' => $payment,
                'qrDetails' => $details,
            ])->header('Cache-Control', 'private, no-store')
                ->header('Referrer-Policy', 'no-referrer');
        }

        // Preserve the legacy redirect flow for non-Selcom gateways.
        try {
            $checkout = $payment->ticket_upgrade_id
                ? $this->ticketUpgradePaymentService->start($payment)
                : $this->paymentService->start($payment);
        } catch (Throwable $exception) {
            Log::warning('Payment checkout initialization failed', [
                'payment_id' => $payment->getKey(),
                'exception_class' => get_class($exception),
            ]);

            return redirect()->route('payments.status', $payment)
                ->with('error', 'Checkout is temporarily unavailable. Your payment has not been confirmed. Please try again later or contact support.');
        }

        $redirectUrl = data_get($checkout, 'redirect_url');
        abort_if(blank($redirectUrl), 502, 'Payment gateway did not return a checkout URL.');

        return redirect()->away($redirectUrl);
    }

    public function submit(Payment $payment, Request $request, SelcomBrandedCheckoutService $branded): RedirectResponse
    {
        $payment->loadMissing('gateway');
        abort_unless($payment->gateway?->code === 'selcom', 404);

        $validated = $request->validate([
            'method' => ['required', 'in:mobile,qr'],
            'phone' => ['required_if:method,mobile', 'nullable', 'string', 'max:24', 'regex:/^(?:\\+?255|0)?[67]\\d{8}$/'],
        ]);

        // A Selcom order that is already processing may have a phone request
        // or QR payment in flight. Never send another request against it.
        if (! $payment->isPending()
            || filled($payment->provider_tracking_id)
            || filled(data_get($payment->metadata, 'checkout_attempted_at'))) {
            return redirect()->route('payments.pay', $payment)
                ->with('error', 'This order already has a checkout attempt. Check payment status before paying again.');
        }

        $checkoutStage = 'order_initialization';

        try {
            $payment = $branded->initialize($payment);

            if ($validated['method'] === 'mobile') {
                $checkoutStage = 'wallet_authorization';
                $branded->requestWallet($payment, (string) $validated['phone']);
            } else {
                // Only retain method choice when there has not already
                // been a wallet authorization request for this order.
                if (! filled(data_get($payment->metadata, 'wallet_attempted_at'))) {
                    $payment->update(['metadata' => array_merge($payment->metadata ?? [], [
                        'checkout_method' => 'qr',
                    ])]);
                }
            }

            return redirect()->route('payments.pay', $payment);
        } catch (Throwable $exception) {
            // Log only stable internal state, never the phone number, provider
            // payload, payment token, headers or exception message.
            $latest = $payment->fresh();
            Log::warning('Selcom branded checkout request failed', [
                'payment_id' => $payment->getKey(),
                'stage' => $checkoutStage,
                'payment_status' => $latest?->status,
                'provider_order_recorded' => filled($latest?->provider_tracking_id),
                'wallet_attempted' => filled(data_get($latest?->metadata, 'wallet_attempted_at')),
                'exception_class' => get_class($exception),
            ]);

            // No automatic retry after a provider request may have been sent.
            return redirect()->route('payments.pay', $payment)
                ->with('error', 'Payment initiation could not be confirmed. Do not submit another payment request; check the status or contact support.');
        }
    }

    public function status(Payment $payment): Response|RedirectResponse
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

        // The latest failed order-initialization attempt is not a payment failure.
        // Never show this state for a payment already tracked by a provider.
        $checkoutUnavailable = $payment->isPending()
            && blank($payment->provider_tracking_id)
            && $payment->transactions()
                ->where('type', 'order_created')
                ->latest('id')
                ->value('status') === 'failed';

        return response()->view('public.payments.status', [
            'payment' => $payment,
            'checkoutUnavailable' => $checkoutUnavailable,
        ])
            ->header('Cache-Control', 'private, no-store')
            ->header('Referrer-Policy', 'no-referrer');
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
