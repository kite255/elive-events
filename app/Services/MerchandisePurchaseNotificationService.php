<?php

namespace App\Services;

use App\Jobs\SendAutomaticCommunicationJob;
use App\Models\CommunicationLog;
use App\Models\MerchandiseOrder;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class MerchandisePurchaseNotificationService
{
    public function queueForPayment(Payment $payment): void
    {
        if (! $payment->merchandise_order_id || $payment->status !== Payment::STATUS_COMPLETED || ! $payment->fulfilled_at) {
            return;
        }

        $order = MerchandiseOrder::with(['event', 'product'])->find($payment->merchandise_order_id);
        if (! $order || $order->status !== 'paid') {
            return;
        }

        $productName = $order->product?->name ?? 'Merchandise';
        $reference = $order->order_number;
        $total = 'TZS '.number_format((float) $order->total_tzs, 0);
        $name = trim((string) $order->buyer_name);
        $details = "{$productName}; Quantity: {$order->quantity}";
        if ($order->color) {
            $details .= "; Colour: {$order->color}";
        }
        if ($order->size) {
            $details .= "; Size: {$order->size}";
        }
        $message = "Hello {$name},\n\nYour eLive Events merchandise payment has been confirmed.\nOrder: {$reference}\nItem: {$details}\nPaid: {$total}\nCollection: At the event venue.\n\nPlease present your order reference when collecting your item. Thank you!";

        $channels = [];
        if (filled($order->buyer_phone)) {
            $channels[CommunicationLog::CHANNEL_SMS] = trim($order->buyer_phone);
        }
        if (filter_var($order->buyer_email, FILTER_VALIDATE_EMAIL)) {
            $channels[CommunicationLog::CHANNEL_EMAIL] = strtolower(trim($order->buyer_email));
        }

        foreach ($channels as $channel => $recipient) {
            $log = DB::transaction(function () use ($order, $channel, $recipient, $message): ?CommunicationLog {
                $existing = CommunicationLog::where('merchandise_order_id', $order->id)
                    ->where('purpose', 'merchandise_purchase_confirmation')
                    ->where('channel', $channel)
                    ->first();
                if ($existing) {
                    return null;
                }

                return CommunicationLog::create([
                    'event_id' => $order->event_id,
                    'merchandise_order_id' => $order->id,
                    'purpose' => 'merchandise_purchase_confirmation',
                    'channel' => $channel,
                    'recipient' => $recipient,
                    'subject' => $channel === CommunicationLog::CHANNEL_EMAIL ? 'Merchandise Payment Confirmed - '.$order->order_number : null,
                    'message' => $message,
                    'status' => CommunicationLog::STATUS_QUEUED,
                    'queued_at' => now(),
                ]);
            });

            if ($log) {
                SendAutomaticCommunicationJob::dispatch($log->id)->onQueue(
                    $channel === CommunicationLog::CHANNEL_EMAIL ? 'communications-email' : 'communications-sms'
                );
            }
        }
    }
}
