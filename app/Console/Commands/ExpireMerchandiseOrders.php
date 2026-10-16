<?php

namespace App\Console\Commands;

use App\Models\MerchandiseOrder;
use App\Models\MerchandiseProduct;
use App\Models\Payment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireMerchandiseOrders extends Command
{
    protected $signature = 'merchandise:expire-orders';
    protected $description = 'Expire unpaid merchandise reservations and return unlinked stock';

    public function handle(): int
    {
        $count = 0;
        MerchandiseOrder::query()->where('status', 'pending_payment')
            ->whereNotNull('expires_at')->where('expires_at', '<=', now())
            ->orderBy('id')->pluck('id')->each(function ($id) use (&$count) {
                DB::transaction(function () use ($id, &$count) {
                    $order = MerchandiseOrder::query()->lockForUpdate()->find($id);
                    if (! $order || $order->status !== 'pending_payment' || ! $order->expires_at || $order->expires_at->isFuture()) {
                        return;
                    }
                    // Do not release inventory while a provider-confirmed payment
                    // is waiting for asynchronous fulfillment.
                    $completedPayment = Payment::query()
                        ->where('merchandise_order_id', $order->id)
                        ->where('status', Payment::STATUS_COMPLETED)
                        ->exists();

                    if ($completedPayment) {
                        return;
                    }

                    if ($order->merchandise_variant_id === null) {
                        $product = MerchandiseProduct::query()->lockForUpdate()->find($order->merchandise_product_id);
                        if ($product) {
                            $product->increment('stock_quantity', $order->quantity);
                        }
                    }
                    // Linked variant availability is calculated from active order statuses.
                    // Expiring the order releases its commitment without altering physical stock.
                    $order->update(['status' => 'expired']);
                    $count++;
                });
            });
        $this->info("Expired {$count} merchandise reservations.");
        return self::SUCCESS;
    }
}
