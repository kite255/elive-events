@php
    use Illuminate\Support\Str;

    $bannerUrl = null;

    if ($campaign->banner_image_path) {
        if (Str::startsWith($campaign->banner_image_path, ['http://', 'https://'])) {
            $bannerUrl = $campaign->banner_image_path;
        } elseif (Str::startsWith($campaign->banner_image_path, ['storage/', '/storage/'])) {
            $bannerUrl = asset(ltrim($campaign->banner_image_path, '/'));
        } else {
            $bannerUrl = asset('storage/' . ltrim($campaign->banner_image_path, '/'));
        }
    }

    $displayOnly = $campaign->payment_mode === \App\Models\DonationCampaign::PAYMENT_MODE_CLIENT_DIRECT
        && $campaign->direct_payment_behavior === \App\Models\DonationCampaign::DIRECT_BEHAVIOR_DISPLAY_ONLY;

    $showProgress = ! $displayOnly && (
        $campaign->show_goal
        || $campaign->show_amount_raised
        || $campaign->show_percentage
        || $campaign->show_donor_count
    );
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $campaign->title }} | eLive Events</title>
    <meta
        name="description"
        content="{{ Str::limit(strip_tags((string) $campaign->description), 155) ?: 'Support this campaign through eLive Events.' }}"
    >

    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('css/creato-font.css') }}">
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif

    <script defer src="https://analytics.elive.co.tz/script.js" data-website-id="a4922c3a-16d3-4451-a2b4-3224dbc16f84"></script>

    <style>
        :root {
            --elive-navy: #161943;
            --elive-blue: #007AB2;
            --elive-orange: #FF9800;
            --elive-bg: #F7F8FC;
            --elive-border: #E6E8EF;
            --elive-muted: #667085;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background: var(--elive-bg);
            color: #0F172A;
            font-family: 'Creato Display', ui-sans-serif, system-ui, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        a { color: inherit; text-decoration: none; }

        .container {
            width: min(1180px, calc(100% - 40px));
            margin-inline: auto;
        }

        .site-header {
            position: sticky;
            top: 0;
            z-index: 50;
            background: rgba(255,255,255,.97);
            border-bottom: 1px solid #E8EDF4;
            backdrop-filter: blur(12px);
        }

        .header-inner {
            min-height: 76px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
        }

        .brand img { display:block; height:48px; width:auto; }

        .nav {
            display: flex;
            align-items: center;
            gap: 30px;
        }

        .nav-link {
            color: #64748B;
            font-size: 14px;
            font-weight: 600;
        }

        .nav-link:hover,
        .nav-link.active { color: var(--elive-blue); }

        .login-btn {
            min-height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 20px;
            border-radius: 11px;
            background: var(--elive-navy);
            color: #FFFFFF;
            font-size: 14px;
            font-weight: 700;
            box-shadow: 0 5px 14px rgba(22,25,67,.16);
        }

        .mobile-menu-button,
        .mobile-nav { display:none; }

        .page-hero {
            background: #FFFFFF;
            border-bottom: 1px solid #E8EDF4;
        }

        .hero-shell { padding: 34px 0 40px; }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 22px;
            color: var(--elive-blue);
            font-size: 13px;
            font-weight: 700;
        }

        .hero-card {
            overflow: hidden;
            border: 1px solid #DCE4EE;
            border-radius: 24px;
            background: #FFFFFF;
            box-shadow: 0 16px 36px rgba(15,23,42,.07);
        }

        .hero-visual {
            position: relative;
            min-height: 370px;
            overflow: hidden;
            background: var(--elive-navy);
        }

        .hero-image {
            width: 100%;
            height: 370px;
            display: block;
            object-fit: cover;
        }

        .hero-fallback {
            width: 100%;
            height: 370px;
            background: var(--elive-navy);
        }

        .hero-overlay {
            position: absolute;
            inset: 0;
            background: rgba(15,23,42,.55);
        }

        .hero-content {
            position: absolute;
            left: 32px;
            right: 32px;
            bottom: 34px;
            z-index: 2;
            color: #FFFFFF;
        }

        .campaign-badge {
            min-height: 26px;
            display: inline-flex;
            align-items: center;
            padding: 0 10px;
            margin-bottom: 12px;
            border-radius: 6px;
            background: var(--elive-orange);
            color: #FFFFFF;
            font-size: 10px;
            font-weight: 800;
        }

        .hero-title {
            margin: 0;
            max-width: 880px;
            font-size: clamp(32px, 4.5vw, 52px);
            line-height: 1.03;
            letter-spacing: -.035em;
            font-weight: 800;
        }

        .hero-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px 20px;
            margin-top: 14px;
            color: rgba(255,255,255,.9);
            font-size: 13px;
            font-weight: 600;
        }

        .details-section { padding: 34px 0 72px; }

        .details-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 26px;
            align-items: start;
        }

        .content-card,
        .side-card {
            background: #FFFFFF;
            border: 1px solid var(--elive-border);
            border-top: 4px solid var(--elive-orange);
            border-radius: 20px;
            box-shadow: 0 7px 22px rgba(15,23,42,.04);
        }

        .content-card { padding: 30px; }

        .section-eyebrow {
            margin: 0 0 8px;
            color: var(--elive-orange);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .12em;
            text-transform: uppercase;
        }

        .section-title {
            margin: 0;
            color: var(--elive-navy);
            font-size: 25px;
            line-height: 1.15;
        }

        .campaign-description {
            margin-top: 18px;
            color: #475569;
            font-size: 15px;
            line-height: 1.8;
            white-space: pre-line;
        }

        .progress-card {
            margin-top: 28px;
            padding: 22px;
            border: 1px solid #E2E8F0;
            border-radius: 16px;
            background: #F8FAFC;
        }

        .gallery-section {
            margin-top: 30px;
        }

        .gallery-grid {
            display: flex;
            gap: 16px;
            overflow-x: auto;
            overscroll-behavior-inline: contain;
            scrollbar-width: thin;
            margin-top: 16px;
            padding-bottom: 12px;
        }
        .gallery-group { display: flex; flex: 0 0 auto; gap: 20px; }
        .gallery-grid:focus-visible { outline: 3px solid var(--elive-blue); }
        .gallery-item { flex: 0 0 calc((min(1180px, 100vw - 40px) - 40px) / 3); min-height:260px; }
        .gallery-item img { height: 260px !important; object-fit: cover; }
        .gallery-hint { color: var(--elive-muted); font-size: 13px; }
        .gallery-heading { display:flex; align-items:end; justify-content:space-between; gap:16px; flex-wrap:wrap; }
        .gallery-controls { display:flex; gap:10px; }
        .gallery-control { display:inline-flex; align-items:center; justify-content:center; height:44px; width:44px; border:1px solid #CBD5E1; background:white; border-radius:50%; color:var(--elive-navy); font-size:22px; cursor:pointer; }
        .gallery-control:hover, .gallery-control:focus-visible { border-color:var(--elive-blue); background:#EFF8FC; outline-offset:2px; }
        .gallery-control:disabled { opacity:.35; cursor:not-allowed; }
        .gallery-section { min-width:0; }
        .gallery-grid { scrollbar-width:none; touch-action:pan-x; cursor:grab; }
        .gallery-grid::-webkit-scrollbar { display:none; }
        .gallery-grid:active { cursor:grabbing; }
        body.gallery-lightbox-open { overflow:hidden; }
        .gallery-lightbox[hidden] { display:none; }
        .gallery-lightbox { position:fixed; inset:0; z-index:2000; background:rgba(11,31,58,.96); display:grid; grid-template-rows:auto minmax(0,1fr) auto; gap:12px; padding:20px; }
        .gallery-lightbox-toolbar { display:flex; justify-content:flex-end; }
        .gallery-lightbox-button { display:grid; place-items:center; width:46px; height:46px; border:1px solid rgba(255,255,255,.3); border-radius:50%; background:rgba(255,255,255,.12); color:white; font-size:28px; cursor:pointer; }
        .gallery-lightbox-stage { display:grid; place-items:center; position:relative; min-height:0; }
        .gallery-lightbox-image { max-width:min(1180px,calc(100vw - 150px)); max-height:calc(100vh - 160px); object-fit:contain; border-radius:14px; }
        .gallery-lightbox-previous,.gallery-lightbox-next { position:absolute; top:50%; transform:translateY(-50%); }
        .gallery-lightbox-previous { left:4px; }
        .gallery-lightbox-next { right:4px; }
        .gallery-lightbox-footer { color:white; text-align:center; }
        .details-grid > * { min-width:0; }
        .contact-value { overflow-wrap:anywhere; }
        .contact-org-name { font-weight:800; color:var(--elive-navy); margin-top:14px; }
        .donate-link { display: inline-flex; padding: 12px 20px; margin-top: 18px; border-radius: 10px; background: var(--elive-orange); color: var(--elive-navy); font-weight: 800; }
        .contact-links { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 18px; }
        .contact-links a { border: 1px solid #DCE4EE; padding: 11px 16px; border-radius: 10px; color: var(--elive-blue); font-weight: 700; }
        #payment-methods, #contact-enquiry { scroll-margin-top: 100px; }

        .gallery-item {
            overflow: hidden;
            display: block;
            border: 1px solid #E2E8F0;
            border-radius: 16px;
            background: #F8FAFC;
        }

        .gallery-item img {
            width: 100%;
            height: auto;
            display: block;
        }

        .progress-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0,1fr));
            gap: 14px;
            margin-top: 16px;
        }

        .stat {
            padding: 14px;
            border-radius: 12px;
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
        }

        .stat-label {
            display:block;
            color:#64748B;
            font-size:11px;
            font-weight:700;
            text-transform:uppercase;
            letter-spacing:.06em;
        }

        .stat-value {
            display:block;
            margin-top:5px;
            color:var(--elive-navy);
            font-size:18px;
            font-weight:800;
        }

        .side-card {
            position: sticky;
            top: 98px;
            padding: 24px;
        }

        .side-card h2 {
            margin: 0;
            color: var(--elive-navy);
            font-size: 22px;
        }

        .side-copy {
            margin: 9px 0 18px;
            color: #64748B;
            font-size: 14px;
            line-height: 1.6;
        }

        .payment-method {
            padding: 16px;
            margin-top: 12px;
            border: 1px solid #E2E8F0;
            border-radius: 14px;
            background: #F8FAFC;
        }

        .payment-method h3 {
            margin: 0 0 10px;
            color: var(--elive-navy);
            font-size: 16px;
        }

        .payment-method p {
            margin: 6px 0;
            color: #475569;
            font-size: 13px;
            line-height: 1.55;
        }

        .notice {
            padding: 14px 16px;
            border-radius: 12px;
            background: #FFF7ED;
            color: #9A3412;
            font-size: 13px;
            line-height: 1.6;
        }

        .site-footer {
            background: var(--elive-navy);
            color: #CBD5E1;
        }

        .footer-inner {
            min-height: 92px;
            display:flex;
            align-items:center;
            justify-content:space-between;
            gap:24px;
        }

        .footer-inner p {
            margin:0;
            color:#94A3B8;
            font-size:14px;
        }

        .footer-links {
            display:flex;
            gap:22px;
            font-size:14px;
            font-weight:600;
        }

        .footer-links a:hover { color:#FFFFFF; }

        @media (max-width: 900px) {
            .gallery-item { flex-basis:calc((min(1180px, 100vw - 40px) - 20px) / 2); }
            .details-grid { grid-template-columns:1fr; }
            .side-card { position:static; }
        }

        @media (max-width: 760px) {
            .container { width:min(100% - 28px, 1180px); }
            .header-inner { min-height:68px; }
            .brand img { height:40px; }
            .nav { display:none; }

            .mobile-menu-button {
                display:inline-flex;
                width:44px;
                height:44px;
                align-items:center;
                justify-content:center;
                border:1px solid #E2E8F0;
                border-radius:999px;
                background:#FFFFFF;
                color:var(--elive-navy);
                cursor:pointer;
            }

            .mobile-nav {
                border-top:1px solid #EEF2F7;
                padding:10px 0 16px;
            }

            .mobile-nav.is-open { display:block; }

            .mobile-nav-list {
                display:flex;
                flex-direction:column;
                gap:4px;
            }

            .mobile-nav-link {
                min-height:44px;
                display:flex;
                align-items:center;
                padding:0 14px;
                border-radius:9px;
                color:#475569;
                font-size:14px;
                font-weight:600;
            }

            .mobile-nav-link.active {
                background:#F8FAFC;
                color:var(--elive-navy);
            }

            .hero-visual,
            .hero-image,
            .hero-fallback { height:330px; min-height:330px; }

            .hero-content {
                left:22px;
                right:22px;
                bottom:24px;
            }

            .content-card,
            .side-card { padding:22px; }

            .progress-grid { grid-template-columns:1fr; }
            .gallery-item { flex-basis:calc(100vw - 28px); }
            .gallery-item img { height:230px !important; }
            .gallery-lightbox { padding:12px; }
            .gallery-lightbox-image { max-width:calc(100vw - 24px); max-height:calc(100vh - 170px); }
            .gallery-lightbox-previous,.gallery-lightbox-next { top:auto; bottom:4px; transform:none; }
            .gallery-lightbox-previous { left:calc(50% - 58px); }
            .gallery-lightbox-next { right:calc(50% - 58px); }
            .hero-title { font-size:clamp(26px, 8vw, 38px); overflow-wrap:anywhere; }
            .hero-content { left:18px; right:18px; bottom:20px; }
            .hero-visual, .hero-image, .hero-fallback { height:380px; min-height:380px; }
            .content-card, .side-card { padding:18px; border-radius:16px; }
            .gallery-section { padding:0 2px; }
            .contact-links { flex-direction:column; }
            .contact-links a { text-align:center; }
            .section-title { font-size:22px; }
            .donate-link { min-height:44px; }

            .footer-inner {
                padding:24px 0;
                flex-direction:column;
                align-items:flex-start;
            }
        }
    </style>
</head>
<body>
<header class="site-header">
    <div class="container">
        <div class="header-inner">
            <a href="{{ route('home') }}" class="brand" aria-label="eLive Events home">
                <img src="{{ asset('eLive-Logo.png') }}" alt="eLive Events">
            </a>

            <nav class="nav" aria-label="Primary navigation">
                <a href="{{ route('home') }}" class="nav-link">Home</a>
                <a href="{{ route('public.events.index') }}" class="nav-link">Events</a>
                <a href="{{ route('public.donations.index') }}" class="nav-link active">Donations</a>
                <a href="{{ route('home') }}#contact" class="nav-link">Contact</a>
                <a href="/admin" class="login-btn">Login</a>
            </nav>

            <button
                type="button"
                id="mobile-menu-button"
                class="mobile-menu-button"
                aria-label="Open navigation menu"
                aria-expanded="false"
                aria-controls="mobile-menu"
            >☰</button>
        </div>

        <div id="mobile-menu" class="mobile-nav">
            <nav class="mobile-nav-list" aria-label="Mobile navigation">
                <a href="{{ route('home') }}" class="mobile-nav-link">Home</a>
                <a href="{{ route('public.events.index') }}" class="mobile-nav-link">Events</a>
                <a href="{{ route('public.donations.index') }}" class="mobile-nav-link active">Donations</a>
                <a href="{{ route('home') }}#contact" class="mobile-nav-link">Contact</a>
                <a href="/admin" class="mobile-nav-link">Organizer Login</a>
            </nav>
        </div>
    </div>
</header>

<main>
    <section class="page-hero">
        <div class="container hero-shell">
            <a href="{{ route('public.donations.index') }}" class="back-link">← Back to campaigns</a>

            <article class="hero-card">
                <div class="hero-visual">
                    @if ($bannerUrl)
                        <img src="{{ $bannerUrl }}" alt="{{ $campaign->title }}" class="hero-image">
                    @else
                        <div class="hero-fallback"></div>
                    @endif

                    <div class="hero-overlay"></div>

                    <div class="hero-content">
                        <span class="campaign-badge">Donation Campaign</span>
                        <h1 class="hero-title">{{ $campaign->title }}</h1>

                        <div class="hero-meta">
                            @if ($campaign->organization)
                                <span>{{ $campaign->organization->name }}</span>
                            @endif
                        </div>
                        <a href="#payment-methods" class="donate-link">Donate Now ↓</a>
                    </div>
                </div>
            </article>
        </div>
    </section>

    <section class="details-section">
        <div class="container details-grid">
            <article class="content-card">
                <p class="section-eyebrow">About this campaign</p>
                <h2 class="section-title">Campaign Story</h2>

                @if ($campaign->description)
                    <div class="campaign-description">{{ $campaign->description }}</div>
                @else
                    <div class="campaign-description">Support this fundraising campaign through eLive Events.</div>
                @endif

                @if ($showProgress)
                    <section class="progress-card">
                        <p class="section-eyebrow">Campaign progress</p>
                        <h2 class="section-title">Your support makes a difference</h2>

                        <div class="progress-grid">
                            @if ($campaign->show_amount_raised)
                                <div class="stat">
                                    <span class="stat-label">Amount Raised</span>
                                    <span class="stat-value">{{ $campaign->currency }} {{ number_format((float) $metrics['amount_raised']) }}</span>
                                </div>
                            @endif

                            @if ($campaign->show_goal && $metrics['goal_amount'] !== null)
                                <div class="stat">
                                    <span class="stat-label">Goal</span>
                                    <span class="stat-value">{{ $campaign->currency }} {{ number_format((float) $metrics['goal_amount']) }}</span>
                                </div>
                            @endif

                            @if ($campaign->show_percentage && $metrics['percentage'] !== null)
                                <div class="stat">
                                    <span class="stat-label">Progress</span>
                                    <span class="stat-value">{{ rtrim(rtrim(number_format((float) $metrics['percentage'], 2, '.', ''), '0'), '.') }}%</span>
                                </div>
                            @endif

                            @if ($campaign->show_donor_count)
                                <div class="stat">
                                    <span class="stat-label">Donors</span>
                                    <span class="stat-value">{{ number_format((int) $metrics['donor_count']) }}</span>
                                </div>
                            @endif
                        </div>
                    </section>
                @endif
            </article>

                @if (! empty($campaign->gallery_image_paths))
                    <section class="gallery-section">
                        <div class="gallery-heading">
                            <div>
                                <p class="section-eyebrow">Campaign Gallery</p>
                                <h2 class="section-title">Campaign posters and updates</h2>
                            </div>
                            @if (count($campaign->gallery_image_paths) > 1)
                                <div class="gallery-controls" aria-label="Gallery navigation">
                                    <button type="button" class="gallery-control" id="gallery-prev" aria-label="Previous photos">←</button>
                                    <button type="button" class="gallery-control" id="gallery-next" aria-label="Next photos">→</button>
                                </div>
                            @endif
                        </div>

                        <div class="gallery-grid" id="campaign-gallery" tabindex="0" aria-label="Campaign images, scroll horizontally">
                            <div class="gallery-group">
                            @foreach ($campaign->gallery_image_paths as $imagePath)
                                @php
                                    $galleryImageUrl = Str::startsWith($imagePath, ['http://', 'https://'])
                                        ? $imagePath
                                        : (
                                            Str::startsWith($imagePath, ['storage/', '/storage/'])
                                                ? asset(ltrim($imagePath, '/'))
                                                : asset('storage/' . ltrim($imagePath, '/'))
                                        );
                                @endphp

                                <a
                                    href="{{ $galleryImageUrl }}"
                                    class="gallery-item"
                                    data-donation-gallery-photo
                                >
                                    <img
                                        src="{{ $galleryImageUrl }}"
                                        alt="{{ $campaign->title }} campaign image"
                                        loading="lazy"
                                    >
                                </a>
                            @endforeach
                            </div>
                        </div>
                        <p class="gallery-hint">Swipe or scroll to see more photos.</p>
                    </section>
                @endif

            <aside class="side-card" id="payment-methods">
                @if ($displayOnly)
                    <h2>How to contribute</h2>
                    <p class="side-copy">Payment is made directly to the campaign organizer using the details below.</p>

                    @forelse ($campaign->paymentMethods as $method)
                        <article class="payment-method">
                            <h3>{{ $method->provider_name }}</h3>

                            @if ($method->account_name)
                                <p><strong>Account Name:</strong> {{ $method->account_name }}</p>
                            @endif

                            @if ($method->account_number_or_phone)
                                <p><strong>Account / Phone:</strong> {{ $method->account_number_or_phone }}</p>
                            @endif

                            @if ($method->instructions)
                                <p>{{ $method->instructions }}</p>
                            @endif
                        </article>
                    @empty
                        <div class="notice">Payment instructions have not been published yet.</div>
                    @endforelse

                    <p class="notice" style="margin-top:16px;">
                        Funds are sent directly to the campaign organizer. eLive does not receive or process these direct payments.
                    </p>
                @else
                    <h2>Donation options</h2>
                    <p class="side-copy">
                        This campaign supports tracked donations. Online and tracked contribution forms will be available here as the donations workflow is completed.
                    </p>
                @endif
            </aside>
            <section class="content-card" id="contact-enquiry">
                @php
                    $campaignPhone = trim((string) ($campaign->enquiry_phone ?? ''));
                    $contactPhone = $campaignPhone !== '' ? $campaignPhone : ($campaign->organization?->contact_phone ?? null);
                    $dialPhone = preg_replace('/[^+0-9]/', '', (string) $contactPhone);
                    $whatsappPhone = preg_replace('/\D/', '', (string) $contactPhone);
                    if (str_starts_with($whatsappPhone, '0')) {
                        $whatsappPhone = '255' . substr($whatsappPhone, 1);
                    }
                    $contactLabel = trim((string) ($campaign->enquiry_label ?? '')) ?: 'Contact the campaign organizer';
                @endphp
                <p class="section-eyebrow">Enquiries</p>
                <h2 class="section-title">{{ $contactLabel }}</h2>
                <p class="side-copy">Questions about this campaign? Contact the organizer directly.</p>
                @if ($campaign->organization)
                    <p class="contact-org-name">{{ $campaign->organization->name }}</p>
                @endif
                <div class="contact-links">
                    @if ($contactPhone)
                        <a class="contact-value" href="tel:{{ $dialPhone }}">Call: {{ $contactPhone }}</a>
                        <a class="contact-value" href="https://wa.me/{{ $whatsappPhone }}" target="_blank" rel="noopener noreferrer">WhatsApp: {{ $contactPhone }}</a>
                    @endif
                    @if ($campaign->organization?->contact_email)
                        <a class="contact-value" href="mailto:{{ $campaign->organization->contact_email }}">Email: {{ $campaign->organization->contact_email }}</a>
                    @endif
                </div>
                @unless ($contactPhone || $campaign->organization?->contact_email)
                    <p class="side-copy">The organizer has not published contact details yet.</p>
                @endunless
            </section>
        </div>
    </section>
</main>
<div class="gallery-lightbox" id="donation-lightbox" role="dialog" aria-modal="true" aria-label="Campaign photo viewer" hidden>
    <div class="gallery-lightbox-toolbar"><button type="button" class="gallery-lightbox-button" id="lightbox-close" aria-label="Close photo viewer">×</button></div>
    <div class="gallery-lightbox-stage" id="lightbox-stage">
        <button type="button" class="gallery-lightbox-button gallery-lightbox-previous" id="lightbox-prev" aria-label="Previous photo">‹</button>
        <img class="gallery-lightbox-image" id="lightbox-image" src="" alt="">
        <button type="button" class="gallery-lightbox-button gallery-lightbox-next" id="lightbox-next" aria-label="Next photo">›</button>
    </div>
    <div class="gallery-lightbox-footer" id="lightbox-count"></div>
</div>
<footer class="site-footer">
    <div class="container footer-inner">
        <p>© {{ date('Y') }} eLive Events. All rights reserved.</p>
        <div class="footer-links">
            <a href="{{ route('home') }}">Home</a>
            <a href="{{ route('public.events.index') }}">Events</a>
            <a href="{{ route('public.donations.index') }}">Donations</a>
            <a href="{{ route('home') }}#contact">Contact</a>
        </div>
    </div>
</footer>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const gallery = document.getElementById('campaign-gallery');
        if (gallery && gallery.querySelectorAll('.gallery-item').length > 1) {
            const first = gallery.querySelector('.gallery-group');
            const duplicate = first.cloneNode(true);
            duplicate.setAttribute('aria-hidden', 'true');
            duplicate.querySelectorAll('a').forEach(a => { a.tabIndex = -1; });
            gallery.appendChild(duplicate);
            const previousButton = document.getElementById('gallery-prev');
            const nextButton = document.getElementById('gallery-next');
            const scrollByCard = (direction) => {
                const item = first.querySelector('.gallery-item');
                const distance = item ? item.getBoundingClientRect().width + 16 : gallery.clientWidth * .8;
                const cycle = duplicate.offsetLeft - first.offsetLeft;
                if (direction < 0 && gallery.scrollLeft <= 2 && cycle > 0) gallery.scrollLeft += cycle;
                gallery.scrollBy({ left: distance * direction, behavior: 'smooth' });
            };
            previousButton?.addEventListener('click', () => scrollByCard(-1));
            nextButton?.addEventListener('click', () => scrollByCard(1));
            const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
            let pause = false;
            let previous = 0;
            function tick(now) {
                if (!pause && !reducedMotion.matches && document.visibilityState === 'visible') {
                    const delta = previous ? Math.min(now - previous, 50) : 0;
                    gallery.scrollLeft += delta * .035;
                    const width = duplicate.offsetLeft - first.offsetLeft;
                    if (width > 0 && gallery.scrollLeft >= width) gallery.scrollLeft -= width;
                }
                previous = now;
                requestAnimationFrame(tick);
            }
            gallery.addEventListener('mouseenter', () => { pause = true; });
            gallery.addEventListener('mouseleave', () => { pause = false; });
            gallery.addEventListener('focusin', () => { pause = true; });
            gallery.addEventListener('focusout', () => { pause = false; });
            gallery.addEventListener('touchstart', () => { pause = true; }, { passive: true });
            gallery.addEventListener('touchend', () => { pause = false; }, { passive: true });
            gallery.addEventListener('keydown', (event) => {
                if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
                    event.preventDefault();
                    scrollByCard(event.key === 'ArrowRight' ? 1 : -1);
                }
            });
            requestAnimationFrame(tick);
        }
        const lightbox = document.getElementById('donation-lightbox');
        const photos = Array.from(gallery?.querySelector('.gallery-group')?.querySelectorAll('.gallery-item') || []);
        if (lightbox && photos.length) {
            const image = document.getElementById('lightbox-image');
            const counter = document.getElementById('lightbox-count');
            let active = 0, focusOrigin = null, touchX = null;
            const render = () => {
                image.src = photos[active].href;
                image.alt = photos[active].querySelector('img')?.alt || 'Campaign photo';
                counter.textContent = (active + 1) + ' / ' + photos.length;
            };
            const move = direction => { active = (active + direction + photos.length) % photos.length; render(); };
            const close = () => {
                if (lightbox.hidden) return;
                lightbox.hidden = true;
                image.removeAttribute('src');
                document.body.classList.remove('gallery-lightbox-open');
                focusOrigin?.focus();
            };
            photos.forEach((photo,index) => photo.addEventListener('click', e => {
                e.preventDefault(); focusOrigin=photo; active=index; render();
                lightbox.hidden=false; document.body.classList.add('gallery-lightbox-open');
                document.getElementById('lightbox-close').focus();
            }));
            document.getElementById('lightbox-close').addEventListener('click',close);
            document.getElementById('lightbox-prev').addEventListener('click',()=>move(-1));
            document.getElementById('lightbox-next').addEventListener('click',()=>move(1));
            lightbox.addEventListener('click',e=>{if(e.target===lightbox)close();});
            document.addEventListener('keydown',e=>{
                if(lightbox.hidden)return;
                if(e.key==='Escape')close();
                if(e.key==='ArrowLeft')move(-1);
                if(e.key==='ArrowRight')move(1);
            });
            const stage=document.getElementById('lightbox-stage');
            stage.addEventListener('touchstart',e=>{touchX=e.changedTouches[0]?.clientX ?? null;},{passive:true});
            stage.addEventListener('touchend',e=>{
                if(touchX===null)return;
                const dx=(e.changedTouches[0]?.clientX ?? touchX)-touchX;
                if(Math.abs(dx)>50)move(dx<0?1:-1);
                touchX=null;
            },{passive:true});
        }
        const button = document.getElementById('mobile-menu-button');
        const menu = document.getElementById('mobile-menu');

        if (!button || !menu) {
            return;
        }

        button.addEventListener('click', function () {
            const open = button.getAttribute('aria-expanded') === 'true';
            button.setAttribute('aria-expanded', open ? 'false' : 'true');
            menu.classList.toggle('is-open', !open);
        });
    });
</script>
</body>
</html>