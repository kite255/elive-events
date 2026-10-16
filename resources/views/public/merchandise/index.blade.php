<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Official Merchandise | {{ $event->name }} | eLive Events</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('css/creato-font.css') }}">
    <style>
        :root{--elive-navy:#161943;--elive-blue:#007AB2;--elive-orange:#FF9800;--elive-bg:#F7F8FC;--elive-border:#E6E8EF;--elive-muted:#667085}
        *{box-sizing:border-box}
        body{margin:0;background:var(--elive-bg);color:#0F172A;font-family:'Creato Display',ui-sans-serif,system-ui,sans-serif;-webkit-font-smoothing:antialiased}
        a{color:inherit;text-decoration:none}
        .container{width:min(1180px,calc(100% - 40px));margin-inline:auto}
        .site-header{background:#fff;border-bottom:1px solid #E8EDF4}
        .header-inner{min-height:76px;display:flex;align-items:center;justify-content:space-between;gap:24px}
        .brand img{display:block;width:auto;height:48px;max-width:180px;object-fit:contain}
        .nav{display:flex;align-items:center;gap:30px}
        .nav-link{font-size:14px;font-weight:600;color:#64748B}
        .nav-link:hover,.nav-link.active{color:var(--elive-blue)}
        .login-btn,.reserve-btn{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:11px;background:var(--elive-navy);color:#fff;font:inherit;font-size:14px;font-weight:800;cursor:pointer;transition:background .2s}
        .login-btn{min-height:42px;padding:0 20px}
        .login-btn:hover,.reserve-btn:hover{background:var(--elive-blue)}
        .page-hero{background:#fff;border-bottom:1px solid #E8EDF4}
        .hero-inner{padding:32px 0 40px}
        .back-link{display:inline-flex;align-items:center;margin-bottom:22px;color:var(--elive-blue);font-size:13px;font-weight:700}
        .back-link:hover{color:var(--elive-orange)}
        .eyebrow{text-transform:uppercase;letter-spacing:.12em;font-size:12px;font-weight:800;color:var(--elive-blue);margin:0 0 10px}
        h1{color:var(--elive-navy);font-size:clamp(30px,4vw,48px);letter-spacing:-.035em;line-height:1.08;margin:0 0 12px}
        .subtitle{color:var(--elive-muted);font-size:16px;margin:0;line-height:1.6}
        .shop-section{padding:42px 0 68px}
        .section-heading{color:var(--elive-navy);font-size:25px;margin:0 0 24px;letter-spacing:-.02em}
        .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,320px),1fr));gap:24px;align-items:start}
        .card{overflow:hidden;background:#fff;border:1px solid var(--elive-border);border-radius:20px;box-shadow:0 12px 30px rgba(15,23,42,.05)}
        .product-image{background:#F1F5F9;aspect-ratio:16/10;display:flex;align-items:center;justify-content:center}
        .product-image img{width:100%;height:100%;object-fit:contain}
        .image-placeholder{color:#94A3B8;font-size:14px;font-weight:700}
        .card-body{padding:24px}
        .category{color:var(--elive-blue);text-transform:uppercase;letter-spacing:.08em;font-size:11px;font-weight:800;margin:0 0 9px}
        .product-title{color:var(--elive-navy);font-size:22px;line-height:1.2;margin:0 0 10px}
        .description{color:var(--elive-muted);font-size:14px;line-height:1.65;margin:0 0 16px;white-space:pre-line}
        .price{color:var(--elive-navy);font-size:21px;font-weight:900;margin:0 0 18px}
        .product-form{border-top:1px solid var(--elive-border);padding-top:18px}
        .form-title{font-size:15px;font-weight:800;color:var(--elive-navy);margin:0 0 16px}
        .fields{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
        .field{min-width:0}
        .field.full{grid-column:1/-1}
        label{display:block;margin-bottom:6px;font-size:13px;font-weight:700;color:#334155}
        input,select{display:block;width:100%;min-height:45px;padding:10px 12px;border:1px solid #D5DCE7;border-radius:10px;background:#fff;color:#0F172A;font:inherit;font-size:14px}
        input:focus,select:focus{outline:2px solid var(--elive-blue);outline-offset:1px;border-color:var(--elive-blue)}
        .reserve-btn{width:100%;min-height:47px;margin-top:20px;padding:12px 18px}
        .alert{padding:16px 18px;background:#FEF2F2;color:#991B1B;border:1px solid #FECACA;border-radius:14px;margin-bottom:24px}
        .alert ul{margin:8px 0 0;padding-left:20px}
        .empty{background:#fff;border:1px solid var(--elive-border);border-radius:18px;padding:32px;color:var(--elive-muted)}
        .site-footer{border-top:1px solid var(--elive-border);background:#fff}
        .footer-inner{display:flex;justify-content:space-between;align-items:center;gap:20px;min-height:90px;font-size:13px;color:var(--elive-muted)}
        .footer-links{display:flex;gap:22px}
        .footer-links a:hover{color:var(--elive-blue)}
        @media(max-width:640px){.container{width:calc(100% - 32px)}.header-inner{min-height:68px}.brand img{height:40px}.nav{gap:12px}.nav-link{display:none}.login-btn{min-height:38px;padding:0 14px}.hero-inner{padding:24px 0 32px}.shop-section{padding:30px 0 48px}.card-body{padding:20px}.fields{grid-template-columns:1fr}.footer-inner{flex-direction:column;justify-content:center;padding:25px 0;text-align:center}.footer-links{flex-wrap:wrap;justify-content:center}}
    </style>
</head>
<body>
<header class="site-header">
    <div class="container header-inner">
        <a href="{{ route('home') }}" class="brand" aria-label="eLive Events home">
            <img src="{{ asset('eLive-Logo.png') }}" alt="eLive Events">
        </a>
        <nav class="nav" aria-label="Primary navigation">
            <a href="{{ route('home') }}" class="nav-link">Home</a>
            <a href="{{ route('public.events.index') }}" class="nav-link active" aria-current="page">Events</a>
            <a href="{{ route('home') }}#contact" class="nav-link">Contact</a>
            <a href="/admin" class="login-btn">Login</a>
        </nav>
    </div>
</header>
<section class="page-hero">
    <div class="container hero-inner">
        <a href="{{ route('public.events.show', $event) }}" class="back-link">← Back to event</a>
        <p class="eyebrow">Official Collection</p>
        <h1>Shop Merchandise</h1>
        <p class="subtitle">Explore official merchandise for {{ $event->name }}.</p>
    </div>
</section>
<main class="shop-section">
    <div class="container">
        <h2 class="section-heading">Available Merchandise</h2>
        @if ($errors->any())
            <div class="alert" role="alert">
                <strong>Please check your details:</strong>
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <div class="grid">
            @forelse ($products as $product)
                <article class="card">
                    <div class="product-image">
                        @if ($product->image_path)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($product->image_path) }}" alt="{{ $product->name }}" loading="lazy">
                        @else
                            <span class="image-placeholder">Official Merchandise</span>
                        @endif
                    </div>
                    <div class="card-body">
                        <p class="category">{{ ucfirst(str_replace('_', ' ', $product->category)) }}</p>
                        <h3 class="product-title">{{ $product->name }}</h3>
                        @if ($product->description)
                            <p class="description">{{ $product->description }}</p>
                        @endif
                        <p class="price">
                            @if ($product->event_merchandise_id)
                                Price varies by selected option
                            @else
                                TZS {{ number_format($product->price_tzs) }}
                            @endif
                        </p>
                        <form class="product-form" action="{{ route('public.merchandise.store', ['event' => $event, 'product' => $product]) }}" method="POST">
                            @csrf
                            <p class="form-title">Reserve your item</p>
                            <div class="fields">
                                @if (! $product->event_merchandise_id && ! empty($product->colors))
                                    <div class="field">
                                        <label for="color-{{ $product->id }}">Colour</label>
                                        <select id="color-{{ $product->id }}" name="color" required>
                                            <option value="">Choose colour</option>
                                            @foreach ($product->colors as $color)
                                                <option value="{{ $color }}" @selected(old('color') === $color)>{{ $color }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                                @if (! $product->event_merchandise_id && ! empty($product->sizes))
                                    <div class="field">
                                        <label for="size-{{ $product->id }}">Size</label>
                                        <select id="size-{{ $product->id }}" name="size" required>
                                            <option value="">Choose size</option>
                                            @foreach ($product->sizes as $size)
                                                <option value="{{ $size }}" @selected(old('size') === $size)>{{ $size }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                                @if ($product->event_merchandise_id)
                                    <div class="field full">
                                        <label for="variant-{{ $product->id }}">Available variant</label>
                                        <select id="variant-{{ $product->id }}" name="merchandise_variant_id" required>
                                            <option value="">Select option</option>
                                            @foreach ($product->sourceMerchandise?->activeVariants ?? [] as $variant)
                                                @if ($variant->currency === 'TZS' && $variant->remainingQuantity() > 0)
                                                    <option value="{{ $variant->id }}">{{ $variant->displayName() }} — TZS {{ number_format((float) $variant->price) }} ({{ $variant->remainingQuantity() }} available)</option>
                                                @endif
                                            @endforeach
                                        </select>
                                    </div>
                                @endif
                                <div class="field">
                                    <label for="quantity-{{ $product->id }}">Quantity</label>
                                    <input id="quantity-{{ $product->id }}" name="quantity" type="number" min="1" max="20" value="{{ old('quantity', 1) }}" required>
                                </div>
                                <div class="field">
                                    <label for="buyer-name-{{ $product->id }}">Your name</label>
                                    <input id="buyer-name-{{ $product->id }}" name="buyer_name" autocomplete="name" value="{{ old('buyer_name') }}" maxlength="150" required>
                                </div>
                                <div class="field">
                                    <label for="buyer-phone-{{ $product->id }}">Phone number</label>
                                    <input id="buyer-phone-{{ $product->id }}" name="buyer_phone" type="tel" autocomplete="tel" value="{{ old('buyer_phone') }}" maxlength="30" required>
                                </div>
                                <div class="field">
                                    <label for="buyer-email-{{ $product->id }}">Email (optional)</label>
                                    <input id="buyer-email-{{ $product->id }}" name="buyer_email" type="email" autocomplete="email" value="{{ old('buyer_email') }}">
                                </div>
                            </div>
                            <input type="hidden" name="fulfillment_method" value="event_pickup">
                            <button type="submit" class="reserve-btn">Reserve item</button>
                        </form>
                    </div>
                </article>
            @empty
                <div class="empty">Merchandise is not available for ordering yet.</div>
            @endforelse
        </div>
    </div>
</main>
<footer class="site-footer">
    <div class="container footer-inner">
        <p>© {{ date('Y') }} eLive Events. All rights reserved.</p>
        <nav class="footer-links" aria-label="Footer navigation">
            <a href="{{ route('home') }}">Home</a>
            <a href="{{ route('public.events.index') }}">Events</a>
            <a href="{{ route('public.donations.index') }}">Donations</a>
            <a href="{{ route('home') }}#contact">Contact</a>
        </nav>
    </div>
</footer>
</body>
</html>
