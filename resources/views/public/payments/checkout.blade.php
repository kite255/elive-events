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
        .eyebrow{text-transform:none;color:var(--blue);font-weight:800;letter-spacing:.08em;font-size:12px}
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
        .payment-networks{margin:18px 0;padding:14px;background:#f8fafc;border:1px solid var(--border);border-radius:12px}
        .payment-networks-title{font-size:13px;font-weight:700;color:var(--navy);margin-bottom:12px}
        .wallet-logos{display:flex;flex-wrap:nowrap;align-items:center;justify-content:space-between;gap:10px;width:100%}
        .wallet-logo{flex:1 1 0;min-width:0;height:56px;background:transparent;border:0;box-shadow:none;display:flex;align-items:center;justify-content:center;padding:0}
        .wallet-logo img{display:block;width:auto;max-width:100%;height:auto;max-height:42px;object-fit:contain;margin:0 auto;background:transparent}
        .wallet-logo span{font-size:12px;font-weight:700;text-align:center;color:var(--navy)}
        .tips-panel{margin:16px 0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:10px;text-align:center;background:#f8fafc;padding:18px 12px;border:1px solid var(--border);border-radius:12px}
        .tips-panel img{display:block;width:190px;max-width:100%;height:auto;object-fit:contain;margin:0 auto}
        .tips-panel span{max-width:400px;color:var(--muted);font-size:12px;line-height:1.5}
        .network-note{font-size:11px;color:var(--muted);margin:10px 0 0;line-height:1.5}
        @media (max-width:650px){
            main{margin:12px auto;padding:12px}
            .card{border-radius:16px;padding:20px 16px}
            h1{font-size:23px}
            .price{font-size:26px}
            .wallet-logos{gap:6px}
            .wallet-logo{height:42px;padding:0}
            .wallet-logo img{max-height:32px}
            .tips-panel img{width:160px}
        }
        @media (max-width:390px){
            .wallet-logos{gap:4px}
            .wallet-logo{height:36px;padding:0}
            .wallet-logo img{max-height:26px}
            .payment-networks{padding:8px}
        }
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
            <h2>Pay with QR Code</h2>
            <div class="tips-panel">
                <img src="{{ asset('images/payment-networks/tips.png') }}"
                     alt="TIPS — Tanzania Instant Payments System" loading="lazy"
                     onerror="this.hidden=true">
                <span>TIPS is Tanzania's instant-payment infrastructure, not a mobile wallet. Use a compatible payment app to scan the order QR.</span>
            </div>
            @if($qrDetails['qr_image'])
                <img class="qr" alt="Selcom order QR code" src="{{ $qrDetails['qr_image'] }}">
            @endif
            @if($qrDetails['payment_token'])
                <p class="muted">Alternatively, use this payment token:</p>
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
                <span><strong>Pay with QR Code</strong><small>Scan the QR code using a supported payment app.</small></span>
            </label>
            <div id="phone-container">
                <label for="phone" class="field">Mobile Money Number</label>
                <input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel"
                    placeholder="e.g. 255712345678" maxlength="24" value="{{ old('phone') }}"
                    aria-describedby="phone-help">
                <p class="helper" id="phone-help">You will authorize payment securely on your phone. Do not enter your PIN on this website.</p>
            </div>
            <div id="wallet-networks" class="payment-networks">
                <div class="payment-networks-title">Mobile money networks</div>
                <div class="wallet-logos">
                    @foreach ([
                        ['file' => 'm-pesa.png', 'label' => 'Vodacom M-Pesa'],
                        ['file' => 'mixx-by-yas.png', 'label' => 'Mixx by Yas'],
                        ['file' => 'airtel-money.png', 'label' => 'Airtel Money'],
                        ['file' => 'halopesa.png', 'label' => 'HaloPesa'],
                        ['file' => 't-pesa.png', 'label' => 'T-Pesa'],
                    ] as $network)
                        <div class="wallet-logo" title="{{ $network['label'] }}">
                            <img src="{{ asset('images/payment-networks/' . $network['file']) }}"
                                 alt="{{ $network['label'] }}" loading="lazy"
                                 onerror="this.hidden=true;this.nextElementSibling.hidden=false">
                            <span hidden>{{ $network['label'] }}</span>
                        </div>
                    @endforeach
                </div>
                <p class="network-note">Supported payment channels depend on Selcom merchant activation. Enter your number to request mobile payment.</p>
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
            const walletNetworks = document.getElementById('wallet-networks');
            const submit = document.getElementById('submit-payment');
            function updateMethod() {
                const mobile = form.querySelector('input[name=method]:checked').value === 'mobile';
                phoneContainer.hidden = !mobile;
                walletNetworks.hidden = !mobile;
                phone.required = mobile;
                submit.textContent = mobile ? 'Request Payment on Phone' : 'Generate Payment QR Code';
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
