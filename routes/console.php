<?php

use App\Models\Payment;
use App\Services\Payments\PaymentService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('payments:reconcile', function () {
    $paymentService = app(PaymentService::class);

    $processed = 0;
    $failed = 0;

    Payment::query()
        ->whereIn('status', [
            Payment::STATUS_PENDING,
            Payment::STATUS_PROCESSING,
        ])
        ->whereNotNull('provider_tracking_id')
        ->where('provider_tracking_id', '<>', '')
        ->chunkById(
            100,
            function ($payments) use (
                $paymentService,
                &$processed,
                &$failed
            ): void {
                foreach ($payments as $payment) {
                    try {
                        $paymentService->syncFromGateway(
                            $payment
                        );

                        $processed++;
                    } catch (\Throwable $exception) {
                        $failed++;

                        report($exception);
                    }
                }
            }
        );

    $this->info(
        sprintf(
            'Payment reconciliation completed. Processed: %d, Failed: %d.',
            $processed,
            $failed
        )
    );

    return 0;
})->purpose(
    'Synchronize pending and processing payments with their payment gateway'
);

Schedule::command('payments:reconcile')
    ->everyFiveMinutes()
    ->withoutOverlapping();