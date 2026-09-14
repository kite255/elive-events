<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Models\TicketOrder;
use Illuminate\Console\Command;

class ExpireTicketOrders extends Command
{
    protected $signature = 'tickets:expire-orders';

    protected $description =
        'Expire unpaid ticket orders whose reservation time has elapsed.';

    public function handle(): int
    {
        $expiredCount =
            TicketOrder::query()
                ->whereIn(
                    'status',
                    [
                        TicketOrder::STATUS_PENDING,
                        TicketOrder::STATUS_PROCESSING,
                    ]
                )
                ->whereNotNull(
                    'expires_at'
                )
                ->where(
                    'expires_at',
                    '<=',
                    now()
                )

                /*
                 * Critical payment race protection:
                 *
                 * Do not expire an order if Pesapal has
                 * already been confirmed as COMPLETED.
                 *
                 * The fulfillment worker may still be waiting
                 * to mark the order PAID and issue tickets.
                 */
                ->whereDoesntHave(
                    'payments',
                    function ($query): void {
                        $query->where(
                            'status',
                            Payment::STATUS_COMPLETED
                        );
                    }
                )

                ->update([
                    'status' =>
                        TicketOrder::STATUS_EXPIRED,
                ]);

        $this->info(
            "{$expiredCount} ticket order(s) expired."
        );

        return self::SUCCESS;
    }
}