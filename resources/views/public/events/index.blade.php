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

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link
        href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700"
        rel="stylesheet"
    >

    @if (
        file_exists(public_path('build/manifest.json'))
        || file_exists(public_path('hot'))
    )
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif

    <style>
        :root {
            --elive-blue: #233F7D;
            --elive-blue-dark: #17233F;
            --elive-orange: #FF9418;
            --elive-bg: #F6F8FB;
            --elive-border: #DDE3EC;
            --elive-muted: #64748B;
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
            font-family: 'Instrument Sans', sans-serif;
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
            background: rgba(255, 255, 255, .96);
            border-bottom: 1px solid #EDF1F6;
            backdrop-filter: blur(12px);
        }

        .header-inner {
            min-height: 78px;
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
            background: var(--elive-blue);
            color: white;
            border-radius: 12px;
            padding: 12px 20px;
            font-size: 14px;
            font-weight: 700;
            box-shadow: 0 5px 14px rgba(35, 63, 125, .16);
        }

        .hero {
            background: #FFFFFF;
            border-bottom: 1px solid #EDF1F6;
        }

        .hero-inner {
            padding: 68px 0 54px;
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
            color: var(--elive-blue);
            font-size: clamp(36px, 5vw, 54px);
            line-height: 1.05;
            letter-spacing: -.035em;
        }

        .hero-copy {
            max-width: 650px;
            margin: 16px 0 0;
            color: var(--elive-muted);
            font-size: 16px;
            line-height: 1.7;
        }

        .search-panel {
            margin-top: 34px;
            padding: 18px;
            background: #F8FAFC;
            border: 1px solid #E6EBF2;
            border-radius: 18px;
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
            border-radius: 12px;
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
            box-shadow: 0 0 0 3px rgba(35, 63, 125, .10);
        }

        .search-btn {
            height: 48px;
            border: 0;
            border-radius: 12px;
            background: var(--elive-blue);
            color: white;
            padding: 0 22px;
            font: inherit;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
        }

        .filters {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 14px;
        }

        .filter-btn {
            min-height: 40px;
            border: 1px solid #CBD5E1;
            border-radius: 10px;
            background: white;
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
            border-color: var(--elive-blue);
            background: var(--elive-blue);
            color: white;
        }

        .events-section {
            padding: 52px 0 70px;
        }

        .results-bar {
            width: min(760px, 100%);
            margin: 0 auto 22px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        .results-count {
            margin: 0;
            color: #475569;
            font-size: 14px;
            font-weight: 600;
        }

        .events-list {
            width: min(760px, 100%);
            margin-inline: auto;
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .event-ticket {
            overflow: hidden;
            background: #FFFFFF;
            border: 1px solid var(--elive-border);
            border-radius: 18px;
            box-shadow: 0 5px 18px rgba(15, 23, 42, .05);
            transition: transform .22s ease, box-shadow .22s ease, border-color .22s ease;
        }

        .event-ticket,
        .event-ticket-grid,
        .event-ticket-content,
        .event-ticket-actions {
            position: relative !important;
        }

        .event-ticket-content,
        .event-ticket-actions {
            z-index: 100 !important;
        }

        .event-ticket::before,
        .event-ticket::after,
        .event-ticket-grid::before,
        .event-ticket-grid::after,
        .event-ticket-overlay {
            pointer-events: none !important;
        }

        .event-ticket a,
        .view-event-btn,
        .register-btn,
        .event-ticket-title a,
        .event-ticket-visual {
            position: relative !important;
            z-index: 999 !important;
            pointer-events: auto !important;
        }

        .event-ticket:hover {
            transform: translateY(-2px);
            border-color: #CBD5E1;
            box-shadow: 0 14px 28px rgba(15, 23, 42, .09);
        }

        .event-ticket.live-event {
            border-color: #86EFAC;
            box-shadow: 0 8px 24px rgba(22, 163, 74, .10);
        }

        .event-ticket.live-event:hover {
            border-color: #4ADE80;
            box-shadow: 0 14px 30px rgba(22, 163, 74, .16);
        }

        .event-ticket.live-event .event-ticket-overlay {
            background: linear-gradient(
                to top,
                rgba(20, 83, 45, .80),
                rgba(16, 42, 82, .08) 68%
            );
        }

        .event-ticket.live-event .event-ticket-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #16A34A;
            color: #FFFFFF;
            padding: 6px 9px;
            border-radius: 999px;
            box-shadow: 0 4px 12px rgba(22, 163, 74, .28);
        }

        .event-ticket.live-event .event-ticket-status::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 999px;
            background: #FFFFFF;
        }

        .event-ticket.live-event .event-ticket-date {
            background: #F0FDF4;
            border-left-color: #86EFAC;
        }

        .event-ticket.live-event .event-ticket-date .month,
        .event-ticket.live-event .event-ticket-date .multi {
            color: #16A34A;
        }

        .live-now-label {
            margin: 4px 0 0;
            color: #16A34A;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .07em;
            text-transform: uppercase;
        }

        .event-ticket-grid {
            display: grid;
            grid-template-columns: 125px minmax(0, 1fr) 96px;
            min-height: 124px;
        }

        .event-ticket-visual {
            position: relative;
            overflow: hidden;
            background: #E2E8F0;
        }

        .event-ticket-image {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: cover;
            transition: transform .35s ease;
        }

        .event-ticket:hover .event-ticket-image {
            transform: scale(1.025);
        }

        .event-ticket-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgba(16,42,82,.70), rgba(16,42,82,.04) 64%);
        }

        .event-ticket-status {
            position: absolute;
            left: 14px;
            bottom: 13px;
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .event-ticket-status.live {
            color: #FFFFFF;
        }

        .event-ticket-status.upcoming {
            color: #FFFFFF;
        }

        .event-ticket-status.ended {
            color: #FFFFFF;
        }

        .event-ticket-fallback {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: flex-end;
            padding: 14px;
            background: linear-gradient(145deg, #17233F, #233F7D 68%, #315AA5);
        }

        .event-ticket-content {
            position: relative;
            z-index: 2;
            min-width: 0;
            padding: 18px 22px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .event-ticket-title {
            margin: 0;
            color: #0F172A;
            font-size: 18px;
            font-weight: 800;
            line-height: 1.25;
            letter-spacing: -.02em;
        }

        .event-ticket-title a:hover {
            color: var(--elive-blue);
        }

        .event-ticket-venue {
            margin: 5px 0 0;
            color: var(--elive-muted);
            font-size: 13px;
            line-height: 1.45;
        }

        .event-ticket-date-line {
            margin: 8px 0 0;
            color: #475569;
            font-size: 13px;
            font-weight: 600;
            line-height: 1.5;
        }

        .event-ticket-days {
            margin: 3px 0 0;
            color: var(--elive-orange);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .07em;
            text-transform: uppercase;
        }

        .event-ticket-actions {
            position: relative;
            z-index: 4;
            margin-top: 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .view-event-btn {
            position: relative;
            z-index: 20;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
            border: 1px solid var(--elive-blue);
            border-radius: 10px;
            background: #FFFFFF;
            font-family: inherit;
            color: var(--elive-blue);
            padding: 0 15px;
            font-size: 12px;
            font-weight: 800;
            line-height: 1;
            cursor: pointer;
            pointer-events: auto !important;
            touch-action: manipulation;
            user-select: none;
            transition:
                background .2s ease,
                color .2s ease,
                border-color .2s ease,
                transform .2s ease,
                box-shadow .2s ease;
        }

        .view-event-btn:hover {
            background: #F1F5FB;
            color: #1B3267;
            border-color: #1B3267;
            transform: translateY(-1px);
            box-shadow: 0 5px 12px rgba(35, 63, 125, .10);
        }

        .view-event-btn:focus-visible {
            outline: 3px solid rgba(35, 63, 125, .18);
            outline-offset: 2px;
        }

        .event-ticket-content,
        .event-ticket-actions,
        .view-event-btn {
            pointer-events: auto !important;
        }

        .event-ticket-overlay {
            pointer-events: none !important;
        }
        .event-ticket-actions a {
            pointer-events: auto !important;
        }


        .register-btn {
            position: relative;
            z-index: 999;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 38px;
            border-radius: 10px;
            background: var(--elive-blue);
            color: white;
            padding: 0 15px;
            font-size: 12px;
            font-weight: 800;
            box-shadow: 0 5px 12px rgba(35, 63, 125, .16);
        }

        .register-btn:hover {
            background: #1B3267;
        }

        .ended-label {
            color: #94A3B8;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        .event-ticket-date {
            border-left: 1px dashed #CBD5E1;
            background: #F8FAFC;
            padding: 10px 7px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
        }

        .event-ticket-date .month {
            color: var(--elive-orange);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .event-ticket-date .day {
            margin-top: 4px;
            color: #0F172A;
            font-size: 34px;
            font-weight: 400;
            line-height: 1;
        }

        .event-ticket-date .year {
            margin-top: 8px;
            color: #94A3B8;
            font-size: 10px;
        }

        .event-ticket-date .multi {
            color: var(--elive-orange);
            font-size: 9px;
            font-weight: 800;
            letter-spacing: .07em;
            text-transform: uppercase;
        }

        .event-ticket-date .multi-day {
            margin-top: 4px;
            color: #0F172A;
            font-size: 20px;
            font-weight: 500;
            line-height: 1.05;
        }

        .event-ticket-date .multi-month {
            margin-top: 3px;
            color: #64748B;
            font-size: 9px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .empty-state {
            padding: 70px 24px;
            background: white;
            border: 1px dashed #CBD5E1;
            border-radius: 20px;
            text-align: center;
        }

        .empty-state h2 {
            margin: 0;
            color: var(--elive-blue);
            font-size: 22px;
        }

        .empty-state p {
            margin: 10px 0 0;
            color: var(--elive-muted);
            font-size: 14px;
        }

        .pagination-wrap {
            margin-top: 36px;
        }

        .site-footer {
            background: var(--elive-blue-dark);
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
            color: white;
        }

@media (max-width: 760px) {
            .container {
                width: min(100% - 28px, 1180px);
            }

            .header-inner {
                min-height: 70px;
            }

            .brand img {
                height: 42px;
            }

            .nav .nav-link {
                display: none;
            }

            .hero-inner {
                padding: 48px 0 40px;
            }

            .search-row {
                grid-template-columns: 1fr;
            }

            .event-ticket-grid {
                grid-template-columns: 96px minmax(0, 1fr) 76px;
                min-height: 118px;
            }

            .event-ticket-content {
                padding: 14px 15px;
            }

            .event-ticket-title {
                font-size: 16px;
            }

            .event-ticket-date .day {
                font-size: 28px;
            }

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
    <div class="container header-inner">
        <a href="{{ route('home') }}" class="brand">
            <img
                src="{{ asset('eLive-Logo.png') }}"
                alt="eLive Events"
            >
        </a>

        <nav class="nav">
            <a href="{{ route('home') }}" class="nav-link">
                Home
            </a>

            <a
                href="{{ route('public.events.index') }}"
                class="nav-link active"
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
                        {{ \Illuminate\Support\Str::plural('event', $events->total()) }}
                        found
                    </p>
                </div>

                <div class="events-list">

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
                            $lastDate = $dates->last() ?? $eventStart;
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

                            $statusText = $isLive
                                ? 'Live'
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
                        @endphp

                        <article class="event-ticket {{ $isLive ? 'live-event' : '' }}">

                            <div class="event-ticket-grid">

                                <a
                                    href="{{ $eventDetailsUrl }}"
                                    class="event-ticket-visual"
                                    aria-label="{{ $event->name }}"
                                    data-event-link
                                    onclick="window.location.assign(this.href); return false;"
                                >
                                    @if ($eventImageUrl)
                                        <img
                                            src="{{ $eventImageUrl }}"
                                            alt="{{ $event->name }}"
                                            class="event-ticket-image"
                                        >
                                        <div class="event-ticket-overlay"></div>
                                    @else
                                        <div class="event-ticket-fallback"></div>
                                    @endif

                                    <span class="event-ticket-status {{ $statusClass }}">
                                        {{ $statusText }}
                                    </span>
                                </a>

                                <div class="event-ticket-content">

                                    <div>
                                        <h2 class="event-ticket-title">
                                            <a
                                                href="{{ $eventDetailsUrl }}"
                                                data-event-link
                                                onclick="window.location.assign(this.href); return false;"
                                            >
                                                {{ $event->name }}
                                            </a>
                                        </h2>

                                        @if ($event->venue)
                                            <p class="event-ticket-venue">
                                                {{ $event->venue }}
                                            </p>
                                        @endif

                                        @if ($dateLabel)
                                            <p class="event-ticket-date-line">
                                                {{ $dateLabel }}
                                            </p>
                                        @endif

                                        @if ($isMultiDay)
                                            <p class="event-ticket-days">
                                                {{ $dates->count() }} {{ \Illuminate\Support\Str::plural('Day', $dates->count()) }}
                                            </p>
                                        @endif

                                        @if ($isLive)
                                            <p class="live-now-label">
                                                Happening Now
                                            </p>
                                        @endif
                                    </div>

                                    <div class="event-ticket-actions">

                                        <a
                                            href="{{ $eventDetailsUrl }}"
                                            class="view-event-btn"
                                            aria-label="View details for {{ $event->name }}"
                                            data-event-link
                                            onclick="window.location.assign(this.href); return false;"
                                        >
                                            View Event
                                        </a>

                                        @if ($event->registration_is_open && ! $isPast)
                                            <a
                                                href="{{ $eventRegisterUrl }}"
                                                class="register-btn"
                                                data-event-link
                                                onclick="window.location.assign(this.href); return false;"
                                            >
                                                Register
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

                                @if ($displayDate)
                                    <div class="event-ticket-date">

                                        @if ($isMultiDay)
                                            <span class="multi">
                                                {{ $dates->count() }} Days
                                            </span>

                                            <span class="multi-day">
                                                {{ $displayDate->format('d') }}
                                            </span>

                                            <span class="multi-month">
                                                {{ $displayDate->format('M') }}
                                            </span>

                                            <span class="year">
                                                {{ $displayDate->format('Y') }}
                                            </span>
                                        @else
                                            <span class="month">
                                                {{ $displayDate->format('M') }}
                                            </span>

                                            <span class="day">
                                                {{ $displayDate->format('d') }}
                                            </span>

                                            <span class="year">
                                                {{ $displayDate->format('Y') }}
                                            </span>
                                        @endif

                                    </div>
                                @endif

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
        document.querySelectorAll('[data-event-link]').forEach(function (element) {
            element.addEventListener('click', function (event) {
                var href = element.getAttribute('href');

                if (! href) {
                    return;
                }

                event.preventDefault();
                window.location.href = href;
            });
        });
    });
</script>

</body>
</html>
