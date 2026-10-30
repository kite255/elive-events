<?php

use App\Models\Payment;
use App\Services\Payments\PaymentService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(
        Inspiring::quote()
    );
})->purpose(
    'Display an inspiring quote'
);

Artisan::command(
    'payments:reconcile',
    function () {
        $paymentService =
            app(
                PaymentService::class
            );

        $processed = 0;
        $failed = 0;

        Payment::query()
            ->whereIn(
                'status',
                [
                    Payment::STATUS_PENDING,
                    Payment::STATUS_PROCESSING,
                ]
            )
            ->whereNotNull(
                'provider_tracking_id'
            )
            ->where(
                'provider_tracking_id',
                '<>',
                ''
            )
            ->chunkById(
                100,
                function ($payments) use (
                    $paymentService,
                    &$processed,
                    &$failed
                ): void {
                    foreach (
                        $payments as $payment
                    ) {
                        try {
                            $paymentService
                                ->syncFromGateway(
                                    $payment
                                );

                            $processed++;
                        } catch (
                            \Throwable $exception
                        ) {
                            $failed++;

                            report(
                                $exception
                            );
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
    }
)->purpose(
    'Synchronize pending and processing payments with their payment gateway'
);

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
|
| Payment reconciliation runs every minute so delayed or missed Pesapal
| callbacks/IPNs are recovered automatically.
|
| Ticket-order expiration also runs every minute so expired unpaid
| reservations release their inventory quickly.
|
*/

Schedule::command(
    'payments:reconcile'
)
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command(
    'tickets:expire-orders'
)
    ->everyMinute()
    ->withoutOverlapping();
/*
|--------------------------------------------------------------------------
| Backup Tasks
|--------------------------------------------------------------------------
|
| All backup schedules use the application timezone.
|
| 02:00 - Create database + storage backup
| 03:00 - Apply retention cleanup
| 04:00 - Check backup health
|
*/

Schedule::command(
    'backup:run'
)
    ->dailyAt('02:00')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping();

Schedule::command(
    'backup:clean'
)
    ->dailyAt('03:00')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping();

Schedule::command(
    'backup:monitor'
)
    ->dailyAt('04:00')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping();

Schedule::command('merchandise:expire-orders')->everyMinute()->withoutOverlapping();

// Daily ticket summaries: opt-in emails for authorized recipients.
Schedule::command('tickets:email-daily-summary')->dailyAt('00:10')->timezone('Africa/Dar_es_Salaam')->withoutOverlapping();
Schedule::command('tickets:email-organizer-summaries')->dailyAt('00:20')->timezone('Africa/Dar_es_Salaam')->withoutOverlapping();
