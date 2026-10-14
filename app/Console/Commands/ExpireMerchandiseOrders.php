<?php

namespace App\Console\Commands;

use App\Models\MerchandiseOrder;
use App\Models\MerchandiseProduct;
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
                    if ($order->merchandise_variant_id !== null) {
                        // Do not release shared inventory until shared reservation accounting is implemented.
                        return;
                    }
                    $product = MerchandiseProduct::query()->lockForUpdate()->find($order->merchandise_product_id);
                    if ($product) {
                        $product->increment('stock_quantity', $order->quantity);
                    }
                    $order->update(['status' => 'expired']);
                    $count++;
                });
            });
        $this->info("Expired {$count} merchandise reservations.");
        return self::SUCCESS;
    }
}
