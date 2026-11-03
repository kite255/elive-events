<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Secure Checkout | eLive Events</title>
    <style>
        :root{color-scheme:light;--navy:#161943;--blue:#007ab2;--border:#dfe6f0;--muted:#64748b}
        *{box-sizing:border-box}body{margin:0;background:#f6f8fc;color:#111827;font-family:Inter,system-ui,-apple-system,"Segoe UI",sans-serif}
        main{max-width:620px;margin:6vh auto;padding:16px}
        .card{background:white;border:1px solid var(--border);border-radius:22px;box-shadow:0 18px 55px rgba(22,25,67,.07);padding:clamp(20px,5vw,36px)}
        .eyebrow{text-transform:uppercase;color:var(--blue);font-weight:800;letter-spacing:.08em;font-size:12px}
        h1{font-size:27px;color:var(--navy);margin:10px 0 24px}h2{font-size:17px;margin:20px 0 12px}
        .summary{border-radius:14px;background:#f4f7fb;padding:20px;margin-bottom:24px}
        .summary small,.muted{color:var(--muted)}.price{font-size:30px;font-weight:800;color:var(--navy);margin-top:5px}
        .method{display:flex;gap:12px;align-items:center;border:1px solid var(--border);border-radius:12px;padding:16px;margin:10px 0;cursor:pointer}
        .method:has(input:checked){background:#eef8fd;border-color:var(--blue)}
        .method input{accent-color:var(--blue)}.method strong{display:block}.method small{display:block;color:var(--muted);margin-top:3px}
        label.field{font-weight:650;font-size:14px;display:block;margin:18px 0 8px}
        input[type=tel]{display:block;width:100%;border:1px solid #cdd7e4;border-radius:10px;padding:14px;font-size:16px}
        button,.button{display:block;width:100%;background:var(--navy);color:#fff;border:0;border-radius:12px;padding:15px;font-weight:700;font-size:15px;cursor:pointer;text-align:center;text-decoration:none}
        button:disabled{opacity:.45;cursor:not-allowed}.helper{text-align:center;color:var(--muted);font-size:13px;line-height:1.6;margin-top:12px}
        .notice{background:#fff7e6;color:#854d0e;padding:14px;border-radius:10px;margin-bottom:18px;line-height:1.6}
        .success{background:#effaf2;color:#166534}.token{background:#f3f6fb;font-size:22px;letter-spacing:.08em;font-weight:800;word-break:break-all;padding:16px;border-radius:10px;text-align:center}
        .qr{display:block;width:220px;max-width:100%;height:auto;margin:15px auto}
        .footer{border-top:1px solid var(--border);padding-top:18px;margin-top:24px;text-align:center;color:var(--muted);font-size:12px}
        [hidden]{display:none!important}a{color:var(--blue)}
    </style>
</head>
<body>
<main><div class="card">
    <div class="eyebrow">eLive Events · Secure Checkout</div>
    <h1>Complete Your Payment</h1>
    <div class="summary">
        <small>Total to pay</small>
        <div class="price">{{ $payment->currency }} {{ number_format((float) $payment->amount, 2) }}</div>
        <small>Order {{ $payment->reference }}</small>
    </div>
    @if(session('error'))
        <div class="notice" role="alert">{{ session('error') }}</div>
    @endif
    @if($payment->isPending() && filled(data_get($payment->metadata, 'checkout_attempted_at')))
        <div class="notice">An earlier checkout request is awaiting reconciliation. Do not attempt another payment. Please check payment status or contact support.</div>
        <a class="button" href="{{ route('payments.status', $payment) }}">Check Payment Status</a>
    @elseif($payment->isProcessing())
        @if(filled(data_get($payment->metadata, 'wallet_attempted_at')))
            <div class="notice success">A mobile payment authorization was requested. Check your phone and approve the payment there. A request is not proof of payment.</div>
        @elseif(data_get($payment->metadata, 'checkout_method') === 'qr')
            <h2>Pay with QR / Payment Token</h2>
            @if($qrDetails['qr_image'])
                <img class="qr" alt="Selcom order QR code" src="{{ $qrDetails['qr_image'] }}">
            @endif
            @if($qrDetails['payment_token'])
                <p class="muted">Selcom payment token for this order:</p>
                <div class="token">{{ $qrDetails['payment_token'] }}</div>
            @endif
            @if(! $qrDetails['qr_image'] && ! $qrDetails['payment_token'])
                <div class="notice">Selcom did not return a usable QR image or payment token for this order. Contact support before paying.</div>
            @else
                <p class="helper">Use a compatible payment application and confirm the merchant and amount before approving. Do not reuse this token for another order.</p>
            @endif
        @else
            <div class="notice">This Selcom order has already been initialized. Check its current status before attempting another payment.</div>
        @endif
        <a class="button" href="{{ route('payments.status', $payment) }}">Check Payment Status</a>
        <p class="helper">If money was deducted, do not submit another payment request.</p>
    @elseif($payment->isPending())
        <h2>Select payment method</h2>
        <form method="POST" action="{{ route('payments.submit', $payment) }}" id="checkout-form">
            @csrf
            <label class="method">
                <input type="radio" name="method" value="mobile" {{ old('method', 'mobile') === 'mobile' ? 'checked' : '' }}>
                <span><strong>Mobile Money</strong><small>Request payment authorization directly on your phone</small></span>
            </label>
            <label class="method">
                <input type="radio" name="method" value="qr" {{ old('method') === 'qr' ? 'checked' : '' }}>
                <span><strong>QR Code / Payment Token</strong><small>Use Selcom's order-linked QR or payment token</small></span>
            </label>
            <label class="method" style="opacity:.5;cursor:not-allowed">
                <input type="radio" disabled>
                <span><strong>Debit / Credit Card — Coming Soon</strong><small>Unavailable until Selcom enables full card checkout</small></span>
            </label>
            <div id="phone-container">
                <label for="phone" class="field">Mobile Money Number</label>
                <input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel"
                    placeholder="2557XXXXXXXX" maxlength="24" value="{{ old('phone') }}"
                    aria-describedby="phone-help">
                <p class="helper" id="phone-help">You will authorize payment securely on your phone. Do not enter your PIN on this website.</p>
            </div>
            @error('phone')<div class="notice" role="alert">{{ $message }}</div>@enderror
            @error('method')<div class="notice" role="alert">{{ $message }}</div>@enderror
            <button type="submit" id="submit-payment">Request Payment on Phone</button>
        </form>
        <script>
            const form = document.getElementById('checkout-form');
            const methods = form.querySelectorAll('input[name=method]');
            const phone = document.getElementById('phone');
            const phoneContainer = document.getElementById('phone-container');
            const submit = document.getElementById('submit-payment');
            function updateMethod() {
                const mobile = form.querySelector('input[name=method]:checked').value === 'mobile';
                phoneContainer.hidden = !mobile;
                phone.required = mobile;
                submit.textContent = mobile ? 'Request Payment on Phone' : 'Continue with QR / Payment Token';
            }
            methods.forEach(radio => radio.addEventListener('change', updateMethod));
            updateMethod();
            form.addEventListener('submit', () => { submit.disabled = true; submit.textContent = 'Submitting securely…'; });
        </script>
    @else
        <div class="notice">This payment can no longer be initiated. Check its status for details.</div>
        <a class="button" href="{{ route('payments.status', $payment) }}">View Payment Status</a>
    @endif
    <div class="footer">Payment securely processed by Selcom. Tickets are issued only after confirmed payment.<br>
        <a href="{{ route('payments.status', $payment) }}">View payment status</a>
    </div>
</div></main>
</body>
</html>
