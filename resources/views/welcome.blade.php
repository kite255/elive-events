@php
    $heroImagePath = public_path('event.jpg');
    $heroImageVersion = file_exists($heroImagePath)
        ? filemtime($heroImagePath)
        : time();

    $happeningNowEvents = \App\Models\Event::query()
        ->whereNotIn('status', ['draft', 'cancelled'])
        ->where('starts_at', '<=', now())
        ->where(function ($query) {
            $query
                ->where('ends_at', '>=', now())
                ->orWhere(function ($query) {
                    $query
                        ->whereNull('ends_at')
                        ->where('starts_at', '>=', now()->startOfDay());
                });
        })
        ->orderBy('starts_at')
        ->limit(2)
        ->get();

    $upcomingEvents = \App\Models\Event::query()
        ->whereNotIn('status', ['draft', 'cancelled'])
        ->where('starts_at', '>', now())
        ->orderBy('starts_at')
        ->limit(4)
        ->get();

    $pastEvents = \App\Models\Event::query()
        ->whereNotIn('status', ['draft', 'cancelled'])
        ->where(function ($query) {
            $query
                ->where('ends_at', '<', now())
                ->orWhere(function ($query) {
                    $query
                        ->whereNull('ends_at')
                        ->where('starts_at', '<', now()->startOfDay());
                });
        })
        ->orderByDesc('starts_at')
        ->limit(2)
        ->get();

    /*
     * Actual schedule days configured by the organizer in Event > Days.
     * These are the source of truth for multi-day labels on the homepage.
     */
    $homepageEventIds = collect([
        $happeningNowEvents,
        $upcomingEvents,
        $pastEvents,
    ])
        ->flatten()
        ->pluck('id')
        ->filter()
        ->unique()
        ->values();

    $eventDaysByEvent = \App\Models\EventDay::query()
        ->whereIn('event_id', $homepageEventIds)
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

    <title>eLive Events | Smart Event Management</title>

    <meta
        name="description"
        content="Manage event registration, digital badges, attendee communication and QR check-in with eLive Events."
    >

    <link rel="icon" href="{{ asset('favicon.ico') }}">

    {{-- Preload hero image and automatically refresh it when event.jpg changes --}}
    <link
        rel="preload"
        as="image"
        href="{{ asset('event.jpg') }}?v={{ $heroImageVersion }}"
    >

    {{-- Instrument Sans --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link
        href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700"
        rel="stylesheet"
    >

    {{-- Use compiled Vite assets when available --}}
    @if (
        file_exists(public_path('build/manifest.json'))
        || file_exists(public_path('hot'))
    )
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
    @endif

    <style>
        html {
            scroll-behavior: smooth;
        }

        html,
        body {
            font-family: 'Instrument Sans', sans-serif;
        }

        #events,
        #contact {
            scroll-margin-top: 90px;
        }

        .event-ticket-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .event-ticket-fallback {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: flex-end;
            padding: 16px;
        }

        .home-events-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 24px;
            align-items: start;
        }

        .home-event-card {
            overflow: hidden;
            display: flex;
            flex-direction: column;
            min-width: 0;
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 18px;
            box-shadow: 0 10px 28px rgba(15, 23, 42, .08);
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .home-event-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 16px 34px rgba(15, 23, 42, .12);
        }

        .home-event-card.live-card {
            border-color: #86EFAC;
        }

        .home-event-media {
            position: relative;
            height: 180px;
            overflow: hidden;
            background: #DDE4EE;
            display: block;
        }

        .home-event-image,
        .home-event-fallback {
            width: 100%;
            height: 100%;
            display: block;
        }

        .home-event-image {
            object-fit: cover;
            transition: transform .3s ease;
        }

        .home-event-card:hover .home-event-image {
            transform: scale(1.03);
        }

        .home-event-fallback {
            background: linear-gradient(135deg, #233F7D, #1B3267);
        }

        .home-status-badge {
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
            box-shadow: 0 4px 10px rgba(15, 23, 42, .18);
        }

        .home-status-badge.live {
            background: #16A34A;
        }

        .home-status-badge.upcoming {
            background: #FF9418;
        }

        .home-status-badge.ended {
            background: #DC2626;
        }

        .home-event-body {
            display: flex;
            flex-direction: column;
            flex: 1;
            padding: 16px 16px 18px;
        }

        .home-event-title {
            margin: 0;
            color: #1F2937;
            font-size: 17px;
            font-weight: 800;
            line-height: 1.3;
        }

        .home-event-title a:hover {
            color: #233F7D;
        }

        .home-event-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px 14px;
            align-items: center;
            margin-top: 10px;
        }

        .home-meta-item {
            min-width: 0;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #6B7280;
            font-size: 12px;
            font-weight: 600;
            line-height: 1.4;
        }

        .home-meta-item svg {
            width: 15px;
            height: 15px;
            flex: 0 0 auto;
            color: #233F7D;
        }

        .home-event-description {
            margin: 12px 0 0;
            color: #6B7280;
            font-size: 13px;
            line-height: 1.55;
        }

        .home-event-actions {
            margin-top: auto;
            padding-top: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .home-register-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 36px;
            padding: 0 14px;
            border-radius: 8px;
            border: 1px solid #233F7D;
            background: #233F7D;
            color: #FFFFFF;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            box-shadow: 0 4px 10px rgba(35, 63, 125, .18);
            transition: background .2s ease, transform .2s ease;
        }

        .home-register-btn:hover {
            background: #1B3267;
            transform: translateY(-1px);
        }

        .home-ended-label {
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
        }

        @media (max-width: 980px) {
            .home-events-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (max-width: 700px) {
            .home-events-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body class="bg-white text-slate-900 antialiased">

    {{-- HEADER --}}
    <header class="border-b border-slate-100 bg-white">
        <div class="mx-auto flex max-w-7xl items-center justify-between px-6 py-4 lg:px-8">

            <a href="{{ route('home') }}" class="flex items-center">
                <img
                    src="{{ asset('eLive-Logo.png') }}"
                    alt="eLive Events"
                    class="h-11 w-auto sm:h-12"
                >
            </a>

            <nav class="hidden items-center gap-8 md:flex">

                <a
                    href="{{ route('home') }}"
                    class="text-sm font-semibold text-[#233F7D]"
                >
                    Home
                </a>

                <a
                    href="{{ route('public.events.index') }}"
                    class="text-sm font-medium text-slate-600 transition hover:text-[#233F7D]"
                >
                    Events
                </a>

                <a
                    href="#contact"
                    class="text-sm font-medium text-slate-600 transition hover:text-[#233F7D]"
                >
                    Contact
                </a>

                <a
                    href="/admin"
                    class="rounded-xl bg-[#233F7D] px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-[#1b3267]"
                >
                    Login
                </a>

            </nav>

            <a
                href="/admin"
                class="rounded-lg bg-[#233F7D] px-4 py-2 text-sm font-semibold text-white md:hidden"
            >
                Login
            </a>

        </div>
    </header>


    <main>

        {{-- FULL HERO IMAGE --}}
        <section
            class="relative min-h-[600px] overflow-hidden bg-cover bg-center bg-no-repeat"
            style="background-image: url('{{ asset('event.jpg') }}?v={{ $heroImageVersion }}');"
        >

            {{-- Premium dark base overlay --}}
            <div class="absolute inset-0 bg-[#17233F]/45"></div>

            {{-- Strong left-side readability while preserving the event image on the right --}}
            <div
                class="absolute inset-0 bg-gradient-to-r from-[#17233F]/95 via-[#17233F]/75 to-[#17233F]/25"
            ></div>

            {{-- Hero content --}}
            <div
                class="relative mx-auto flex min-h-[600px] max-w-7xl items-center px-6 py-16 lg:px-8"
            >

                <div class="max-w-2xl">

                    <div
                        class="mb-5 inline-flex rounded-full border border-white/20 bg-white/10 px-3.5 py-1.5 text-xs font-semibold tracking-wide text-white/90 backdrop-blur-sm sm:text-sm"
                    >
                        Smarter Event Management
                    </div>

                    <h1
                        class="text-4xl font-bold leading-[1.05] tracking-tight text-white sm:text-5xl lg:text-[56px]"
                    >
                        Manage Your Events
                        <span class="mt-2 block text-[#FF9418]">
                            Simply &amp; Professionally.
                        </span>
                    </h1>

                    <p
                        class="mt-6 max-w-xl text-base leading-7 text-white/85 sm:text-lg"
                    >
                        Registration, badges, communication and QR check-in —
                        all in one platform.
                    </p>

                    <div class="mt-8 flex flex-wrap gap-4">

                        <a
                            href="#contact"
                            class="rounded-xl bg-[#FF9418] px-6 py-3.5 text-sm font-semibold text-white shadow-lg transition hover:bg-[#ed8611] sm:text-base"
                        >
                            Contact eLive
                        </a>

                        <a
                            href="{{ route('public.events.index') }}"
                            class="rounded-xl border border-white/35 bg-white/10 px-6 py-3.5 text-sm font-semibold text-white backdrop-blur-sm transition hover:bg-white hover:text-[#233F7D] sm:text-base"
                        >
                            Explore Events
                        </a>

                    </div>

                </div>

            </div>

            {{-- Bottom accent --}}
            <div
                class="absolute bottom-0 left-0 h-1 w-full bg-gradient-to-r from-[#FF9418] via-[#FF9418] to-[#233F7D]"
            ></div>

        </section>


        {{-- EVENTS --}}
        <section id="events" class="bg-slate-50 py-16 sm:py-20">

            <div class="mx-auto max-w-7xl px-6 lg:px-8">

                {{-- Section header --}}
                <div class="mx-auto max-w-3xl text-center">

                    <p class="text-sm font-bold uppercase tracking-wider text-[#FF9418]">
                        Events
                    </p>

                    <h2 class="mt-3 text-3xl font-bold text-[#233F7D] sm:text-4xl">
                        Discover Events
                    </h2>

                    <p class="mx-auto mt-4 max-w-2xl leading-7 text-slate-600">
                        See events happening now, upcoming events and recently completed events.
                    </p>

                    <a
                        href="{{ route('public.events.index') }}"
                        class="mt-6 inline-flex items-center gap-2 text-sm font-semibold text-[#233F7D] transition hover:text-[#FF9418]"
                    >
                        View All Events
                        <span aria-hidden="true">→</span>
                    </a>

                </div>

                {{-- Compact event cards --}}
                <div class="mx-auto mt-12" style="width:100%; max-width:1180px;">

                    {{-- HAPPENING NOW --}}
                    @if ($happeningNowEvents->isNotEmpty())
                        <div>

                            <div class="mb-4 flex items-end justify-between gap-4">

                                <div>
                                    <div class="flex items-center gap-3">
                                        <span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>

                                        <h3 class="text-xl font-bold text-[#233F7D]">
                                            Happening Now
                                        </h3>
                                    </div>

                                    <p class="mt-1 pl-5 text-sm text-slate-500">
                                        Events currently in progress.
                                    </p>
                                </div>

                            </div>

                            <div class="home-events-grid">

                                @foreach ($happeningNowEvents as $event)

                                    @php
                                        $eventStart = $event->starts_at
                                            ? \Illuminate\Support\Carbon::parse($event->starts_at)
                                            : null;

                                        $eventEnd = $event->ends_at
                                            ? \Illuminate\Support\Carbon::parse($event->ends_at)
                                            : null;

                                        $eventName = trim((string) $event->name);

                                        $assignedDays = $eventDaysByEvent
                                            ->get($event->id, collect())
                                            ->values();

                                        $assignedDates = $assignedDays
                                            ->map(
                                                fn ($day) => $day->event_date
                                                    ? \Illuminate\Support\Carbon::parse($day->event_date)
                                                    : null
                                            )
                                            ->filter()
                                            ->values();

                                        $usesAssignedDays = $assignedDates->isNotEmpty();
                                        $isAssignedMultiDay = $assignedDates->count() > 1;
                                        $displayDate = $assignedDates->first() ?? $eventStart;

                                        $cardDateLabel = null;

                                        if ($isAssignedMultiDay) {
                                            $firstDate = $assignedDates->first();
                                            $lastDate = $assignedDates->last();

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

                                        if ($usesAssignedDays && $assignedDays->first()?->starts_at) {
                                            $cardTimeLabel =
                                                \Illuminate\Support\Carbon::parse(
                                                    $assignedDays->first()->starts_at
                                                )->format('g:i A');

                                            if ($assignedDays->first()?->ends_at) {
                                                $cardTimeLabel .=
                                                    ' - '
                                                    . \Illuminate\Support\Carbon::parse(
                                                        $assignedDays->first()->ends_at
                                                    )->format('g:i A');
                                            }
                                        } elseif ($eventStart) {
                                            $cardTimeLabel = $eventStart->format('g:i A');

                                            if ($eventEnd) {
                                                $cardTimeLabel .= ' - ' . $eventEnd->format('g:i A');
                                            }
                                        }

                                        $eventDetailsUrl = route('public.events.show', $event->slug);
                                        $eventRegisterUrl = route('public.events.register', $event->slug);

                                        $eventImage = $event->registration_banner_image_path;
                                        $eventImageUrl = null;

                                        if ($eventImage) {
                                            if (\Illuminate\Support\Str::startsWith($eventImage, ['http://', 'https://'])) {
                                                $eventImageUrl = $eventImage;
                                            } elseif (\Illuminate\Support\Str::startsWith($eventImage, ['storage/', '/storage/'])) {
                                                $eventImageUrl = asset(ltrim($eventImage, '/'));
                                            } else {
                                                $eventImageUrl = asset('storage/' . ltrim($eventImage, '/'));
                                            }
                                        }

                                        $description = trim(strip_tags((string) $event->description));
                                    @endphp

                                    <article class="home-event-card live-card">

                                        <a
                                            href="{{ $eventDetailsUrl }}"
                                            class="home-event-media"
                                            aria-label="{{ $eventName !== '' ? $eventName : 'View event' }}"
                                        >
                                            @if ($eventImageUrl)
                                                <img
                                                    src="{{ $eventImageUrl }}"
                                                    alt="{{ $eventName !== '' ? $eventName : 'Event image' }}"
                                                    class="home-event-image"
                                                >
                                            @else
                                                <div class="home-event-fallback"></div>
                                            @endif

                                            <span class="home-status-badge live">
                                                Live Now
                                            </span>
                                        </a>

                                        <div class="home-event-body">

                                            <h4 class="home-event-title">
                                                <a href="{{ $eventDetailsUrl }}">
                                                    {{ $eventName !== '' ? $eventName : 'Event' }}
                                                </a>
                                            </h4>

                                            <div class="home-event-meta">

                                                @if ($cardDateLabel)
                                                    <div class="home-meta-item">
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
                                                    <div class="home-meta-item">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                            <path d="M12 21s6-5.2 6-11a6 6 0 1 0-12 0c0 5.8 6 11 6 11Z"/>
                                                            <circle cx="12" cy="10" r="2"/>
                                                        </svg>

                                                        <span>{{ $event->venue }}</span>
                                                    </div>
                                                @endif

                                            </div>

                                            @if ($description !== '')
                                                <p class="home-event-description">
                                                    {{ \Illuminate\Support\Str::limit($description, 105) }}
                                                </p>
                                            @endif

                                            <div class="home-event-actions">

                                                    @if ($event->registration_is_open)
                                                        <a
                                                            href="{{ $eventRegisterUrl }}"
                                                            class="home-register-btn"
                                                        >
                                                            Register Now
                                                        </a>
                                                    @else
                                                        <span class="home-ended-label">
                                                            Registration Closed
                                                        </span>
                                                    @endif

                                            </div>

                                        </div>

                                    </article>

                                @endforeach

                            </div>

                        </div>
                    @endif


                    {{-- UPCOMING EVENTS --}}
                    <div class="{{ $happeningNowEvents->isNotEmpty() ? 'mt-10' : '' }}">

                        <div class="mb-4">

                            <div class="flex items-center gap-3">
                                <span class="h-2.5 w-2.5 rounded-full bg-[#FF9418]"></span>

                                <h3 class="text-xl font-bold text-[#233F7D]">
                                    Upcoming Events
                                </h3>
                            </div>

                            <p class="mt-1 pl-5 text-sm text-slate-500">
                                Events scheduled to start soon.
                            </p>

                        </div>

                        @if ($upcomingEvents->isNotEmpty())

                            <div class="home-events-grid">

                                @foreach ($upcomingEvents as $event)

                                    @php
                                        $eventStart = $event->starts_at
                                            ? \Illuminate\Support\Carbon::parse($event->starts_at)
                                            : null;

                                        $eventEnd = $event->ends_at
                                            ? \Illuminate\Support\Carbon::parse($event->ends_at)
                                            : null;

                                        $eventName = trim((string) $event->name);

                                        $assignedDays = $eventDaysByEvent
                                            ->get($event->id, collect())
                                            ->values();

                                        $assignedDates = $assignedDays
                                            ->map(
                                                fn ($day) => $day->event_date
                                                    ? \Illuminate\Support\Carbon::parse($day->event_date)
                                                    : null
                                            )
                                            ->filter()
                                            ->values();

                                        $usesAssignedDays = $assignedDates->isNotEmpty();
                                        $isAssignedMultiDay = $assignedDates->count() > 1;
                                        $displayDate = $assignedDates->first() ?? $eventStart;

                                        $cardDateLabel = null;

                                        if ($isAssignedMultiDay) {
                                            $firstDate = $assignedDates->first();
                                            $lastDate = $assignedDates->last();

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

                                        if ($usesAssignedDays && $assignedDays->first()?->starts_at) {
                                            $cardTimeLabel =
                                                \Illuminate\Support\Carbon::parse(
                                                    $assignedDays->first()->starts_at
                                                )->format('g:i A');

                                            if ($assignedDays->first()?->ends_at) {
                                                $cardTimeLabel .=
                                                    ' - '
                                                    . \Illuminate\Support\Carbon::parse(
                                                        $assignedDays->first()->ends_at
                                                    )->format('g:i A');
                                            }
                                        } elseif ($eventStart) {
                                            $cardTimeLabel = $eventStart->format('g:i A');

                                            if ($eventEnd) {
                                                $cardTimeLabel .= ' - ' . $eventEnd->format('g:i A');
                                            }
                                        }

                                        $eventDetailsUrl = route('public.events.show', $event->slug);
                                        $eventRegisterUrl = route('public.events.register', $event->slug);

                                        $eventImage = $event->registration_banner_image_path;
                                        $eventImageUrl = null;

                                        if ($eventImage) {
                                            if (\Illuminate\Support\Str::startsWith($eventImage, ['http://', 'https://'])) {
                                                $eventImageUrl = $eventImage;
                                            } elseif (\Illuminate\Support\Str::startsWith($eventImage, ['storage/', '/storage/'])) {
                                                $eventImageUrl = asset(ltrim($eventImage, '/'));
                                            } else {
                                                $eventImageUrl = asset('storage/' . ltrim($eventImage, '/'));
                                            }
                                        }

                                        $description = trim(strip_tags((string) $event->description));
                                    @endphp

                                    <article class="home-event-card ">

                                        <a
                                            href="{{ $eventDetailsUrl }}"
                                            class="home-event-media"
                                            aria-label="{{ $eventName !== '' ? $eventName : 'View event' }}"
                                        >
                                            @if ($eventImageUrl)
                                                <img
                                                    src="{{ $eventImageUrl }}"
                                                    alt="{{ $eventName !== '' ? $eventName : 'Event image' }}"
                                                    class="home-event-image"
                                                >
                                            @else
                                                <div class="home-event-fallback"></div>
                                            @endif

                                            <span class="home-status-badge upcoming">
                                                Upcoming
                                            </span>
                                        </a>

                                        <div class="home-event-body">

                                            <h4 class="home-event-title">
                                                <a href="{{ $eventDetailsUrl }}">
                                                    {{ $eventName !== '' ? $eventName : 'Event' }}
                                                </a>
                                            </h4>

                                            <div class="home-event-meta">

                                                @if ($cardDateLabel)
                                                    <div class="home-meta-item">
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
                                                    <div class="home-meta-item">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                            <path d="M12 21s6-5.2 6-11a6 6 0 1 0-12 0c0 5.8 6 11 6 11Z"/>
                                                            <circle cx="12" cy="10" r="2"/>
                                                        </svg>

                                                        <span>{{ $event->venue }}</span>
                                                    </div>
                                                @endif

                                            </div>

                                            @if ($description !== '')
                                                <p class="home-event-description">
                                                    {{ \Illuminate\Support\Str::limit($description, 105) }}
                                                </p>
                                            @endif

                                            <div class="home-event-actions">

                                                    @if ($event->registration_is_open)
                                                        <a
                                                            href="{{ $eventRegisterUrl }}"
                                                            class="home-register-btn"
                                                        >
                                                            Register Now
                                                        </a>
                                                    @else
                                                        <span class="home-ended-label">
                                                            Registration Closed
                                                        </span>
                                                    @endif

                                            </div>

                                        </div>

                                    </article>

                                @endforeach

                            </div>

                        @else

                            <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center">
                                <p class="font-semibold text-[#233F7D]">
                                    No upcoming events yet
                                </p>

                                <p class="mt-2 text-sm text-slate-500">
                                    New events will appear here automatically.
                                </p>
                            </div>

                        @endif

                    </div>


                    {{-- PAST EVENTS --}}
                    @if ($pastEvents->isNotEmpty())
                        <div class="mt-10">

                            <div class="mb-4">

                                <div class="flex items-center gap-3">
                                    <span class="h-2.5 w-2.5 rounded-full bg-slate-400"></span>

                                    <h3 class="text-xl font-bold text-[#233F7D]">
                                        Past Events
                                    </h3>
                                </div>

                                <p class="mt-1 pl-5 text-sm text-slate-500">
                                    Recently completed events.
                                </p>

                            </div>

                            <div class="home-events-grid">

                                @foreach ($pastEvents as $event)

                                    @php
                                        $eventStart = $event->starts_at
                                            ? \Illuminate\Support\Carbon::parse($event->starts_at)
                                            : null;

                                        $eventEnd = $event->ends_at
                                            ? \Illuminate\Support\Carbon::parse($event->ends_at)
                                            : null;

                                        $eventName = trim((string) $event->name);

                                        $assignedDays = $eventDaysByEvent
                                            ->get($event->id, collect())
                                            ->values();

                                        $assignedDates = $assignedDays
                                            ->map(
                                                fn ($day) => $day->event_date
                                                    ? \Illuminate\Support\Carbon::parse($day->event_date)
                                                    : null
                                            )
                                            ->filter()
                                            ->values();

                                        $usesAssignedDays = $assignedDates->isNotEmpty();
                                        $isAssignedMultiDay = $assignedDates->count() > 1;
                                        $displayDate = $assignedDates->first() ?? $eventStart;

                                        $cardDateLabel = null;

                                        if ($isAssignedMultiDay) {
                                            $firstDate = $assignedDates->first();
                                            $lastDate = $assignedDates->last();

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

                                        if ($usesAssignedDays && $assignedDays->first()?->starts_at) {
                                            $cardTimeLabel =
                                                \Illuminate\Support\Carbon::parse(
                                                    $assignedDays->first()->starts_at
                                                )->format('g:i A');

                                            if ($assignedDays->first()?->ends_at) {
                                                $cardTimeLabel .=
                                                    ' - '
                                                    . \Illuminate\Support\Carbon::parse(
                                                        $assignedDays->first()->ends_at
                                                    )->format('g:i A');
                                            }
                                        } elseif ($eventStart) {
                                            $cardTimeLabel = $eventStart->format('g:i A');

                                            if ($eventEnd) {
                                                $cardTimeLabel .= ' - ' . $eventEnd->format('g:i A');
                                            }
                                        }

                                        $eventDetailsUrl = route('public.events.show', $event->slug);
                                        $eventRegisterUrl = route('public.events.register', $event->slug);

                                        $eventImage = $event->registration_banner_image_path;
                                        $eventImageUrl = null;

                                        if ($eventImage) {
                                            if (\Illuminate\Support\Str::startsWith($eventImage, ['http://', 'https://'])) {
                                                $eventImageUrl = $eventImage;
                                            } elseif (\Illuminate\Support\Str::startsWith($eventImage, ['storage/', '/storage/'])) {
                                                $eventImageUrl = asset(ltrim($eventImage, '/'));
                                            } else {
                                                $eventImageUrl = asset('storage/' . ltrim($eventImage, '/'));
                                            }
                                        }

                                        $description = trim(strip_tags((string) $event->description));
                                    @endphp

                                    <article class="home-event-card ">

                                        <a
                                            href="{{ $eventDetailsUrl }}"
                                            class="home-event-media"
                                            aria-label="{{ $eventName !== '' ? $eventName : 'View event' }}"
                                        >
                                            @if ($eventImageUrl)
                                                <img
                                                    src="{{ $eventImageUrl }}"
                                                    alt="{{ $eventName !== '' ? $eventName : 'Event image' }}"
                                                    class="home-event-image"
                                                >
                                            @else
                                                <div class="home-event-fallback"></div>
                                            @endif

                                            <span class="home-status-badge ended">
                                                Ended
                                            </span>
                                        </a>

                                        <div class="home-event-body">

                                            <h4 class="home-event-title">
                                                <a href="{{ $eventDetailsUrl }}">
                                                    {{ $eventName !== '' ? $eventName : 'Event' }}
                                                </a>
                                            </h4>

                                            <div class="home-event-meta">

                                                @if ($cardDateLabel)
                                                    <div class="home-meta-item">
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
                                                    <div class="home-meta-item">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                                                            <path d="M12 21s6-5.2 6-11a6 6 0 1 0-12 0c0 5.8 6 11 6 11Z"/>
                                                            <circle cx="12" cy="10" r="2"/>
                                                        </svg>

                                                        <span>{{ $event->venue }}</span>
                                                    </div>
                                                @endif

                                            </div>

                                            @if ($description !== '')
                                                <p class="home-event-description">
                                                    {{ \Illuminate\Support\Str::limit($description, 105) }}
                                                </p>
                                            @endif

                                            <div class="home-event-actions">

                                                    <span class="home-ended-label">
                                                        Event Ended
                                                    </span>

                                            </div>

                                        </div>

                                    </article>

                                @endforeach

                            </div>

                        </div>
                    @endif

                </div>

            </div>

        </section>


        {{-- PLATFORM CAPABILITIES --}}
        <section class="bg-slate-50 py-16 sm:py-20">

            <div class="mx-auto max-w-7xl px-6 lg:px-8">

                <div class="mx-auto max-w-2xl text-center">

                    <p class="text-sm font-bold uppercase tracking-wider text-[#FF9418]">
                        Platform Capabilities
                    </p>

                    <h2 class="mt-3 text-3xl font-bold text-[#233F7D] sm:text-4xl">
                        More Than Registration
                    </h2>

                    <p class="mt-4 leading-7 text-slate-600">
                        Powerful tools for organizers to manage communication,
                        attendance, event operations and reporting from one platform.
                    </p>

                </div>

                <div class="mt-12 grid gap-6 md:grid-cols-2 xl:grid-cols-3">

                    <div class="rounded-2xl border border-slate-200 bg-white p-7 shadow-sm transition hover:-translate-y-1 hover:shadow-md">
                        <div class="h-1 w-12 rounded-full bg-[#233F7D]"></div>
                        <h3 class="mt-5 text-xl font-bold text-[#233F7D]">
                            Multi-Event Management
                        </h3>
                        <p class="mt-3 leading-7 text-slate-600">
                            Create and manage multiple events, attendee categories,
                            registration settings and capacities from one dashboard.
                        </p>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-7 shadow-sm transition hover:-translate-y-1 hover:shadow-md">
                        <div class="h-1 w-12 rounded-full bg-[#FF9418]"></div>
                        <h3 class="mt-5 text-xl font-bold text-[#233F7D]">
                            Communication
                        </h3>
                        <p class="mt-3 leading-7 text-slate-600">
                            Send confirmations, reminders and event updates through
                            SMS, email and WhatsApp communication channels.
                        </p>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-7 shadow-sm transition hover:-translate-y-1 hover:shadow-md">
                        <div class="h-1 w-12 rounded-full bg-[#233F7D]"></div>
                        <h3 class="mt-5 text-xl font-bold text-[#233F7D]">
                            Attendance Management
                        </h3>
                        <p class="mt-3 leading-7 text-slate-600">
                            Track attendee entry, check-in activity and attendance
                            securely across your event.
                        </p>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-7 shadow-sm transition hover:-translate-y-1 hover:shadow-md">
                        <div class="h-1 w-12 rounded-full bg-[#FF9418]"></div>
                        <h3 class="mt-5 text-xl font-bold text-[#233F7D]">
                            Event Reporting
                        </h3>
                        <p class="mt-3 leading-7 text-slate-600">
                            Monitor registrations, attendance and event activity
                            with clear operational reports.
                        </p>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-7 shadow-sm transition hover:-translate-y-1 hover:shadow-md">
                        <div class="h-1 w-12 rounded-full bg-[#233F7D]"></div>
                        <h3 class="mt-5 text-xl font-bold text-[#233F7D]">
                            Multi-Day Events
                        </h3>
                        <p class="mt-3 leading-7 text-slate-600">
                            Manage events that run across multiple days and track
                            expected and actual attendance by day.
                        </p>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-7 shadow-sm transition hover:-translate-y-1 hover:shadow-md">
                        <div class="h-1 w-12 rounded-full bg-[#FF9418]"></div>
                        <h3 class="mt-5 text-xl font-bold text-[#233F7D]">
                            Event Branding
                        </h3>
                        <p class="mt-3 leading-7 text-slate-600">
                            Present professional event experiences with branded
                            registration pages, badges and attendee communication.
                        </p>
                    </div>

                </div>

            </div>
        </section>


        {{-- PROCESS --}}
        <section class="py-16 sm:py-20">

            <div class="mx-auto max-w-6xl px-6 text-center lg:px-8">

                <p class="text-sm font-bold uppercase tracking-wider text-[#FF9418]">
                    Simple Process
                </p>

                <h2 class="mt-3 text-3xl font-bold text-[#233F7D]">
                    From Registration to Check-in
                </h2>

                <p class="mx-auto mt-4 max-w-2xl leading-7 text-slate-600">
                    A simple attendee journey from joining an event to entering the venue.
                </p>

                <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">

                    <div class="rounded-2xl border border-slate-100 bg-slate-50 p-6">
                        <span class="text-sm font-bold text-[#FF9418]">
                            01
                        </span>

                        <h3 class="mt-2 font-semibold text-slate-800">
                            Register
                        </h3>

                        <p class="mt-2 text-sm leading-6 text-slate-500">
                            Complete the event registration form.
                        </p>
                    </div>

                    <div class="rounded-2xl border border-slate-100 bg-slate-50 p-6">
                        <span class="text-sm font-bold text-[#FF9418]">
                            02
                        </span>

                        <h3 class="mt-2 font-semibold text-slate-800">
                            Get Your Badge
                        </h3>

                        <p class="mt-2 text-sm leading-6 text-slate-500">
                            Receive your digital badge with a secure QR code.
                        </p>
                    </div>

                    <div class="rounded-2xl border border-slate-100 bg-slate-50 p-6">
                        <span class="text-sm font-bold text-[#FF9418]">
                            03
                        </span>

                        <h3 class="mt-2 font-semibold text-slate-800">
                            Stay Updated
                        </h3>

                        <p class="mt-2 text-sm leading-6 text-slate-500">
                            Receive confirmations, reminders and event updates.
                        </p>
                    </div>

                    <div class="rounded-2xl border border-slate-100 bg-slate-50 p-6">
                        <span class="text-sm font-bold text-[#FF9418]">
                            04
                        </span>

                        <h3 class="mt-2 font-semibold text-slate-800">
                            Check In
                        </h3>

                        <p class="mt-2 text-sm leading-6 text-slate-500">
                            Scan your QR code for fast and secure event entry.
                        </p>
                    </div>

                </div>

            </div>
        </section>



        {{-- CONTACT --}}
        <section id="contact" class="bg-slate-50 px-6 py-16 sm:py-20 lg:px-8">

            <div class="mx-auto max-w-7xl">

                <div class="mx-auto max-w-2xl text-center">

                    <p class="text-sm font-bold uppercase tracking-wider text-[#FF9418]">
                        Contact eLive
                    </p>

                    <h2 class="mt-3 text-3xl font-bold text-[#233F7D] sm:text-4xl">
                        Get in Touch
                    </h2>

                    <p class="mt-4 leading-7 text-slate-600">
                        Contact our team for event setup, registration, attendee management,
                        communication, digital badges and QR check-in support.
                    </p>

                </div>

                <div class="mt-10 grid gap-6 md:grid-cols-2 xl:grid-cols-4">

                    <a
                        href="tel:+255745939140"
                        class="rounded-2xl border border-slate-200 bg-white p-7 shadow-sm transition hover:-translate-y-1 hover:shadow-md"
                    >
                        <p class="text-xs font-bold uppercase tracking-wider text-[#FF9418]">
                            Call Us
                        </p>

                        <p class="mt-3 text-xl font-bold text-[#233F7D]">
                            +255 745 939 140
                        </p>

                        <p class="mt-3 leading-7 text-slate-600">
                            Speak directly with our team.
                        </p>
                    </a>

                    <a
                        href="https://wa.me/255777792017"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="rounded-2xl border border-slate-200 bg-white p-7 shadow-sm transition hover:-translate-y-1 hover:shadow-md"
                    >
                        <p class="text-xs font-bold uppercase tracking-wider text-[#FF9418]">
                            WhatsApp
                        </p>

                        <p class="mt-3 text-xl font-bold text-[#233F7D]">
                            +255 777 792 017
                        </p>

                        <p class="mt-3 leading-7 text-slate-600">
                            Get quick support instantly.
                        </p>
                    </a>

                    <a
                        href="mailto:info@elive.co.tz"
                        class="rounded-2xl border border-slate-200 bg-white p-7 shadow-sm transition hover:-translate-y-1 hover:shadow-md"
                    >
                        <p class="text-xs font-bold uppercase tracking-wider text-[#FF9418]">
                            Email
                        </p>

                        <p class="mt-3 text-xl font-bold text-[#233F7D]">
                            info@elive.co.tz
                        </p>

                        <p class="mt-3 leading-7 text-slate-600">
                            Send your inquiry anytime.
                        </p>
                    </a>

                    <a
                        href="https://www.google.com/maps/search/?api=1&query=Kawawa+Road+Kinondoni+B+Dar+es+Salaam"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="rounded-2xl border border-slate-200 bg-white p-7 shadow-sm transition hover:-translate-y-1 hover:shadow-md"
                    >
                        <p class="text-xs font-bold uppercase tracking-wider text-[#FF9418]">
                            Location
                        </p>

                        <p class="mt-3 text-xl font-bold text-[#233F7D]">
                            Kinondoni B
                        </p>

                        <p class="mt-3 leading-7 text-slate-600">
                            Kawawa Road, Dar es Salaam.
                        </p>
                    </a>

                </div>

            </div>

        </section>


        {{-- CTA --}}
        <section class="px-6 pb-16 sm:pb-20 lg:px-8">

            <div
                class="mx-auto max-w-7xl rounded-3xl bg-[#233F7D] px-6 py-14 text-center shadow-xl sm:px-12"
            >

                <h2 class="text-3xl font-bold text-white">
                    Ready to deliver a better event experience?
                </h2>

                <p class="mx-auto mt-4 max-w-xl leading-7 text-blue-100">
                    Talk to eLive about your event and we will help you set up
                    registration, badges, communication and attendance management.
                </p>

                <a
                    href="#contact"
                    class="mt-8 inline-flex rounded-xl bg-[#FF9418] px-7 py-3.5 font-semibold text-white transition hover:bg-[#ed8611]"
                >
                    Contact eLive
                </a>

            </div>

        </section>

    </main>


    {{-- FOOTER --}}
    <footer class="bg-[#17233F]">

        <div
            class="mx-auto flex max-w-7xl flex-col gap-8 px-6 py-10 md:flex-row md:items-center md:justify-between lg:px-8"
        >

            <div>

                <div class="inline-flex rounded-xl bg-white px-4 py-2">
                    <img
                        src="{{ asset('eLive-Logo.png') }}"
                        alt="eLive Events"
                        class="h-9 w-auto"
                    >
                </div>

                <p class="mt-4 text-sm text-slate-400">
                    Smart event registration and management.
                </p>

            </div>

            <div class="flex flex-wrap gap-x-6 gap-y-3 text-sm text-slate-300">

                <a
                    href="{{ route('public.events.index') }}"
                    class="transition hover:text-white"
                >
                    Events
                </a>

                <a
                    href="#contact"
                    class="transition hover:text-white"
                >
                    Contact
                </a>

                @if (Route::has('privacy'))
                    <a href="{{ route('privacy') }}" class="transition hover:text-white">
                        Privacy
                    </a>
                @else
                    <a
                        href="mailto:info@elive.co.tz?subject=Privacy%20Inquiry"
                        class="transition hover:text-white"
                    >
                        Privacy
                    </a>
                @endif

            </div>

        </div>

        <div class="border-t border-white/10">

            <div
                class="mx-auto max-w-7xl px-6 py-5 text-sm text-slate-500 lg:px-8"
            >
                © {{ date('Y') }} eLive Events. All rights reserved.
            </div>

        </div>

    </footer>

</body>
</html>