@php
    use Illuminate\Support\Carbon;
    use Illuminate\Support\Str;

    $eventStart = $event->starts_at
        ? Carbon::parse($event->starts_at)
        : null;

    $eventEnd = $event->ends_at
        ? Carbon::parse($event->ends_at)
        : null;

    $days = \App\Models\EventDay::query()
        ->where('event_id', $event->id)
        ->whereIn('status', ['active', 'completed'])
        ->orderBy('event_date')
        ->orderBy('display_order')
        ->orderBy('id')
        ->get();

    $dates = $days
        ->map(
            fn ($day) => $day->event_date
                ? Carbon::parse($day->event_date)
                : null
        )
        ->filter()
        ->values();

    /*
     * Assigned Event Days are the source of truth whenever they exist.
     * This keeps the hero, status and Event Information card consistent
     * for both single-day and multi-day events.
     */
    $firstAssignedDate = $dates->first();
    $lastAssignedDate = $dates->last();

    $scheduleStart = $firstAssignedDate
        ? $firstAssignedDate->copy()->startOfDay()
        : $eventStart;

    $scheduleEnd = $lastAssignedDate
        ? $lastAssignedDate->copy()->endOfDay()
        : $eventEnd;

    $isLive = $scheduleStart
        && $scheduleStart->lte(now())
        && (
            ($scheduleEnd && $scheduleEnd->gte(now()))
            || (
                ! $scheduleEnd
                && $scheduleStart->gte(now()->startOfDay())
            )
        );

    $isPast = $scheduleEnd
        ? $scheduleEnd->lt(now())
        : (
            $scheduleStart
            && $scheduleStart->lt(now()->startOfDay())
        );

    $statusText = $isLive
        ? 'Happening Now'
        : ($isPast ? 'Event Ended' : 'Upcoming');

    $statusClass = $isLive
        ? 'live'
        : ($isPast ? 'ended' : 'upcoming');

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

    $eventDetailsDateLabel = null;

    /*
     * Compact event date display:
     *
     * Single day:
     * 23 Aug 2026
     *
     * Multi-day in the same month:
     * 23 – 29 Aug 2026
     *
     * Multi-day across months:
     * 30 Aug – 02 Sep 2026
     *
     * Multi-day across years:
     * 31 Dec 2026 – 02 Jan 2027
     *
     * The Event Days section below still shows every configured day,
     * so the hero stays compact without losing the detailed schedule.
     */
    if ($dates->isNotEmpty()) {
        $firstDate = $dates->first();
        $lastDate = $dates->last();

        if ($dates->count() === 1 || $firstDate->isSameDay($lastDate)) {
            $eventDetailsDateLabel = $firstDate->format('d M Y');
        } elseif (
            $firstDate->year === $lastDate->year
            && $firstDate->month === $lastDate->month
        ) {
            $eventDetailsDateLabel =
                $firstDate->format('d')
                . ' – '
                . $lastDate->format('d M Y');
        } elseif ($firstDate->year === $lastDate->year) {
            $eventDetailsDateLabel =
                $firstDate->format('d M')
                . ' – '
                . $lastDate->format('d M Y');
        } else {
            $eventDetailsDateLabel =
                $firstDate->format('d M Y')
                . ' – '
                . $lastDate->format('d M Y');
        }
    } elseif ($eventStart) {
        if (
            $eventEnd
            && ! $eventStart->isSameDay($eventEnd)
        ) {
            if (
                $eventStart->year === $eventEnd->year
                && $eventStart->month === $eventEnd->month
            ) {
                $eventDetailsDateLabel =
                    $eventStart->format('d')
                    . ' – '
                    . $eventEnd->format('d M Y');
            } elseif ($eventStart->year === $eventEnd->year) {
                $eventDetailsDateLabel =
                    $eventStart->format('d M')
                    . ' – '
                    . $eventEnd->format('d M Y');
            } else {
                $eventDetailsDateLabel =
                    $eventStart->format('d M Y')
                    . ' – '
                    . $eventEnd->format('d M Y');
            }
        } else {
            $eventDetailsDateLabel = $eventStart->format('d M Y');
        }
    }

    /*
     * Event Information helpers.
     * We intentionally avoid the raw event starts_at / ends_at rows when
     * assigned Event Days exist, because those values can be generic/default
     * timestamps and may not match the actual configured event schedule.
     */
    $eventDayCount = $dates->isNotEmpty()
        ? $dates->count()
        : (
            $eventStart && $eventEnd
                ? max(1, $eventStart->copy()->startOfDay()->diffInDays($eventEnd->copy()->startOfDay()) + 1)
                : ($eventStart ? 1 : null)
        );

    $eventDateHeading = ($eventDayCount ?? 0) > 1
        ? 'Event Dates'
        : 'Event Date';

    $eventDurationLabel = $eventDayCount
        ? $eventDayCount . ' ' . Str::plural('day', $eventDayCount)
        : null;

    $registerUrl = route('public.events.register', [
        'event' => $event->slug,
    ]);
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>{{ $event->name }} | eLive Events</title>

    <meta
        name="description"
        content="{{ Str::limit(strip_tags((string) $event->description), 155) ?: 'Event details and registration information.' }}"
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
            background: #FFFFFF;
            border-bottom: 1px solid #E8EDF4;
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
            transition: background .2s ease, transform .2s ease;
        }

        .login-btn:hover {
            background: var(--elive-blue);
            transform: translateY(-1px);
        }

        .page-hero {
            background: #FFFFFF;
            border-bottom: 1px solid #E8EDF4;
        }

        .hero-shell {
            padding: 34px 0 40px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 22px;
            color: var(--elive-blue);
            font-size: 13px;
            font-weight: 700;
        }

        .back-link:hover {
            color: var(--elive-orange);
        }

        .hero-card {
            overflow: hidden;
            border: 1px solid #DCE4EE;
            border-radius: 24px;
            background: #FFFFFF;
            box-shadow: 0 16px 36px rgba(15, 23, 42, .07);
        }

        .hero-visual {
            position: relative;
            height: 370px;
            background: #DDE4EE;
            overflow: hidden;
        }

        .hero-image {
            width: 100%;
            height: 370px;
            display: block;
            object-fit: cover;
        }

        .hero-fallback {
            height: 370px;
            background: linear-gradient(135deg, var(--elive-navy), var(--elive-blue));
        }

        .hero-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(
                to top,
                rgba(15, 23, 42, .78),
                rgba(15, 23, 42, .18) 58%,
                rgba(15, 23, 42, .04)
            );
            pointer-events: none;
        }

        .hero-content {
            position: absolute;
            left: 32px;
            right: 32px;
            bottom: 34px;
            z-index: 2;
            color: #FFFFFF;
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            min-height: 26px;
            padding: 0 10px;
            border-radius: 999px;
            font-size: 9px;
            font-weight: 800;
            letter-spacing: .06em;
            text-transform: uppercase;
            margin-bottom: 11px;
        }

        .status-pill.live {
            background: var(--elive-success);
            color: #FFFFFF;
        }

        .status-pill.upcoming {
            background: var(--elive-orange);
            color: #FFFFFF;
        }

        .status-pill.ended {
            background: rgba(71, 85, 105, .94);
            color: #FFFFFF;
        }

        .status-pill.live::before {
            content: '';
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #FFFFFF;
        }

        .hero-title {
            margin: 0;
            max-width: 860px;
            font-size: clamp(32px, 4.5vw, 52px);
            line-height: 1.03;
            letter-spacing: -.035em;
            font-weight: 800;
        }

        .hero-meta {
            margin-top: 13px;
            display: flex;
            flex-wrap: wrap;
            gap: 10px 20px;
            color: rgba(255, 255, 255, .90);
            font-size: 13px;
            font-weight: 600;
        }

        .hero-meta-item {
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .hero-meta svg {
            width: 16px;
            height: 16px;
            flex: 0 0 auto;
        }

        .details-section {
            padding: 34px 0 72px;
        }

        .details-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 340px;
            gap: 26px;
            align-items: start;
        }

        .content-card,
        .info-card {
            background: #FFFFFF;
            border: 1px solid var(--elive-border);
            border-radius: 20px;
            box-shadow: 0 7px 22px rgba(15, 23, 42, .04);
        }

        .content-card {
            padding: 30px;
        }

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
            letter-spacing: -.025em;
        }

        .event-description {
            margin-top: 18px;
            color: #475569;
            font-size: 15px;
            line-height: 1.8;
        }

        .event-description p:first-child {
            margin-top: 0;
        }

        .event-description p:last-child {
            margin-bottom: 0;
        }

        .days-section {
            margin-top: 30px;
            padding-top: 28px;
            border-top: 1px solid #E8EDF4;
        }

        .days-list {
            margin-top: 16px;
            display: grid;
            gap: 10px;
        }

        .day-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding: 15px 16px;
            border: 1px solid #E2E8F0;
            border-radius: 14px;
            background: #F8FAFC;
        }

        .day-main {
            min-width: 0;
        }

        .day-label {
            margin: 0;
            color: #0F172A;
            font-size: 14px;
            font-weight: 800;
        }

        .day-date {
            margin: 4px 0 0;
            color: #64748B;
            font-size: 13px;
        }

        .day-number {
            min-width: 66px;
            color: var(--elive-blue);
            font-size: 12px;
            font-weight: 800;
            text-align: right;
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .info-card {
            position: sticky;
            top: 98px;
            padding: 24px;
        }

        .info-title {
            margin: 0 0 18px;
            color: var(--elive-navy);
            font-size: 20px;
            line-height: 1.2;
        }

        .info-list {
            display: grid;
            gap: 16px;
        }

        .info-item {
            display: grid;
            grid-template-columns: 38px minmax(0, 1fr);
            gap: 12px;
            align-items: start;
        }

        .info-icon {
            width: 38px;
            height: 38px;
            border-radius: 11px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: rgba(0, 122, 178, .08);
            color: var(--elive-blue);
        }

        .info-icon svg {
            width: 18px;
            height: 18px;
        }

        .info-copy strong {
            display: block;
            color: #334155;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .info-copy span {
            display: block;
            margin-top: 4px;
            color: #64748B;
            font-size: 13px;
            line-height: 1.5;
        }

        .info-divider {
            margin: 20px 0;
            border: 0;
            border-top: 1px solid #E8EDF4;
        }

        .register-btn {
            width: 100%;
            min-height: 48px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            background: var(--elive-navy);
            color: #FFFFFF;
            font-size: 14px;
            font-weight: 800;
            box-shadow: 0 7px 16px rgba(22, 25, 67, .17);
            transition: background .2s ease, transform .2s ease;
        }

        .register-btn:hover {
            background: var(--elive-blue);
            transform: translateY(-1px);
        }

        .registration-state {
            width: 100%;
            min-height: 48px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            background: #F1F5F9;
            color: #64748B;
            font-size: 13px;
            font-weight: 800;
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

        @media (max-width: 900px) {
            .details-grid {
                grid-template-columns: 1fr;
            }

            .info-card {
                position: static;
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

            .nav .nav-link {
                display: none;
            }

            .login-btn {
                min-height: 40px;
                padding: 0 16px;
            }

            .hero-shell {
                padding: 24px 0 30px;
            }

            .hero-visual,
            .hero-image,
            .hero-fallback {
                height: 300px;
            }

            .hero-content {
                left: 20px;
                right: 20px;
                bottom: 24px;
            }

            .hero-title {
                font-size: clamp(28px, 8vw, 38px);
            }

            .hero-meta {
                font-size: 12px;
            }

            .details-section {
                padding: 24px 0 54px;
            }

            .content-card,
            .info-card {
                border-radius: 16px;
            }

            .content-card {
                padding: 22px;
            }

            .info-card {
                padding: 20px;
            }

            .day-row {
                align-items: flex-start;
            }

            .footer-inner {
                padding: 24px 0;
                flex-direction: column;
                align-items: flex-start;
            }
        }

        @media (max-width: 480px) {
            .container {
                width: calc(100% - 22px);
            }

            .hero-meta {
                display: grid;
                gap: 8px;
            }

            .day-row {
                flex-direction: column;
            }

            .day-number {
                min-width: 0;
                text-align: left;
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

    <section class="page-hero">
        <div class="container hero-shell">

            <a
                href="{{ route('public.events.index') }}"
                class="back-link"
            >
                ← Back to Events
            </a>

            <article class="hero-card">

                <div class="hero-visual">

                    @if ($eventImageUrl)
                        <img
                            src="{{ $eventImageUrl }}"
                            alt="{{ $event->name }}"
                            class="hero-image"
                        >
                    @else
                        <div class="hero-fallback"></div>
                    @endif

                    <div class="hero-overlay"></div>

                    <div class="hero-content">

                        <span class="status-pill {{ $statusClass }}">
                            {{ $statusText }}
                        </span>

                        <h1 class="hero-title">
                            {{ $event->name }}
                        </h1>

                        <div class="hero-meta">

                            @if ($event->venue)
                                <span class="hero-meta-item">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <path d="M12 21s6-5.2 6-11a6 6 0 1 0-12 0c0 5.8 6 11 6 11Z"/>
                                        <circle cx="12" cy="10" r="2"/>
                                    </svg>

                                    {{ $event->venue }}
                                </span>
                            @endif

                            @if ($eventDetailsDateLabel)
                                <span class="hero-meta-item">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                        <rect x="3" y="5" width="18" height="16" rx="2"/>
                                        <path d="M16 3v4M8 3v4M3 10h18"/>
                                    </svg>

                                    {{ $eventDetailsDateLabel }}
                                </span>
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

                <p class="section-eyebrow">
                    Event Details
                </p>

                <h2 class="section-title">
                    About this event
                </h2>

                <div class="event-description">
                    @if ($event->description)
                        {!! nl2br(e($event->description)) !!}
                    @else
                        <p>
                            Event information will be available here.
                        </p>
                    @endif
                </div>

                @if ($days->count())
                    <div class="days-section">

                        <p class="section-eyebrow">
                            Event Schedule
                        </p>

                        <h2 class="section-title">
                            Event Days
                        </h2>

                        <div class="days-list">
                            @foreach ($days as $index => $day)
                                @php
                                    $dayDate = $day->event_date
                                        ? Carbon::parse($day->event_date)
                                        : null;
                                @endphp

                                <div class="day-row">

                                    <div class="day-main">
                                        <p class="day-label">
                                            {{ $day->name ?: 'Event Day ' . ($index + 1) }}
                                        </p>

                                        @if ($dayDate)
                                            <p class="day-date">
                                                {{ $dayDate->format('l, d F Y') }}

                                                @if ($day->starts_at)
                                                    · {{ Carbon::parse($day->starts_at)->format('g:i A') }}
                                                    @if ($day->ends_at)
                                                        – {{ Carbon::parse($day->ends_at)->format('g:i A') }}
                                                    @endif
                                                @endif
                                            </p>
                                        @endif
                                    </div>

                                    <div class="day-number">
                                        Day {{ $index + 1 }}
                                    </div>

                                </div>
                            @endforeach
                        </div>

                    </div>
                @endif

            </article>


            <aside class="info-card">

                <h2 class="info-title">
                    Event Information
                </h2>

                <div class="info-list">

                    @if ($eventDetailsDateLabel)
                        <div class="info-item">
                            <div class="info-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <rect x="3" y="5" width="18" height="16" rx="2"/>
                                    <path d="M16 3v4M8 3v4M3 10h18"/>
                                </svg>
                            </div>

                            <div class="info-copy">
                                <strong>{{ $eventDateHeading }}</strong>
                                <span>{{ $eventDetailsDateLabel }}</span>
                            </div>
                        </div>
                    @endif

                    @if ($eventDurationLabel)
                        <div class="info-item">
                            <div class="info-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="M8 3v3M16 3v3M4 9h16"/>
                                    <rect x="3" y="5" width="18" height="16" rx="2"/>
                                    <path d="M8 13h3M13 13h3M8 17h3M13 17h3"/>
                                </svg>
                            </div>

                            <div class="info-copy">
                                <strong>Duration</strong>
                                <span>{{ $eventDurationLabel }}</span>
                            </div>
                        </div>
                    @endif

                    @if ($event->venue)
                        <div class="info-item">
                            <div class="info-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                    <path d="M12 21s6-5.2 6-11a6 6 0 1 0-12 0c0 5.8 6 11 6 11Z"/>
                                    <circle cx="12" cy="10" r="2"/>
                                </svg>
                            </div>

                            <div class="info-copy">
                                <strong>Venue</strong>
                                <span>{{ $event->venue }}</span>
                            </div>
                        </div>
                    @endif

                    <div class="info-item">
                        <div class="info-icon">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                <circle cx="12" cy="12" r="9"/>
                                <path d="M8.5 12.5 11 15l4.5-5"/>
                            </svg>
                        </div>

                        <div class="info-copy">
                            <strong>Status</strong>
                            <span>{{ $statusText }}</span>
                        </div>
                    </div>

                </div>

                <hr class="info-divider">

                @if ($event->registration_is_open && ! $isPast)
                    <a
                        href="{{ $registerUrl }}"
                        class="register-btn"
                    >
                        Register for Event
                    </a>
                @elseif ($isPast)
                    <div class="registration-state">
                        Event Ended
                    </div>
                @else
                    <div class="registration-state">
                        Registration Closed
                    </div>
                @endif

            </aside>

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

            <a href="{{ route('public.events.index') }}">
                Events
            </a>

            <a href="{{ route('home') }}#contact">
                Contact
            </a>
        </div>

    </div>
</footer>

</body>
</html>
