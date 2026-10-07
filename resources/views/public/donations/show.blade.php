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
            grid-template-columns: minmax(0, 1fr) 360px;
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
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 16px;
            margin-top: 16px;
        }

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

            .progress-grid,
            .gallery-grid { grid-template-columns:1fr; }

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

                            @if ($campaign->event)
                                <span>Linked to {{ $campaign->event->name }}</span>
                            @endif
                        </div>
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

                @if (! empty($campaign->gallery_image_paths))
                    <section class="gallery-section">
                        <p class="section-eyebrow">Campaign Gallery</p>
                        <h2 class="section-title">Campaign posters and updates</h2>

                        <div class="gallery-grid">
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
                                    target="_blank"
                                    rel="noopener"
                                >
                                    <img
                                        src="{{ $galleryImageUrl }}"
                                        alt="{{ $campaign->title }} campaign image"
                                        loading="lazy"
                                    >
                                </a>
                            @endforeach
                        </div>
                    </section>
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

            <aside class="side-card">
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
        </div>
    </section>
</main>

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