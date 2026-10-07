@php
    use Illuminate\Support\Str;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Donations | eLive Events</title>
    <meta
        name="description"
        content="Discover active fundraising campaigns and support causes managed through eLive Events."
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
            --elive-surface: #FFFFFF;
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
            background: rgba(255, 255, 255, .97);
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

        .brand img {
            display: block;
            width: auto;
            height: 48px;
        }

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
            box-shadow: 0 5px 14px rgba(22, 25, 67, .16);
        }

        .login-btn:hover { background: var(--elive-blue); }

        .mobile-menu-button,
        .mobile-nav { display: none; }

        .hero {
            background: #FFFFFF;
            border-bottom: 1px solid #E8EDF4;
        }

        .hero-inner { padding: 50px 0 38px; }

        .eyebrow {
            margin: 0;
            color: var(--elive-orange);
            font-size: 12px;
            font-weight: 800;
            letter-spacing: .14em;
            text-transform: uppercase;
        }

        .hero h1 {
            margin: 12px 0 0;
            color: var(--elive-navy);
            font-size: clamp(38px, 5vw, 56px);
            line-height: 1.04;
            letter-spacing: -.035em;
        }

        .hero-copy {
            max-width: 680px;
            margin: 14px 0 0;
            color: var(--elive-muted);
            font-size: 16px;
            line-height: 1.7;
        }

        .campaign-section { padding: 34px 0 72px; }

        .results-count {
            margin: 0 0 18px;
            color: #475569;
            font-size: 14px;
            font-weight: 700;
        }

        .campaign-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 24px;
            align-items: stretch;
        }

        .campaign-card {
            overflow: hidden;
            display: flex;
            min-width: 0;
            flex-direction: column;
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 18px;
            box-shadow: 0 10px 28px rgba(15, 23, 42, .10);
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .campaign-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 34px rgba(15, 23, 42, .14);
        }

        .campaign-card-media {
            position: relative;
            display: block;
            height: 185px;
            overflow: hidden;
            background: #DDE4EE;
        }

        .campaign-card-image,
        .campaign-card-fallback {
            width: 100%;
            height: 100%;
            display: block;
        }

        .campaign-card-image {
            object-fit: cover;
            transition: transform .3s ease;
        }

        .campaign-card:hover .campaign-card-image { transform: scale(1.03); }

        .campaign-card-fallback {
            background: var(--elive-navy);
        }

        .campaign-badge {
            position: absolute;
            top: 14px;
            left: 14px;
            min-height: 24px;
            display: inline-flex;
            align-items: center;
            padding: 0 10px;
            border-radius: 6px;
            background: var(--elive-orange);
            color: #FFFFFF;
            font-size: 10px;
            font-weight: 800;
            box-shadow: 0 4px 10px rgba(15, 23, 42, .18);
        }

        .campaign-card-body {
            display: flex;
            flex: 1;
            flex-direction: column;
            padding: 16px 16px 18px;
        }

        .campaign-title {
            margin: 0;
            color: #1F2937;
            font-size: 17px;
            font-weight: 800;
            line-height: 1.3;
        }

        .campaign-title a:hover { color: var(--elive-blue); }

        .campaign-meta {
            display: grid;
            gap: 7px;
            margin-top: 10px;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 7px;
            color: #6B7280;
            font-size: 12px;
            font-weight: 600;
        }

        .meta-item svg {
            width: 15px;
            height: 15px;
            flex: 0 0 auto;
            color: var(--elive-blue);
        }

        .campaign-description {
            margin: 12px 0 0;
            color: #6B7280;
            font-size: 13px;
            line-height: 1.55;
        }

        .campaign-actions {
            margin-top: auto;
            padding-top: 16px;
        }

        .support-btn {
            min-height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 14px;
            border: 1px solid var(--elive-navy);
            border-radius: 8px;
            background: var(--elive-navy);
            color: #FFFFFF;
            font-size: 13px;
            font-weight: 700;
            box-shadow: 0 4px 10px rgba(22, 25, 67, .22);
        }

        .support-btn:hover {
            border-color: var(--elive-blue);
            background: var(--elive-blue);
        }

        .empty-state {
            padding: 70px 24px;
            background: #FFFFFF;
            border: 1px dashed #CBD5E1;
            border-radius: 20px;
            text-align: center;
        }

        .empty-state h2 {
            margin: 0;
            color: var(--elive-navy);
            font-size: 22px;
        }

        .empty-state p {
            margin: 10px 0 0;
            color: var(--elive-muted);
            font-size: 14px;
        }

        .pagination-wrap { margin-top: 34px; }

        .site-footer {
            background: var(--elive-navy);
            color: #CBD5E1;
        }

        .footer-inner {
            min-height: 92px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
        }

        .footer-inner p {
            margin: 0;
            color: #94A3B8;
            font-size: 14px;
        }

        .footer-links {
            display: flex;
            gap: 22px;
            font-size: 14px;
            font-weight: 600;
        }

        .footer-links a:hover { color: #FFFFFF; }

        @media (max-width: 980px) {
            .campaign-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }

        @media (max-width: 760px) {
            .container { width: min(100% - 28px, 1180px); }
            .header-inner { min-height: 68px; }
            .brand img { height: 40px; }
            .nav { display: none; }

            .mobile-menu-button {
                display: inline-flex;
                width: 44px;
                height: 44px;
                align-items: center;
                justify-content: center;
                border: 1px solid #E2E8F0;
                border-radius: 999px;
                background: #FFFFFF;
                color: var(--elive-navy);
                cursor: pointer;
            }

            .mobile-nav {
                border-top: 1px solid #EEF2F7;
                padding: 10px 0 16px;
            }

            .mobile-nav.is-open { display: block; }

            .mobile-nav-list {
                display: flex;
                flex-direction: column;
                gap: 4px;
            }

            .mobile-nav-link {
                min-height: 44px;
                display: flex;
                align-items: center;
                padding: 0 14px;
                border-radius: 9px;
                color: #475569;
                font-size: 14px;
                font-weight: 600;
            }

            .mobile-nav-link.active {
                background: #F8FAFC;
                color: var(--elive-navy);
            }

            .campaign-grid { grid-template-columns: 1fr; }
            .hero-inner { padding: 38px 0 30px; }

            .footer-inner {
                padding: 24px 0;
                flex-direction: column;
                align-items: flex-start;
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
                <a href="{{ route('public.donations.index') }}" class="nav-link active" aria-current="page">Donations</a>
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
            >
                <span aria-hidden="true">☰</span>
            </button>
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
    <section class="hero">
        <div class="container hero-inner">
            <p class="eyebrow">Donations</p>
            <h1>Support a Campaign</h1>
            <p class="hero-copy">
                Discover active fundraising campaigns and support causes managed through eLive Events.
            </p>
        </div>
    </section>

    <section class="campaign-section">
        <div class="container">
            @if ($campaigns->count())
                <p class="results-count">
                    {{ $campaigns->total() }} {{ Str::plural('campaign', $campaigns->total()) }} available
                </p>

                <div class="campaign-grid">
                    @foreach ($campaigns as $campaign)
                        @php
                            $campaignUrl = route('public.donations.show', ['campaign' => $campaign->slug]);
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

                            $description = trim(strip_tags((string) $campaign->description));
                        @endphp

                        <article class="campaign-card">
                            <a href="{{ $campaignUrl }}" class="campaign-card-media" aria-label="View {{ $campaign->title }}">
                                @if ($bannerUrl)
                                    <img src="{{ $bannerUrl }}" alt="{{ $campaign->title }}" class="campaign-card-image">
                                @else
                                    <div class="campaign-card-fallback"></div>
                                @endif

                                <span class="campaign-badge">Donation Campaign</span>
                            </a>

                            <div class="campaign-card-body">
                                <h2 class="campaign-title">
                                    <a href="{{ $campaignUrl }}">{{ $campaign->title }}</a>
                                </h2>

                                <div class="campaign-meta">
                                    @if ($campaign->organization)
                                        <div class="meta-item">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                <path d="M3 21h18M5 21V7l7-4 7 4v14M9 10h6M9 14h6M9 18h6"/>
                                            </svg>
                                            <span>{{ $campaign->organization->name }}</span>
                                        </div>
                                    @endif

                                    @if ($campaign->event)
                                        <div class="meta-item">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                <rect x="3" y="5" width="18" height="16" rx="2"/>
                                                <path d="M16 3v4M8 3v4M3 10h18"/>
                                            </svg>
                                            <span>{{ $campaign->event->name }}</span>
                                        </div>
                                    @endif
                                </div>

                                @if ($description !== '')
                                    <p class="campaign-description">{{ Str::limit($description, 110) }}</p>
                                @endif

                                <div class="campaign-actions">
                                    <a href="{{ $campaignUrl }}" class="support-btn">Support Campaign</a>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="pagination-wrap">
                    {{ $campaigns->links() }}
                </div>
            @else
                <div class="empty-state">
                    <h2>No active campaigns</h2>
                    <p>There are no public donation campaigns available right now.</p>
                </div>
            @endif
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
            <a href="/admin">Login</a>
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
