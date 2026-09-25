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

    public function __invoke(Request $request): RedirectResponse
    {
        $trackingId = trim((string) $request->query('OrderTrackingId', ''));
        $merchantReference = trim((string) $request->query('OrderMerchantReference', ''));

        abort_if(
            $trackingId === '' || $merchantReference === '',
            422,
            'Invalid Pesapal callback.'
        );

        $payment = Payment::query()
            ->where('reference', $merchantReference)
            ->firstOrFail();

        try {
            $payment = $this->paymentService->syncFromGateway($payment, $trackingId);
        } catch (Throwable $exception) {
            report($exception);
            $payment = $payment->fresh();
        }

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
