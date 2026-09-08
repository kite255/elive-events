<?php

namespace App\Jobs;

use App\Models\Payment;
use App\Services\Payments\PaymentFulfillmentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class FulfillCompletedPayment implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 120;

    public function __construct(
        public int $paymentId
    ) {
        $this->onQueue('payments');
    }

    public function handle(
        PaymentFulfillmentService $fulfillmentService
    ): void {
        $payment = Payment::query()
            ->find($this->paymentId);

        if (! $payment) {
            return;
        }

        if (! $payment->isCompleted()) {
            return;
        }

        if ($payment->isFulfilled()) {
            return;
        }

        $fulfillmentService->fulfill(
            $payment
        );
    }

    public function failed(
        Throwable $exception
    ): void {
        report($exception);
    }
}