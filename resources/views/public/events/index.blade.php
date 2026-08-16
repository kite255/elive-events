@php
    use Illuminate\Support\Carbon;
    use Illuminate\Support\Str;

    $filter = request('filter', 'all');
    $search = trim((string) request('search'));

    $query = \App\Models\Event::query()
        ->whereNotIn('status', ['draft', 'cancelled']);

    if ($search !== '') {
        $query->where(function ($q) use ($search) {
            $q->where('name', 'ilike', '%' . $search . '%')
                ->orWhere('venue', 'ilike', '%' . $search . '%');
        });
    }

    if ($filter === 'live') {
        $query
            ->where('starts_at', '<=', now())
            ->where(function ($q) {
                $q->where('ends_at', '>=', now())
                    ->orWhere(function ($q) {
                        $q->whereNull('ends_at')
                            ->where('starts_at', '>=', now()->startOfDay());
                    });
            });
    } elseif ($filter === 'upcoming') {
        $query->where('starts_at', '>', now());
    } elseif ($filter === 'past') {
        $query->where(function ($q) {
            $q->where('ends_at', '<', now())
                ->orWhere(function ($q) {
                    $q->whereNull('ends_at')
                        ->where('starts_at', '<', now()->startOfDay());
                });
        });
    }

    $events = $query
        ->orderByRaw("
            CASE
                WHEN starts_at <= NOW()
                    AND (
                        ends_at >= NOW()
                        OR (
                            ends_at IS NULL
                            AND starts_at >= CURRENT_DATE
                        )
                    )
                THEN 0
                WHEN starts_at > NOW()
                THEN 1
                ELSE 2
            END
        ")
        ->orderByRaw("
            CASE
                WHEN starts_at <= NOW()
                    AND (
                        ends_at >= NOW()
                        OR (
                            ends_at IS NULL
                            AND starts_at >= CURRENT_DATE
                        )
                    )
                THEN starts_at
                ELSE NULL
            END ASC
        ")
        ->orderByRaw("
            CASE
                WHEN starts_at > NOW()
                THEN starts_at
                ELSE NULL
            END ASC
        ")
        ->orderByRaw("
            CASE
                WHEN starts_at < NOW()
                THEN starts_at
                ELSE NULL
            END DESC
        ")
        ->paginate(12)
        ->withQueryString();

    $eventIds = $events->getCollection()->pluck('id');

    $eventDaysByEvent = \App\Models\EventDay::query()
        ->whereIn('event_id', $eventIds)
        ->whereIn('status', ['active', 'completed'])
        ->orderBy('event_date')
        ->orderBy('display_order')
        ->orderBy('id')
        ->get()
        ->groupBy('event_id');
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Events | eLive Events</title>

    <meta
        name="description"
        content="Browse current, upcoming and past events managed with eLive Events."
    >

    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('css/creato-font.css') }}">
    @if (
        file_exists(public_path('build/manifest.json'))
        || file_exists(public_path('hot'))
    )
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif

    <style>
        :root {
            --elive-navy: #161943;
            --elive-blue: #007AB2;
            --elive-orange: #FF9800;

            --elive-bg: #F7F8FC;
            --elive-surface: #FFFFFF;
            --elive-border: #E6E8EF;
            --elive-muted: #667085;

            --elive-success: #16A34A;
            --elive-warning: #FF9800;
            --elive-danger: #DC2626;

            --elive-navy-hover: #20265C;
            --elive-blue-hover: #006B9D;
        }

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;
            background: var(--elive-bg);
            color: #0F172A;
            font-family: 'Creato Display', ui-sans-serif, system-ui, sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

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
            height: 48px;
            width: auto;
            display: block;
        }

        .nav {
            display: flex;
            align-items: center;
            gap: 30px;
        }

        .nav-link {
            font-size: 14px;
            font-weight: 600;
            color: #64748B;
            transition: color .2s ease;
        }

        .nav-link:hover,
        .nav-link.active {
            color: var(--elive-blue);
        }

        .login-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            padding: 0 20px;
            border-radius: 11px;
            background: var(--elive-navy);
            color: #FFFFFF;
            font-size: 14px;
            font-weight: 700;
            box-shadow: 0 5px 14px rgba(22, 25, 67, .16);
        }

        .login-btn:hover {
            background: var(--elive-blue);
        }

        .hero {
            background: #FFFFFF;
            border-bottom: 1px solid #E8EDF4;
        }

        .hero-inner {
            padding: 50px 0 36px;
        }

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
            max-width: 650px;
            margin: 14px 0 0;
            color: var(--elive-muted);
            font-size: 16px;
            line-height: 1.7;
        }

        .search-panel {
            margin-top: 28px;
            padding: 16px;
            background: #F8FAFC;
            border: 1px solid #E5EAF1;
            border-radius: 16px;
        }

        .search-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 12px;
            align-items: center;
        }

        .search-input {
            width: 100%;
            height: 48px;
            border: 1px solid #CBD5E1;
            border-radius: 11px;
            background: #FFFFFF;
            padding: 0 16px;
            color: #0F172A;
            font: inherit;
            font-size: 14px;
            outline: none;
            transition: border-color .2s ease, box-shadow .2s ease;
        }

        .search-input:focus {
            border-color: var(--elive-blue);
            box-shadow: 0 0 0 3px rgba(0, 122, 178, .14);
        }

        .search-btn {
            height: 48px;
            border: 0;
            border-radius: 11px;
            background: var(--elive-navy);
            color: #FFFFFF;
            padding: 0 24px;
            font: inherit;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
        }

        .search-btn:hover {
            background: var(--elive-blue);
        }

        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 9px;
            margin-top: 13px;
        }

        .filter-btn {
            min-height: 38px;
            border: 1px solid #CBD5E1;
            border-radius: 5px;
            background: #FFFFFF;
            color: #475569;
            padding: 0 14px;
            font: inherit;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: all .2s ease;
        }

        .filter-btn:hover {
            border-color: var(--elive-blue);
            color: var(--elive-blue);
        }

        .filter-btn.active {
            border-color: var(--elive-navy);
            background: var(--elive-navy);
            color: #FFFFFF;
        }

        .events-section {
            padding: 34px 0 72px;
        }

        .results-bar {
            margin: 0 0 18px;
        }

        .results-count {
            margin: 0;
            color: #475569;
            font-size: 14px;
            font-weight: 700;
        }


        .events-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 24px;
            align-items: start;
        }

        .event-card {
            overflow: hidden;
            display: flex;
            flex-direction: column;
            width: 100%;
            max-width: 340px;
            min-width: 0;
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 18px;
            box-shadow: 0 10px 28px rgba(15, 23, 42, .10);
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .event-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 34px rgba(15, 23, 42, .14);
        }

        .event-card.live-event {
            border-color: #86EFAC;
        }

        .event-card-media {
            position: relative;
            height: 185px;
            overflow: hidden;
            background: #DDE4EE;
            display: block;
        }

        .event-card-image,
        .event-card-fallback {
            width: 100%;
            height: 100%;
            display: block;
        }

        .event-card-image {
            object-fit: cover;
            transition: transform .3s ease;
        }

        .event-card:hover .event-card-image {
            transform: scale(1.03);
        }

        .event-card-fallback {
            background: linear-gradient(135deg, var(--elive-navy), var(--elive-blue));
        }

        .event-card-shade {
            display: none;
        }

        .status-badge {
            position: absolute;
            left: 14px;
            top: 14px;
            z-index: 2;
            min-height: 24px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 10px;
            border-radius: 6px;
            color: #FFFFFF;
            font-size: 10px;
            font-weight: 800;
            line-height: 1;
            text-transform: none;
            box-shadow: 0 4px 10px rgba(15, 23, 42, .18);
        }

        .status-badge.live {
            background: #16A34A;
        }

        .status-badge.upcoming {
            background: var(--elive-orange);
        }

        .status-badge.ended {
            background: var(--elive-danger);
        }

        .status-badge.live::before {
            display: none;
        }

        .event-card-body {
            display: flex;
            flex-direction: column;
            flex: 1;
            padding: 16px 16px 18px;
        }

        .event-card-title {
            margin: 0;
            color: #1F2937;
            font-size: 17px;
            font-weight: 800;
            line-height: 1.3;
            letter-spacing: -.01em;
        }

        .event-card-title a {
            color: inherit;
            text-decoration: none;
            transition: color .2s ease;
        }

        .event-card-title a:hover {
            color: var(--elive-blue);
        }

        .event-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 14px;
            align-items: center;
            margin-top: 10px;
        }

        .meta-item {
            min-width: 0;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #6B7280;
            font-size: 12px;
            font-weight: 600;
            line-height: 1.4;
        }

        .meta-item svg {
            width: 15px;
            height: 15px;
            flex: 0 0 auto;
            margin-top: 0;
            color: var(--elive-blue);
        }

        .meta-item span {
            min-width: 0;
            overflow-wrap: anywhere;
        }

        .event-description {
            margin: 12px 0 0;
            color: #6B7280;
            font-size: 13px;
            line-height: 1.55;
        }

        .event-days,
        .live-label,
        .view-btn {
            display: none;
        }

        .event-card-actions {
            margin-top: auto;
            padding-top: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .register-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 36px;
            padding: 0 14px;
            border-radius: 8px;
            border: 1px solid var(--elive-navy);
            background: var(--elive-navy);
            color: #FFFFFF;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            box-shadow: 0 4px 10px rgba(22, 25, 67, .22);
            transition: background .2s ease, transform .2s ease;
        }

        .register-btn:hover {
            background: var(--elive-blue);
            border-color: var(--elive-blue);
            transform: translateY(-1px);
        }

        .ended-label {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 36px;
            padding: 0 14px;
            border-radius: 8px;
            background: #E5E7EB;
            color: #6B7280;
            font-size: 12px;
            font-weight: 700;
            text-transform: none;
            letter-spacing: 0;
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

        .pagination-wrap {
            margin-top: 34px;
        }

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

        .footer-links a:hover {
            color: #FFFFFF;
        }

        .mobile-menu-button {
            display: none;
            width: 44px;
            height: 44px;
            align-items: center;
            justify-content: center;
            border: 1px solid #E2E8F0;
            border-radius: 999px;
            background: #FFFFFF;
            color: var(--elive-navy);
            box-shadow: 0 4px 12px rgba(15, 23, 42, .08);
            cursor: pointer;
            transition:
                background .2s ease,
                border-color .2s ease,
                color .2s ease,
                transform .2s ease;
        }

        .mobile-menu-button:hover {
            background: #F8FAFC;
            border-color: #CBD5E1;
        }

        .mobile-menu-button:focus-visible {
            outline: none;
            border-color: var(--elive-blue);
            box-shadow: 0 0 0 3px rgba(0, 122, 178, .14);
        }

        .mobile-menu-button svg {
            width: 24px;
            height: 24px;
        }

        .mobile-menu-icon-close {
            display: none;
        }

        .mobile-menu-button[aria-expanded="true"] .mobile-menu-icon-open {
            display: none;
        }

        .mobile-menu-button[aria-expanded="true"] .mobile-menu-icon-close {
            display: block;
        }

        .mobile-nav {
            display: none;
            border-top: 1px solid #EEF2F7;
            padding: 10px 0 16px;
        }

        .mobile-nav.is-open {
            display: block;
        }

        .mobile-nav-list {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .mobile-nav-link {
            display: flex;
            align-items: center;
            min-height: 44px;
            padding: 0 14px;
            border-radius: 9px;
            color: #475569;
            font-size: 14px;
            font-weight: 600;
            transition:
                background .2s ease,
                color .2s ease;
        }

        .mobile-nav-link:hover,
        .mobile-nav-link.active {
            background: #F8FAFC;
            color: var(--elive-navy);
        }

        .mobile-nav-login {
            display: inline-flex;
            width: 100%;
            min-height: 44px;
            align-items: center;
            justify-content: center;
            margin-top: 8px;
            border-radius: 9px;
            background: var(--elive-navy);
            color: #FFFFFF;
            font-size: 14px;
            font-weight: 700;
            box-shadow: 0 5px 14px rgba(22, 25, 67, .16);
            transition: background .2s ease;
        }

        .mobile-nav-login:hover {
            background: var(--elive-blue);
        }

        @media (max-width: 980px) {
            .events-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 760px) {
            .container {
                width: min(100% - 28px, 1180px);
            }

            .header-inner {
                min-height: 68px;
            }

            .brand img {
                height: 40px;
            }

            .nav {
                display: none;
            }

            .mobile-menu-button {
                display: inline-flex;
            }

            .hero-inner {
                padding: 38px 0 30px;
            }

            .search-row {
                grid-template-columns: 1fr;
            }

            .search-btn {
                width: 100%;
            }

            .filters {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .filter-btn {
                width: 100%;
            }

            .events-grid {
                grid-template-columns: 1fr;
                gap: 18px;
            }

            .event-card {
                max-width: none;
                border-radius: 18px;
            }

            .footer-inner {
                padding: 24px 0;
                flex-direction: column;
                align-items: flex-start;
            }
        }

        @media (max-width: 460px) {
            .container {
                width: calc(100% - 22px);
            }

            .event-card-body {
                padding: 16px;
            }

            .event-card-title {
                font-size: 17px;
            }

            .event-card-media {
                height: 180px;
            }

            .event-card-actions {
                align-items: flex-start;
            }

            .register-btn,
            .ended-label {
                width: auto;
            }
        }
    </style>
</head>

<body>

<header class="site-header">
    <div class="container">

        <div class="header-inner">
            <a
                href="{{ route('home') }}"
                class="brand"
                aria-label="eLive Events home"
            >
                <img
                    src="{{ asset('eLive-Logo.png') }}"
                    alt="eLive Events"
                >
            </a>

            <nav class="nav" aria-label="Primary navigation">
                <a href="{{ route('home') }}" class="nav-link">
                    Home
                </a>

                <a
                    href="{{ route('public.events.index') }}"
                    class="nav-link active"
                    aria-current="page"
                >
                    Events
                </a>

                <a href="{{ route('home') }}#contact" class="nav-link">
                    Contact
                </a>

                <a href="/admin" class="login-btn">
                    Login
                </a>
            </nav>

            <button
                type="button"
                id="mobile-menu-button"
                class="mobile-menu-button"
                aria-label="Open navigation menu"
                aria-expanded="false"
                aria-controls="mobile-menu"
            >
                <svg
                    class="mobile-menu-icon-open"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    aria-hidden="true"
                >
                    <path d="M4 7h16M4 12h16M4 17h16"/>
                </svg>

                <svg
                    class="mobile-menu-icon-close"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    aria-hidden="true"
                >
                    <path d="M6 6l12 12M18 6L6 18"/>
                </svg>
            </button>
        </div>

        <div id="mobile-menu" class="mobile-nav">
            <nav class="mobile-nav-list" aria-label="Mobile navigation">
                <a
                    href="{{ route('home') }}"
                    class="mobile-nav-link"
                >
                    Home
                </a>

                <a
                    href="{{ route('public.events.index') }}"
                    class="mobile-nav-link active"
                    aria-current="page"
                >
                    Events
                </a>

                <a
                    href="{{ route('home') }}#contact"
                    class="mobile-nav-link"
                >
                    Contact
                </a>

                <a href="/admin" class="mobile-nav-login">
                    Organizer Login
                </a>
            </nav>
        </div>

    </div>
</header>

<main>

    <section class="hero">
        <div class="container hero-inner">

            <p class="eyebrow">
                Events
            </p>

            <h1>
                Discover Events
            </h1>

            <p class="hero-copy">
                Browse events happening now, upcoming events and recently completed events.
            </p>

            <form
                method="GET"
                action="{{ route('public.events.index') }}"
                class="search-panel"
            >
                <div class="search-row">
                    <input
                        type="search"
                        name="search"
                        value="{{ $search }}"
                        placeholder="Search by event name or venue"
                        class="search-input"
                    >

                    <button
                        type="submit"
                        class="search-btn"
                    >
                        Search
                    </button>
                </div>

                <div class="filters">
                    @foreach ([
                        'all' => 'All',
                        'live' => 'Happening Now',
                        'upcoming' => 'Upcoming',
                        'past' => 'Past',
                    ] as $key => $label)

                        <button
                            type="submit"
                            name="filter"
                            value="{{ $key }}"
                            class="filter-btn {{ $filter === $key ? 'active' : '' }}"
                        >
                            {{ $label }}
                        </button>

                    @endforeach
                </div>
            </form>

        </div>
    </section>


    <section class="events-section">
        <div class="container">

            @if ($events->count())

                <div class="results-bar">
                    <p class="results-count">
                        {{ $events->total() }}
                        {{ Str::plural('event', $events->total()) }}
                        found
                    </p>
                </div>

                <div class="events-grid">

                    @foreach ($events as $event)

                        @php
                            $eventStart = $event->starts_at
                                ? Carbon::parse($event->starts_at)
                                : null;

                            $eventEnd = $event->ends_at
                                ? Carbon::parse($event->ends_at)
                                : null;

                            $days = $eventDaysByEvent
                                ->get($event->id, collect())
                                ->values();

                            $dates = $days
                                ->map(
                                    fn ($day) => $day->event_date
                                        ? Carbon::parse($day->event_date)
                                        : null
                                )
                                ->filter()
                                ->values();

                            $isLive = $eventStart
                                && $eventStart->lte(now())
                                && (
                                    ($eventEnd && $eventEnd->gte(now()))
                                    || (
                                        ! $eventEnd
                                        && $eventStart->gte(now()->startOfDay())
                                    )
                                );

                            $isPast = $eventEnd
                                ? $eventEnd->lt(now())
                                : (
                                    $eventStart
                                    && $eventStart->lt(now()->startOfDay())
                                );

                            $eventImage = $event->registration_banner_image_path;
                            $eventImageUrl = null;

                            if ($eventImage) {
                                if (Str::startsWith($eventImage, ['http://', 'https://'])) {
                                    $eventImageUrl = $eventImage;
                                } elseif (Str::startsWith($eventImage, ['storage/', '/storage/'])) {
                                    $eventImageUrl = asset(ltrim($eventImage, '/'));
                                } else {
                                    $eventImageUrl = asset('storage/' . ltrim($eventImage, '/'));
                                }
                            }

                            $displayDate = $dates->first() ?? $eventStart;
                            $isMultiDay = $dates->count() > 1;

                            $dateLabel = null;

                            if ($dates->isNotEmpty()) {
                                $years = $dates
                                    ->map(fn ($date) => $date->format('Y'))
                                    ->unique();

                                $sameYear = $years->count() === 1;

                                $dateLabel = $dates
                                    ->map(
                                        fn ($date) => $sameYear
                                            ? $date->format('d M')
                                            : $date->format('d M Y')
                                    )
                                    ->implode(' • ');

                                if ($sameYear) {
                                    $dateLabel .= ' ' . $dates->first()->format('Y');
                                }
                            } elseif ($eventStart) {
                                $dateLabel = $eventStart->format('D, d M Y');
                            }

                            /*
                             * Compact date/time label for the card:
                             * - Single-day event: 15 Aug 2026 · 9:00 AM - 5:00 PM
                             * - Multi-day, same month/year: 15 - 17 Aug 2026 · 9:00 AM - 5:00 PM
                             * - Multi-day, different months: 30 Aug - 02 Sep 2026 · 9:00 AM - 5:00 PM
                             * - Multi-day, different years: 31 Dec 2026 - 02 Jan 2027 · 9:00 AM - 5:00 PM
                             */
                            $cardDateLabel = null;

                            if ($isMultiDay && $dates->isNotEmpty()) {
                                $firstDate = $dates->first();
                                $lastDate = $dates->last();

                                if (
                                    $firstDate->year === $lastDate->year
                                    && $firstDate->month === $lastDate->month
                                ) {
                                    $cardDateLabel =
                                        $firstDate->format('d')
                                        . ' - '
                                        . $lastDate->format('d M Y');
                                } elseif ($firstDate->year === $lastDate->year) {
                                    $cardDateLabel =
                                        $firstDate->format('d M')
                                        . ' - '
                                        . $lastDate->format('d M Y');
                                } else {
                                    $cardDateLabel =
                                        $firstDate->format('d M Y')
                                        . ' - '
                                        . $lastDate->format('d M Y');
                                }
                            } elseif ($displayDate) {
                                $cardDateLabel = $displayDate->format('d M Y');
                            }

                            $cardTimeLabel = null;

                            if ($eventStart) {
                                $cardTimeLabel = $eventStart->format('g:i A');

                                if ($eventEnd) {
                                    $cardTimeLabel .= ' - ' . $eventEnd->format('g:i A');
                                }
                            }

                            $statusText = $isLive
                                ? 'Live Now'
                                : ($isPast ? 'Ended' : 'Upcoming');

                            $statusClass = $isLive
                                ? 'live'
                                : ($isPast ? 'ended' : 'upcoming');

                            $eventDetailsUrl = route('public.events.show', [
                                'event' => $event->slug,
                            ]);

                            $eventRegisterUrl = route('public.events.register', [
                                'event' => $event->slug,
                            ]);

                            $description = trim(strip_tags((string) $event->description));
                        @endphp

                        <article class="event-card {{ $isLive ? 'live-event' : '' }}">

                            <a
                                href="{{ $eventDetailsUrl }}"
                                class="event-card-media"
                                aria-label="View {{ $event->name }}"
                            >
                                @if ($eventImageUrl)
                                    <img
                                        src="{{ $eventImageUrl }}"
                                        alt="{{ $event->name }}"
                                        class="event-card-image"
                                    >
                                @else
                                    <div class="event-card-fallback"></div>
                                @endif

                                <div class="event-card-shade"></div>

                                <span class="status-badge {{ $statusClass }}">
                                    {{ $statusText }}
                                </span>

                            </a>

                            <div class="event-card-body">

                                <h2 class="event-card-title">
                                    <a href="{{ $eventDetailsUrl }}">
                                        {{ $event->name }}
                                    </a>
                                </h2>

                                <div class="event-meta">

                                    @if ($cardDateLabel)
                                        <div class="meta-item">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                <rect x="3" y="5" width="18" height="16" rx="2"/>
                                                <path d="M16 3v4M8 3v4M3 10h18"/>
                                            </svg>

                                            <span>
                                                {{ $cardDateLabel }}
                                                @if ($cardTimeLabel)
                                                    · {{ $cardTimeLabel }}
                                                @endif
                                            </span>
                                        </div>
                                    @endif

                                    @if ($event->venue)
                                        <div class="meta-item">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                <path d="M12 21s6-5.2 6-11a6 6 0 1 0-12 0c0 5.8 6 11 6 11Z"/>
                                                <circle cx="12" cy="10" r="2"/>
                                            </svg>

                                            <span>{{ $event->venue }}</span>
                                        </div>
                                    @endif

                                </div>

                                @if ($description !== '')
                                    <p class="event-description">
                                        {{ Str::limit($description, 105) }}
                                    </p>
                                @endif

                                @if ($isMultiDay)
                                    <p class="event-days">
                                        {{ $dates->count() }}
                                        {{ Str::plural('Day', $dates->count()) }}
                                        · {{ $dateLabel }}
                                    </p>
                                @elseif ($dateLabel)
                                    <p class="event-days">
                                        {{ $dateLabel }}
                                    </p>
                                @endif

                                @if ($isLive)
                                    <p class="live-label">
                                        Happening Now
                                    </p>
                                @endif

                                <div class="event-card-actions">

                                    <a
                                        href="{{ $eventDetailsUrl }}"
                                        class="view-btn"
                                    >
                                        View Event
                                    </a>

                                    @if ($event->registration_is_open && ! $isPast)
                                        <a
                                            href="{{ $eventRegisterUrl }}"
                                            class="register-btn"
                                        >
                                            Register Now
                                        </a>
                                    @elseif ($isPast)
                                        <span class="ended-label">
                                            Event Ended
                                        </span>
                                    @else
                                        <span class="ended-label">
                                            Registration Closed
                                        </span>
                                    @endif

                                </div>

                            </div>

                        </article>

                    @endforeach

                </div>

                <div class="pagination-wrap">
                    {{ $events->links() }}
                </div>

            @else

                <div class="empty-state">
                    <h2>No events found</h2>

                    <p>
                        Try another search term or select a different event filter.
                    </p>
                </div>

            @endif

        </div>
    </section>

</main>


<footer class="site-footer">
    <div class="container footer-inner">

        <p>
            © {{ date('Y') }} eLive Events. All rights reserved.
        </p>

        <div class="footer-links">
            <a href="{{ route('home') }}">
                Home
            </a>

            <a href="{{ route('home') }}#contact">
                Contact
            </a>

            <a href="/admin">
                Login
            </a>
        </div>

    </div>
</footer>


<script>
    document.addEventListener('DOMContentLoaded', function () {
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const mobileMenu = document.getElementById('mobile-menu');

        if (!mobileMenuButton || !mobileMenu) {
            return;
        }

        const closeMobileMenu = function () {
            mobileMenu.classList.remove('is-open');
            mobileMenuButton.setAttribute('aria-expanded', 'false');
            mobileMenuButton.setAttribute('aria-label', 'Open navigation menu');
        };

        mobileMenuButton.addEventListener('click', function () {
            const isOpen = mobileMenuButton.getAttribute('aria-expanded') === 'true';

            if (isOpen) {
                closeMobileMenu();
                return;
            }

            mobileMenu.classList.add('is-open');
            mobileMenuButton.setAttribute('aria-expanded', 'true');
            mobileMenuButton.setAttribute('aria-label', 'Close navigation menu');
        });

        mobileMenu.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', closeMobileMenu);
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeMobileMenu();
            }
        });

        window.addEventListener('resize', function () {
            if (window.innerWidth > 760) {
                closeMobileMenu();
            }
        });
    });
</script>

</body>
</html>
