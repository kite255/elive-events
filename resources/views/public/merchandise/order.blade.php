<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Merchandise order</title>
    <style>
        body{font-family:system-ui,sans-serif;background:#f7f5f3;padding:24px;color:#282020}
        main{background:#fff;max-width:650px;margin:auto;padding:30px;border-radius:16px}
        a{color:#871e21}
        .btn{display:inline-block;background:#871e21;color:#fff;padding:12px 20px;border:0;border-radius:8px;cursor:pointer;font:inherit}
        .alert{background:#fff0ed;padding:12px;border-radius:8px}
    </style>
</head>
<body>
<main>
    <h1>Merchandise order</h1>
    <p>Reference: {{ $order->order_number }}</p>
    <p>Product: {{ $order->product->name }}</p>
    <p>
        @if($order->color){{ $order->color }}@endif
        @if($order->size) / {{ $order->size }}@endif
        · Quantity {{ $order->quantity }}
    </p>
    <p>Total: TZS {{ number_format($order->total_tzs) }}</p>
    <p>Status: <strong>{{ ucfirst(str_replace('_', ' ', $order->status)) }}</strong></p>
    <p>Collection method: Event pickup</p>
    @if(session('payment_error'))
        <p class="alert">{{ session('payment_error') }}</p>
    @endif
    @if($order->status === 'paid')
        <p><strong>Payment confirmed.</strong> Keep this order reference for collection.</p>
    @elseif($order->status === 'pending_payment' && $order->expires_at && $order->expires_at->isFuture())
        <p>Reservation expires: {{ $order->expires_at->format('d M Y H:i') }}</p>
        @if($order->payment?->status === \App\Models\Payment::STATUS_COMPLETED)
            <p>Payment received; confirmation is being processed. If the reservation expired, contact the organizer for assistance.</p>
        @else
            <form method="POST" action="{{ route('public.merchandise.orders.pay', $order->public_token) }}">
                @csrf
                <button type="submit" class="btn">Pay with Pesapal</button>
            </form>
        @endif
    @else
        <p class="alert">This reservation is no longer available for online payment. Contact the organizer if you have already paid.</p>
    @endif
    <p><a href="{{ route('public.events.show', $order->event) }}">Return to event</a></p>
</main>
</body>
</html>
