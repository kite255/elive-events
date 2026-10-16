<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Merchandise Order | eLive Events</title>
    <style>
        :root { --navy:#161943; --blue:#007AB2; --orange:#FF9800; --muted:#64748b; --border:#e2e8f0; }
        * { box-sizing:border-box; }
        body { margin:0; font-family:"Creato Display", Inter, system-ui, -apple-system, "Segoe UI", sans-serif; color:var(--navy); background:#f6f8fb; }
        a { color:var(--blue); }
        .shell { max-width:1050px; margin:0 auto; padding:0 22px; }
        .topbar { background:#fff; border-bottom:1px solid var(--border); }
        .topbar-inner { display:flex; align-items:center; justify-content:space-between; min-height:76px; gap:15px; }
        .brand { font-size:24px; font-weight:850; color:var(--navy); text-decoration:none; letter-spacing:-.6px; }
        .brand span { color:var(--orange); }
        .toplink { text-decoration:none; font-size:14px; font-weight:700; }
        .page { padding-top:48px; padding-bottom:70px; }
        .eyebrow { color:#e57d00; font-size:12px; font-weight:800; text-transform:uppercase; letter-spacing:1.6px; margin:0 0 10px; }
        h1 { font-size:clamp(29px,4vw,40px); line-height:1.12; margin:0 0 12px; letter-spacing:-1px; }
        .subtitle { color:var(--muted); line-height:1.6; margin:0 0 30px; }
        .layout { display:grid; grid-template-columns:minmax(0,1.45fr) minmax(280px,1fr); gap:22px; align-items:start; }
        .panel { border:1px solid var(--border); border-radius:18px; background:white; padding:26px; box-shadow:0 8px 28px rgba(22,25,67,.035); min-width:0; }
        .panel h2 { font-size:20px; margin:0 0 20px; letter-spacing:-.4px; }
        .product { display:flex; align-items:center; gap:18px; padding-bottom:22px; border-bottom:1px solid var(--border); }
        .product-photo { flex:0 0 160px; height:160px; border-radius:12px; background:#f1f5f9; overflow:hidden; display:flex; justify-content:center; align-items:center; color:var(--muted); font-size:12px; text-align:center; }
        .product-photo img { height:100%; width:100%; object-fit:contain; }
        .product-name { margin:0 0 7px; font-size:18px; line-height:1.4; font-weight:800; }
        .product-meta { margin:0 0 6px; font-size:14px; color:var(--muted); }
        .details { display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-top:23px; }
        .field-label { color:var(--muted); display:block; font-size:12px; margin-bottom:7px; }
        .field-value { font-size:14px; font-weight:750; overflow-wrap:anywhere; }
        .reference { font-variant-numeric:tabular-nums; }
        .summary-line { display:flex; justify-content:space-between; gap:15px; align-items:center; padding:14px 0; border-bottom:1px solid var(--border); font-size:14px; }
        .summary-line span { color:var(--muted); }
        .summary-total { display:flex; justify-content:space-between; align-items:center; padding:24px 0 18px; gap:12px; }
        .summary-total span { font-weight:750; }
        .summary-total strong { font-size:26px; letter-spacing:-.6px; white-space:nowrap; }
        .status { display:inline-flex; border-radius:100px; padding:7px 12px; background:#fff5e5; color:#925000; font-size:12px; font-weight:800; }
        .status.paid { background:#eaf9ef; color:#146b38; }
        .status.expired { background:#fff0ef; color:#a02c24; }
        .message { border-radius:12px; padding:14px 16px; line-height:1.5; font-size:13px; margin:15px 0; background:#eff7fb; color:#134d69; }
        .message.error { background:#fff1ee; color:#9e3228; }
        .message.success { background:#eaf9ef; color:#146b38; }
        .pay-button { display:flex; justify-content:center; align-items:center; width:100%; min-height:50px; border:0; border-radius:11px; padding:12px 18px; background:var(--blue); color:#fff; font:inherit; font-size:15px; font-weight:800; cursor:pointer; }
        .pay-button:hover { background:#00618e; }
        .secure { text-align:center; font-size:12px; color:var(--muted); margin:13px 0 0; line-height:1.5; }
        .back { display:inline-flex; gap:8px; align-items:center; margin-top:25px; text-decoration:none; font-weight:750; font-size:14px; }
        @media(max-width:720px) { .topbar-inner { min-height:65px; } .page { padding-top:30px; } .layout { grid-template-columns:1fr; } .panel { padding:20px; } }
        @media(max-width:420px) { .shell { padding:0 14px; } .product-photo { flex-basis:110px; height:110px; } .product-name { font-size:15px; } .details { gap:16px 12px; } .summary-total strong { font-size:23px; } }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="shell topbar-inner">
            <a class="brand" href="{{ url('/') }}">eLive<span>.</span> Events</a>
            <a class="toplink" href="{{ route('public.events.show', $order->event) }}">&larr; Back to event</a>
        </div>
    </header>
    <main class="shell page">
        <p class="eyebrow">Official Merchandise</p>
        <h1>Your merchandise order</h1>
        <p class="subtitle">Review your order details and complete your payment securely.</p>
        <div class="layout">
            <section class="panel" aria-labelledby="order-heading">
                <h2 id="order-heading">Order details</h2>
                <div class="product">
                    <div class="product-photo">
                        @if($order->product?->image_path)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($order->product->image_path) }}" alt="{{ $order->product->name }}">
                        @else
                            Official Merchandise
                        @endif
                    </div>
                    <div>
                        <h3 class="product-name">{{ $order->product?->name ?? 'Merchandise item' }}</h3>
                        @if($order->color || $order->size)
                            <p class="product-meta">{{ collect([$order->color, $order->size])->filter()->implode(' / ') }}</p>
                        @endif
                        <p class="product-meta">Quantity: <strong>{{ $order->quantity }}</strong></p>
                    </div>
                </div>
                <div class="details">
                    <div><span class="field-label">Order reference</span><div class="field-value reference">{{ $order->order_number }}</div></div>
                    <div><span class="field-label">Collection method</span><div class="field-value">Event pickup</div></div>
                    <div><span class="field-label">Customer</span><div class="field-value">{{ $order->buyer_name }}</div></div>
                    <div><span class="field-label">Order status</span><div class="field-value"><span class="status {{ $order->status === 'paid' ? 'paid' : ($order->status === 'pending_payment' && $order->expires_at?->isFuture() ? '' : 'expired') }}">{{ ucfirst(str_replace('_', ' ', $order->status)) }}</span></div></div>
                </div>
                <a href="{{ route('public.events.show', $order->event) }}" class="back">&larr; Return to event</a>
            </section>
            <aside class="panel" aria-labelledby="payment-heading">
                <h2 id="payment-heading">Payment summary</h2>
                <div class="summary-line"><span>Unit price</span><strong>TZS {{ number_format($order->unit_price_tzs) }}</strong></div>
                <div class="summary-line"><span>Quantity</span><strong>{{ $order->quantity }}</strong></div>
                <div class="summary-total"><span>Total payable</span><strong>TZS {{ number_format($order->total_tzs) }}</strong></div>
                @if(session('payment_error'))
                    <div class="message error" role="alert">{{ session('payment_error') }}</div>
                @endif
                @if($order->status === 'paid')
                    <div class="message success">Payment confirmed. Keep your order reference for collection at the event.</div>
                @elseif($order->status === 'pending_payment' && $order->expires_at && $order->expires_at->isFuture())
                    <div class="message">Reservation valid until <strong>{{ $order->expires_at->format('d M Y, H:i') }}</strong>. Complete your payment before it expires.</div>
                    @if($order->payment?->status === \App\Models\Payment::STATUS_COMPLETED)
                        <div class="message">Payment received; confirmation is being processed. If your reservation expires, contact the organizer for assistance.</div>
                    @else
                        <form method="POST" action="{{ route('public.merchandise.orders.pay', $order->public_token) }}">
                            @csrf
                            <button type="submit" class="pay-button">Pay securely with Pesapal &rarr;</button>
                        </form>
                        <p class="secure">You'll be redirected to Pesapal to complete payment.</p>
                    @endif
                @else
                    <div class="message error">This reservation is no longer available for online payment. Contact the organizer if you've already paid.</div>
                @endif
            </aside>
        </div>
    </main>
</body>
</html>
