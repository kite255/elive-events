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

// Send the previous completed calendar day's ticket summary at 00:10 EAT.
// Delivery is opt-in via DAILY_TICKET_SUMMARY_EMAIL_ENABLED and explicit recipients.
Schedule::command('tickets:email-daily-summary')
    ->dailyAt('00:10')
    ->timezone('Africa/Dar_es_Salaam')
    ->withoutOverlapping();

// Send each eligible organizer only their own event's previous-day report.
Schedule::command('tickets:email-organizer-summaries')
    ->dailyAt('00:20')
    ->timezone('Africa/Dar_es_Salaam')
    ->withoutOverlapping();


Artisan::command('certificates:recover-batches {event : Event ID} {--apply : Reconcile stale records after reviewing the queue}', function () {
    $eventId = filter_var($this->argument('event'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (! $eventId || ! \App\Models\Event::query()->whereKey($eventId)->exists()) {
        $this->error('A valid event ID is required.');

        return 1;
    }

    $apply = (bool) $this->option('apply');
    $result = app(\App\Services\EventCertificateBatchRecovery::class)->inspect($eventId, $apply);
    $this->info($apply ? 'Recovery changes applied. No jobs were dispatched.' : 'Read-only inspection. No records changed.');
    $this->table(['Metric', 'Count'], collect($result)->map(fn ($count, $key) => [$key, $count])->values()->all());
    if (! $apply) {
        $this->warn('Inspect worker and queue health before applying recovery.');
    }

    return 0;
})->purpose('Inspect or reconcile stale certificate batch recipients for one event');


Artisan::command('certificates:recover-deliveries {event : Event ID} {--apply : Flag stale queued deliveries as uncertain}', function () {
    $eventId = filter_var($this->argument('event'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (! $eventId || ! \App\Models\Event::query()->whereKey($eventId)->exists()) {
        $this->error('A valid event ID is required.');

        return 1;
    }

    $apply = (bool) $this->option('apply');
    $result = app(\App\Services\EventCertificateDeliveryRecovery::class)->inspect($eventId, $apply);
    $this->info($apply ? 'Recovery applied. No messages or jobs dispatched.' : 'Read-only inspection. No records changed.');
    $this->table(['Metric', 'Count'], collect($result)->map(fn ($count, $key) => [$key, $count])->values()->all());
    $this->warn('Investigate stale processing records and provider status before considering any resend.');

    return 0;
})->purpose('Inspect interrupted certificate delivery records for one event');


Artisan::command('certificates:prune-download-tokens {--apply : Delete expired short download tokens}', function () {
    $cutoff = now();
    $query = \Illuminate\Support\Facades\DB::table('event_certificate_download_tokens')->where('expires_at', '<=', $cutoff);
    if (! $this->option('apply')) {
        $this->info('Read-only inspection. Expired tokens: '.$query->count());

        return 0;
    }

    $deleted = 0;
    foreach ((clone $query)->select('id')->lazyById(500) as $token) {
        $deleted += (clone $query)->where('id', $token->id)->delete();
    }
    $this->info('Expired short download tokens deleted: '.$deleted.'. Certificates and PDFs unchanged.');

    return 0;
})->purpose('Inspect or prune expired certificate short download tokens');

Schedule::command('certificates:prune-download-tokens --apply')
    ->dailyAt('04:30')
    ->timezone(config('app.timezone'))
    ->withoutOverlapping();
