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
    $firstEventDay = $days->first();
    $lastEventDay = $days->last();

    /*
     * Build precise schedule timestamps from the assigned Event Day date and
     * time. If an Event Day has no time, retain the Event start/end time as a
     * fallback. This prevents the countdown from ending at midnight.
     */
    $scheduleStart = $firstAssignedDate
        ? $firstAssignedDate->copy()->startOfDay()
        : $eventStart?->copy();

    if ($scheduleStart && ($firstEventDay?->starts_at || $eventStart)) {
        $startTime = Carbon::parse($firstEventDay?->starts_at ?: $eventStart);
        $scheduleStart->setTime($startTime->hour, $startTime->minute, $startTime->second);
    }

    $scheduleEnd = $lastAssignedDate
        ? $lastAssignedDate->copy()->endOfDay()
        : $eventEnd?->copy();

    if ($scheduleEnd && ($lastEventDay?->ends_at || $eventEnd)) {
        $endTime = Carbon::parse($lastEventDay?->ends_at ?: $eventEnd);
        $scheduleEnd->setTime($endTime->hour, $endTime->minute, $endTime->second);
    }

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

    $ticketSettings = $event->ticketSetting;
    $ticketSalesOpen = $ticketSettings
        && $ticketSettings->salesAreOpen()
        && $event->ticketTypes()->onSale()->exists();
    $ticketSalesEnabled = (bool) ($ticketSettings?->ticket_sales_enabled);
    $ticketUrl = route('public.tickets.buy', ['event' => $event->slug]);

    $finiteRemainingSeats = $publicTicketTypes
        ->map(fn ($ticketType) => $ticketType->remainingCapacity())
        ->filter(fn ($remaining) => $remaining !== null);

    $remainingSeatCount = $finiteRemainingSeats->isNotEmpty()
        ? max(0, (int) $finiteRemainingSeats->sum())
        : null;

    $hasUnlimitedTicketCapacity = $publicTicketTypes->contains(
        fn ($ticketType) => $ticketType->remainingCapacity() === null
    );

    $seatAvailabilityLabel = $hasUnlimitedTicketCapacity
        ? 'Available'
        : ($remainingSeatCount !== null ? number_format($remainingSeatCount) : null);

    $seatStatValue = $seatAvailabilityLabel
        ?: ($event->capacity ? number_format($event->capacity) : null);
    $seatStatLabel = $seatAvailabilityLabel ? 'Seats Available' : 'Total Capacity';

    $gallery = collect($event->public_gallery ?? [])
        ->filter(fn ($item) => filled($item['image_path'] ?? null));
    $speakers = collect($event->public_speakers ?? [])
        ->filter(fn ($item) => filled($item['name'] ?? null));
    $faqs = collect($event->public_faqs ?? [])
        ->filter(fn ($item) => filled($item['question'] ?? null) && filled($item['answer'] ?? null));
    $sessions = $event->activeSessions()->orderBy('starts_at')->get();
    $policies = collect([
        'Dress Code' => $event->dress_code,
        'Seating Information' => $event->seating_policy,
        'Age Restrictions' => $event->age_restriction,
        'Ticket and Entry Policy' => $event->ticket_policy,
        'Cancellation / Refund Policy' => $event->refund_policy,
    ])->filter();
    $organization = $event->organization;
    $contactEmail = $event->organizer_contact_email
        ?: $organization?->email
        ?: $organization?->support_email;
    $contactPhone = $event->organizer_contact_phone
        ?: $organization?->phone
        ?: $organization?->support_phone;
    $relatedEvents = $organization
        ? $organization->events()
            ->whereKeyNot($event->getKey())
            ->where('status', \App\Models\Event::STATUS_ACTIVE)
            ->where('starts_at', '>=', now())
            ->orderBy('starts_at')
            ->limit(3)
            ->get()
        : collect();
    $shareUrl = route('public.events.show', ['event' => $event->slug]);
    $shareText = $event->name
        . ($event->public_theme ? ' — ' . $event->public_theme : '');

    $socialShareTitle = trim((string) $event->social_share_title)
        ?: $event->name;

    $socialShareDescription = trim((string) $event->social_share_description)
        ?: Str::limit(
            strip_tags((string) $event->description),
            180
        );

    if ($socialShareDescription === '') {
        $socialShareDescription = 'Event details, tickets and registration information from eLive Events.';
    }

    $socialShareImage = $event->social_share_image_path
        ?: $event->registration_banner_image_path;

    $socialShareImageUrl = null;

    if ($socialShareImage) {
        if (Str::startsWith($socialShareImage, ['http://', 'https://'])) {
            $socialShareImageUrl = $socialShareImage;
        } elseif (Str::startsWith($socialShareImage, ['storage/', '/storage/'])) {
            $socialShareImageUrl = asset(ltrim($socialShareImage, '/'), true);
        } else {
            $socialShareImageUrl = asset('storage/' . ltrim($socialShareImage, '/'), true);
        }

        // Social crawlers require an absolute public URL. Force HTTPS in
        // production so WhatsApp/Facebook do not ignore the event image.
        if (app()->environment('production')) {
            $socialShareImageUrl = preg_replace(
                '/^http:\/\//i',
                'https://',
                $socialShareImageUrl
            );
        }

        // Bust stale social-preview caches whenever the event is updated.
        // This keeps the public event URL unchanged while making the image
        // resource look fresh to WhatsApp/Facebook/Twitter crawlers.
        $socialShareImageUrl .= (str_contains($socialShareImageUrl, '?') ? '&' : '?')
            . 'v=' . optional($event->updated_at)->timestamp;
    }
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

    <link rel="canonical" href="{{ $shareUrl }}">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="eLive Events">
    <meta property="og:title" content="{{ $socialShareTitle }}">
    <meta property="og:description" content="{{ $socialShareDescription }}">
    <meta property="og:url" content="{{ $shareUrl }}">
    @if ($socialShareImageUrl)
        <meta property="og:image" content="{{ $socialShareImageUrl }}">
        <meta property="og:image:secure_url" content="{{ $socialShareImageUrl }}">
        <meta property="og:image:type" content="image/jpeg">
        <meta property="og:image:width" content="1200">
        <meta property="og:image:height" content="630">
        <meta property="og:image:alt" content="{{ $socialShareTitle }}">
    @endif

    <meta name="twitter:card" content="{{ $socialShareImageUrl ? 'summary_large_image' : 'summary' }}">
    <meta name="twitter:title" content="{{ $socialShareTitle }}">
    <meta name="twitter:description" content="{{ $socialShareDescription }}">
    @if ($socialShareImageUrl)
        <meta name="twitter:image" content="{{ $socialShareImageUrl }}">
    @endif

    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="stylesheet" href="{{ asset('css/creato-font.css') }}">
    @if (
        file_exists(public_path('build/manifest.json'))
        || file_exists(public_path('hot'))
    )
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
            background: var(--elive-navy);
        }

        .hero-overlay {
            position: absolute;
            inset: 0;
            background: rgba(15, 23, 42, .48);
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
            align-items: stretch;
        }

        .content-card,
        .info-card {
            background: #FFFFFF;
            border: 1px solid var(--elive-border);
            border-top: 4px solid var(--elive-orange);
            border-radius: 20px;
            box-shadow: 0 7px 22px rgba(15, 23, 42, .04);
            transition: transform .2s ease, box-shadow .2s ease;
        }

        .content-card:hover,
        .info-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 32px rgba(15, 23, 42, .08);
        }

        .content-card {
            padding: 30px;
        }

        .event-story-card {
            display: flex;
            flex-direction: column;
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
            max-width: 850px;
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

        .event-theme-callout {
            margin-top: 26px;
            padding: 20px 22px;
            border-left: 4px solid var(--elive-orange);
            border-radius: 0 14px 14px 0;
            background: #F5F9FD;
            color: var(--elive-navy);
        }

        .event-theme-label {
            display: block;
            margin-bottom: 7px;
            color: var(--elive-orange);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: .13em;
            text-transform: uppercase;
        }

        .event-theme-callout strong {
            display: block;
            font-size: clamp(18px, 2.2vw, 24px);
            line-height: 1.3;
            letter-spacing: -.015em;
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

        .event-info-card {
            align-self: start;
            display: flex;
            flex-direction: column;
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

        .hero-theme {
            margin: 0 0 12px;
            color: #FFD08A;
            font-size: clamp(14px, 2vw, 18px);
            font-weight: 800;
            letter-spacing: .02em;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 22px;
        }

        .hero-action {
            min-height: 46px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0 20px;
            border: 1px solid rgba(255, 255, 255, .28);
            border-radius: 12px;
            background: rgba(255, 255, 255, .12);
            color: #FFFFFF;
            font-size: 14px;
            font-weight: 800;
            backdrop-filter: blur(12px);
            transition: transform .2s ease, background .2s ease;
        }

        .hero-action.primary {
            border-color: var(--elive-orange);
            background: var(--elive-orange);
        }

        .hero-action:hover {
            background: var(--elive-blue);
            transform: translateY(-2px);
        }

        .mobile-ticket-cta {
            display: none;
        }

        .countdown-card {
            margin-top: 22px;
            padding: 24px;
            border: 1px solid rgba(35, 63, 126, .12);
            border-radius: 18px;
            background: var(--elive-navy);
            color: #FFFFFF;
            box-shadow: 0 16px 34px rgba(11, 31, 58, .18);
            text-align: center;
        }

        .countdown-eyebrow {
            margin: 0 0 16px;
            color: #FFD08A;
            font-size: 13px;
            font-weight: 900;
            letter-spacing: .12em;
            text-transform: uppercase;
        }

        .countdown-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            max-width: 680px;
            margin: 0 auto;
        }

        .countdown-unit {
            padding: 16px 10px;
            border: 1px solid rgba(255, 255, 255, .16);
            border-radius: 14px;
            background: rgba(255, 255, 255, .09);
            backdrop-filter: blur(10px);
        }

        .countdown-value {
            display: block;
            font-size: clamp(26px, 4vw, 42px);
            font-weight: 900;
            line-height: 1;
        }

        .countdown-label {
            display: block;
            margin-top: 8px;
            color: rgba(255, 255, 255, .76);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }

        .countdown-message {
            margin: 0;
            font-size: clamp(22px, 4vw, 34px);
            font-weight: 900;
        }

        .hero-summary {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            background: #FFFFFF;
        }

        .hero-stat {
            padding: 20px 22px;
            border-right: 1px solid var(--elive-border);
        }

        .hero-stat:last-child {
            border-right: 0;
        }

        .hero-stat strong {
            display: block;
            color: var(--elive-navy);
            font-size: 22px;
            line-height: 1;
        }

        .hero-stat span {
            display: block;
            margin-top: 7px;
            color: var(--elive-muted);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
        }

        .public-detail-section {
            padding: 76px 0;
        }

        .public-detail-section.alt {
            background: #FFFFFF;
            border-block: 1px solid #EDF1F6;
        }

        .public-detail-kicker {
            margin: 0 0 10px;
            color: var(--elive-orange);
            font-size: 12px;
            font-weight: 900;
            letter-spacing: .14em;
            text-transform: uppercase;
        }

        .public-detail-heading {
            max-width: 720px;
            margin: 0 0 30px;
            color: var(--elive-navy);
            font-size: clamp(28px, 4vw, 42px);
            line-height: 1.08;
            letter-spacing: -.035em;
        }

        .speaker-grid,
        .related-grid,
        .ticket-grid,
        .detail-card-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 20px;
        }

        .ticket-grid {
            grid-template-columns: repeat(4, minmax(0, 1fr));
            align-items: stretch;
        }

        .ticket-package-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 22px;
        }

        .ticket-package-card {
            overflow: hidden;
            border: 1px solid var(--elive-border);
            border-radius: 22px;
            background: #FFFFFF;
            box-shadow: 0 14px 34px rgba(15, 23, 42, .07);
        }

        .ticket-package-card.vvip {
            border-color: rgba(232, 122, 18, .38);
        }

        .ticket-package-header {
            padding: 24px 26px;
            background: var(--elive-navy);
            color: #FFFFFF;
        }

        .ticket-package-card.vvip .ticket-package-header {
            background: linear-gradient(135deg, var(--elive-navy), var(--elive-blue));
        }

        .ticket-package-label {
            display: block;
            margin-bottom: 6px;
            color: #F6A623;
            font-size: 11px;
            font-weight: 900;
            letter-spacing: .14em;
            text-transform: uppercase;
        }

        .ticket-package-header h3 {
            margin: 0;
            font-size: 26px;
            line-height: 1.15;
        }

        .ticket-package-list {
            display: grid;
            gap: 0;
            margin: 0;
            padding: 8px 26px 14px;
            list-style: none;
        }

        .ticket-package-list li {
            position: relative;
            padding: 19px 0 19px 28px;
            border-bottom: 1px solid #EDF1F6;
            color: var(--elive-muted);
            font-size: 16px;
            line-height: 1.7;
        }

        .ticket-package-list li:last-child {
            border-bottom: 0;
        }

        .ticket-package-list li::before {
            content: '\2713';
            position: absolute;
            top: 18px;
            left: 0;
            display: grid;
            width: 18px;
            height: 18px;
            place-items: center;
            border-radius: 50%;
            background: rgba(35, 63, 126, .10);
            color: var(--elive-blue);
            font-size: 11px;
            font-weight: 900;
        }

        .ticket-package-list strong {
            display: block;
            margin-bottom: 7px;
            color: var(--elive-navy);
            font-size: 16px;
            line-height: 1.35;
        }

        .gallery-viewport {
            overflow-x: auto;
            overscroll-behavior-inline: contain;
            scrollbar-width: none;
            touch-action: pan-x;
            cursor: grab;
        }

        .gallery-viewport::-webkit-scrollbar {
            display: none;
        }

        .gallery-viewport:active {
            cursor: grabbing;
        }

        .gallery-track {
            display: flex;
            gap: 20px;
        }

        .gallery-item,
        .speaker-card,
        .related-card,
        .ticket-tier,
        .detail-card {
            overflow: hidden;
            border: 1px solid var(--elive-border);
            border-radius: 20px;
            background: #FFFFFF;
            box-shadow: 0 12px 30px rgba(15, 23, 42, .06);
            transition: transform .25s ease, box-shadow .25s ease;
        }

        .gallery-item:hover,
        .speaker-card:hover,
        .related-card:hover,
        .ticket-tier:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 44px rgba(15, 23, 42, .11);
        }

        .gallery-item {
            position: relative;
            flex: 0 0 calc((100% - 40px) / 3);
            min-height: 260px;
        }

        .gallery-item img,
        .speaker-photo {
            width: 100%;
            height: 100%;
            display: block;
            object-fit: cover;
        }

        .gallery-caption {
            position: absolute;
            right: 12px;
            bottom: 12px;
            left: 12px;
            padding: 12px 14px;
            border-radius: 12px;
            background: rgba(15, 23, 42, .76);
            color: #FFFFFF;
            font-size: 13px;
            backdrop-filter: blur(10px);
        }

        body.gallery-lightbox-open {
            overflow: hidden;
        }

        .gallery-lightbox[hidden] {
            display: none;
        }

        .gallery-lightbox {
            position: fixed;
            inset: 0;
            z-index: 2000;
            display: grid;
            grid-template-rows: auto minmax(0, 1fr) auto;
            gap: 14px;
            padding: 20px;
            background: rgba(11, 31, 58, .96);
        }

        .gallery-lightbox-toolbar {
            display: flex;
            justify-content: flex-end;
        }

        .gallery-lightbox-button {
            width: 46px;
            height: 46px;
            display: inline-grid;
            place-items: center;
            border: 1px solid rgba(255, 255, 255, .28);
            border-radius: 50%;
            background: #FFFFFF;
            color: var(--elive-navy);
            font: inherit;
            font-size: 26px;
            font-weight: 800;
            cursor: pointer;
            box-shadow: 0 12px 28px rgba(0, 0, 0, .22);
        }

        .gallery-lightbox-button:hover,
        .gallery-lightbox-button:focus-visible {
            border-color: var(--elive-orange);
            background: var(--elive-orange);
            color: #FFFFFF;
            outline: none;
        }

        .gallery-lightbox-stage {
            position: relative;
            min-height: 0;
            display: grid;
            place-items: center;
        }

        .gallery-lightbox-image {
            max-width: min(1180px, calc(100vw - 150px));
            max-height: calc(100vh - 160px);
            display: block;
            border-radius: 18px;
            object-fit: contain;
            box-shadow: 0 24px 70px rgba(0, 0, 0, .42);
        }

        .gallery-lightbox-previous,
        .gallery-lightbox-next {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
        }

        .gallery-lightbox-previous { left: 4px; }
        .gallery-lightbox-next { right: 4px; }

        .gallery-lightbox-footer {
            min-height: 28px;
            color: #FFFFFF;
            text-align: center;
        }

        .gallery-lightbox-caption {
            margin: 0;
            font-size: 15px;
            font-weight: 700;
        }

        .ticket-tier,
        .detail-card,
        .related-card {
            padding: 26px;
        }

        .policy-grid {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 20px;
        }

        .policy-grid .detail-card {
            flex: 0 1 calc((100% - 40px) / 3);
            min-width: 0;
            display: flex;
            align-items: flex-start;
            gap: 16px;
            padding: 24px;
            border-top: 4px solid var(--elive-orange);
        }

        .detail-card-icon {
            width: 44px;
            height: 44px;
            flex: 0 0 44px;
            display: grid;
            place-items: center;
            border-radius: 12px;
            background: #EEF5FF;
            color: var(--elive-blue);
        }

        .detail-card-icon svg {
            width: 23px;
            height: 23px;
            fill: none;
            stroke: currentColor;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .detail-card-copy {
            min-width: 0;
        }

        .detail-card-copy p {
            margin-bottom: 0;
        }

        .ticket-tier {
            position: relative;
            border-top: 4px solid var(--elive-orange);
        }

        .ticket-tier h3,
        .detail-card h3,
        .related-card h3,
        .speaker-copy h3 {
            margin: 0;
            color: var(--elive-navy);
            font-size: 19px;
        }

        .ticket-price {
            margin: 16px 0 10px;
            color: var(--elive-blue);
            font-size: 28px;
            font-weight: 900;
        }

        .ticket-copy,
        .detail-card p,
        .related-card p,
        .speaker-copy p {
            color: var(--elive-muted);
            font-size: 14px;
            line-height: 1.7;
        }

        .ticket-stock {
            display: inline-flex;
            margin: 10px 0 18px;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .05em;
        }

        .stock-ok { background: #DCFCE7; color: #166534; }
        .stock-low { background: #FEF3C7; color: #92400E; }
        .stock-out { background: #FEE2E2; color: #991B1B; }

        .public-action,
        .share-link {
            display: inline-flex;
            min-height: 42px;
            align-items: center;
            justify-content: center;
            padding: 0 17px;
            border: 0;
            border-radius: 11px;
            background: var(--elive-navy);
            color: #FFFFFF;
            font: inherit;
            font-size: 13px;
            font-weight: 800;
            cursor: pointer;
            transition: background .2s ease, transform .2s ease;
        }

        .public-action:hover,
        .share-link:hover {
            background: var(--elive-blue);
            transform: translateY(-1px);
        }

        .public-action.secondary {
            background: #EEF6FA;
            color: var(--elive-blue);
        }

        .programme-list {
            display: grid;
            gap: 14px;
        }

        .programme-row {
            display: grid;
            grid-template-columns: 180px minmax(0, 1fr);
            gap: 22px;
            padding: 22px;
            border: 1px solid var(--elive-border);
            border-radius: 16px;
            background: #FFFFFF;
        }

        .programme-time {
            color: var(--elive-blue);
            font-weight: 900;
        }

        .programme-row h3 {
            margin: 0;
            color: var(--elive-navy);
        }

        .programme-row p {
            margin: 6px 0 0;
            color: var(--elive-muted);
        }

        .speaker-photo {
            height: 300px;
        }

        .speaker-copy {
            padding: 20px;
        }

        .faq-section-header {
            text-align: center;
        }

        .faq-section-header .public-detail-heading {
            margin-inline: auto;
        }

        .faq-list {
            max-width: 980px;
            display: grid;
            gap: 12px;
            margin-inline: auto;
        }

        .faq-item {
            border: 1px solid var(--elive-border);
            border-radius: 18px;
            background: #FFFFFF;
            box-shadow: 0 8px 24px rgba(15, 23, 42, .05);
            transition: border-color .25s ease, box-shadow .25s ease;
        }

        .faq-item[open] {
            border-color: var(--elive-orange);
            box-shadow: 0 16px 36px rgba(22, 25, 67, .10);
        }

        .faq-item summary {
            display: grid;
            grid-template-columns: 34px minmax(0, 1fr) 38px;
            align-items: center;
            gap: 14px;
            padding: 15px 18px;
            color: var(--elive-navy);
            font-weight: 800;
            cursor: pointer;
            list-style: none;
        }

        .faq-item summary::-webkit-details-marker {
            display: none;
        }

        .faq-number {
            width: 30px;
            height: 30px;
            display: inline-grid;
            place-items: center;
            border-radius: 50%;
            background: #EEF5FF;
            color: var(--elive-blue);
            font-size: 11px;
            font-weight: 900;
        }

        .faq-question {
            min-width: 0;
        }

        .faq-toggle {
            width: 36px;
            height: 36px;
            display: inline-grid;
            place-items: center;
            border-radius: 50%;
            background: var(--elive-navy);
            color: #FFFFFF;
            font-size: 0;
            box-shadow: 0 7px 16px rgba(22, 25, 67, .18);
            transition: background .25s ease, transform .25s ease;
        }

        .faq-toggle::before {
            content: '+';
            font-size: 22px;
            font-weight: 500;
            line-height: 1;
        }

        .faq-item[open] .faq-toggle {
            background: var(--elive-orange);
            transform: rotate(180deg);
        }

        .faq-item[open] .faq-toggle::before {
            content: '−';
        }

        .faq-item[open] .faq-number {
            background: var(--elive-navy);
            color: #FFFFFF;
        }

        .faq-answer {
            margin: 0 18px 0 66px;
            padding: 0 0 20px;
            color: var(--elive-muted);
            line-height: 1.7;
            animation: faq-answer-in .25s ease both;
        }

        @keyframes faq-answer-in {
            from {
                opacity: 0;
                transform: translateY(-5px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .share-row {
            display: flex;
            flex-wrap: wrap;
            gap: 9px;
            margin-top: 18px;
        }

        .final-cta {
            position: relative;
            overflow: hidden;
            padding: clamp(30px, 5vw, 52px);
            border-radius: 28px;
            border-top: 5px solid var(--elive-orange);
            background: var(--elive-navy);
            color: #FFFFFF;
            box-shadow: 0 24px 60px rgba(22, 25, 67, .22);
        }

        .final-cta h2 {
            max-width: 760px;
            margin: 0;
            font-size: clamp(30px, 5vw, 52px);
            line-height: 1.05;
            letter-spacing: -.04em;
        }

        .final-cta p {
            max-width: 680px;
            margin: 16px 0 22px;
            color: rgba(255, 255, 255, .82);
            font-size: 16px;
            line-height: 1.7;
        }

        .final-cta-action {
            min-height: 50px;
            padding: 0 24px;
            border-radius: 12px;
            background: var(--elive-orange);
            color: #FFFFFF;
        }

        .final-cta-action:hover {
            background: #E67E00;
            transform: translateY(-2px);
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

        @media (prefers-reduced-motion: reduce) {
            html {
                scroll-behavior: auto;
            }

            *,
            *::before,
            *::after {
                animation-duration: .01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: .01ms !important;
            }
        }

        @media (max-width: 900px) {
            .details-grid {
                grid-template-columns: 1fr;
            }

            .info-card {
                position: static;
            }

            .speaker-grid,
            .related-grid,
            .ticket-grid,
            .detail-card-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .gallery-item {
                flex-basis: calc((100% - 20px) / 2);
            }

            .policy-grid .detail-card {
                flex-basis: calc((100% - 20px) / 2);
            }

            .hero-summary {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .hero-stat:nth-child(2) {
                border-right: 0;
            }
        }

        @media (max-width: 760px) {
            .has-mobile-ticket-cta {
                padding-bottom: calc(92px + env(safe-area-inset-bottom));
            }

            .desktop-ticket-cta {
                display: none;
            }

            .mobile-ticket-cta {
                position: fixed;
                z-index: 1200;
                right: 14px;
                bottom: calc(14px + env(safe-area-inset-bottom));
                left: 14px;
                min-height: 56px;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 0 24px;
                border-radius: 14px;
                background: var(--elive-orange);
                color: #FFFFFF;
                font-size: 16px;
                font-weight: 800;
                box-shadow: 0 14px 34px rgba(11, 31, 58, .24);
            }

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

            .public-detail-section {
                padding: 54px 0;
            }

            .ticket-package-grid {
                grid-template-columns: 1fr;
                gap: 18px;
            }

            .ticket-package-header {
                padding: 22px 20px;
            }

            .ticket-package-list {
                padding: 8px 20px 14px;
            }

            .ticket-package-list li {
                padding: 17px 0 17px 28px;
                font-size: 15px;
            }

            .ticket-package-list strong {
                margin-bottom: 6px;
                font-size: 15px;
            }

            .programme-row {
                grid-template-columns: 1fr;
                gap: 8px;
            }

            .faq-item summary {
                grid-template-columns: 30px minmax(0, 1fr) 34px;
                gap: 10px;
                padding: 14px;
            }

            .faq-number {
                width: 28px;
                height: 28px;
            }

            .faq-toggle {
                width: 34px;
                height: 34px;
            }

            .faq-answer {
                margin-inline: 54px 14px;
                padding-bottom: 16px;
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

            .countdown-card {
                padding: 20px 14px;
            }

            .countdown-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .day-row {
                flex-direction: column;
            }

            .day-number {
                min-width: 0;
                text-align: left;
            }

            .speaker-grid,
            .related-grid,
            .ticket-grid,
            .detail-card-grid {
                grid-template-columns: 1fr;
            }

            .gallery-item {
                flex-basis: 100%;
                min-height: 230px;
            }

            .policy-grid .detail-card {
                flex-basis: 100%;
            }

            .gallery-lightbox {
                padding: 12px;
            }

            .gallery-lightbox-image {
                max-width: calc(100vw - 24px);
                max-height: calc(100vh - 170px);
                border-radius: 12px;
            }

            .gallery-lightbox-button {
                width: 42px;
                height: 42px;
            }

            .hero-summary {
                grid-template-columns: 1fr 1fr;
            }

            .hero-stat {
                padding: 16px;
            }

            .hero-actions {
                display: grid;
            }
        }
    </style>
</head>

<body class="{{ $ticketSalesOpen && ! $isPast ? 'has-mobile-ticket-cta' : '' }}">

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
                    Login
                </a>
            </nav>
        </div>

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

                        @if ($event->public_theme)
                            <p class="hero-theme">
                                {{ $event->public_theme }}
                            </p>
                        @endif

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

                        <div class="hero-actions">
                            @if ($ticketSalesOpen && ! $isPast)
                                <a href="{{ $ticketUrl }}" class="hero-action primary desktop-ticket-cta">
                                    Secure Your Seat
                                </a>
                            @elseif ($event->registration_is_open && ! $isPast)
                                <a href="{{ $registerUrl }}" class="hero-action primary">
                                    Register Now
                                </a>
                            @endif
                        </div>

                    </div>

                </div>

                @if (
                    $event->ministry_years
                    || $event->group_members_count
                    || $event->capacity
                    || $publicTicketTypes->isNotEmpty()
                )
                    <div class="hero-summary" aria-label="Event highlights">
                        <div class="hero-stat">
                            <strong>{{ $event->ministry_years ? $event->ministry_years . '+' : '—' }}</strong>
                            <span>Years of Ministry</span>
                        </div>

                        <div class="hero-stat">
                            <strong>{{ $event->group_members_count ?: '—' }}</strong>
                            <span>Group Members</span>
                        </div>

                        <div class="hero-stat">
                            <strong>{{ $seatStatValue ?: '—' }}</strong>
                            <span>{{ $seatStatLabel }}</span>
                        </div>

                        <div class="hero-stat">
                            <strong>{{ $publicTicketTypes->count() ?: '—' }}</strong>
                            <span>Ticket Tiers</span>
                        </div>
                    </div>
                @endif

            </article>

            @if ($scheduleStart && ! $isPast)
                <section
                    class="countdown-card"
                    data-event-countdown
                    data-starts-at="{{ $scheduleStart->toIso8601String() }}"
                    data-ends-at="{{ $scheduleEnd?->toIso8601String() }}"
                    aria-labelledby="countdown-heading"
                >
                    <p id="countdown-heading" class="countdown-eyebrow">
                        Revival begins in
                    </p>

                    <div class="countdown-grid" data-countdown-grid>
                        <div class="countdown-unit">
                            <strong class="countdown-value" data-countdown-days>00</strong>
                            <span class="countdown-label">Days</span>
                        </div>
                        <div class="countdown-unit">
                            <strong class="countdown-value" data-countdown-hours>00</strong>
                            <span class="countdown-label">Hours</span>
                        </div>
                        <div class="countdown-unit">
                            <strong class="countdown-value" data-countdown-minutes>00</strong>
                            <span class="countdown-label">Minutes</span>
                        </div>
                        <div class="countdown-unit">
                            <strong class="countdown-value" data-countdown-seconds>00</strong>
                            <span class="countdown-label">Seconds</span>
                        </div>
                    </div>

                    <p class="countdown-message" data-countdown-message hidden aria-live="polite"></p>
                </section>
            @endif

        </div>
    </section>


    <section id="event-details" class="details-section">
        <div class="container details-grid">

            <article class="content-card event-story-card">

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

                @if ($event->public_theme)
                    <div class="event-theme-callout">
                        <span class="event-theme-label">Event Theme</span>
                        <strong>“{{ $event->public_theme }}”</strong>
                    </div>
                @endif

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


            <aside class="info-card event-info-card">

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

                @if ($isPast)

                    <div class="registration-state">
                        Event Ended
                    </div>

                @elseif ($ticketSalesOpen)

                    <a
                        href="{{ $ticketUrl }}"
                        class="register-btn"
                    >
                        Buy Tickets
                    </a>

                @elseif ($event->registration_is_open)

                    <a
                        href="{{ $registerUrl }}"
                        class="register-btn"
                    >
                        Register for Event
                    </a>

                @elseif ($ticketSalesEnabled)

                    <div class="registration-state">
                        Tickets Closed
                    </div>

                @else

                    <div class="registration-state">
                        Registration Closed
                    </div>

                @endif

            </aside>

        </div>
    </section>

    @if ($event->slug === 'the-revived-way-album-launch-concert')
        <section class="public-detail-section alt" aria-labelledby="ticket-packages-heading">
            <div class="container">
                <p class="public-detail-kicker">More than a ticket</p>
                <h2 id="ticket-packages-heading" class="public-detail-heading">Premium Package Benefits</h2>

                <div class="ticket-package-grid">
                    <article class="ticket-package-card">
                        <header class="ticket-package-header">
                            <span class="ticket-package-label">Elevated concert experience</span>
                            <h3>VIP Experience</h3>
                        </header>

                        <ul class="ticket-package-list">
                            <li><strong>Priority Seating</strong>Reserved front or centre seating for an excellent view and concert experience.</li>
                            <li><strong>Exclusive Welcome Package</strong>A concert booklet, wristband and themed scarf.</li>
                            <li><strong>Meet and Greet</strong>An opportunity to interact with the guest artists before or after the concert.</li>
                            <li><strong>Semi-Luxury Hospitality</strong>Complimentary light snacks and drinks during the concert.</li>
                            <li><strong>Photo Opportunity</strong>Access to the designated photo booth and backdrop, including a photo with Revived Music.</li>
                        </ul>
                    </article>

                    <article class="ticket-package-card vvip">
                        <header class="ticket-package-header">
                            <span class="ticket-package-label">Our most exclusive experience</span>
                            <h3>VVIP Experience</h3>
                        </header>

                        <ul class="ticket-package-list">
                            <li><strong>Front-Row Seating</strong>Premium front-row seating with a personalized name tag.</li>
                            <li><strong>Backstage Access</strong>An exclusive behind-the-scenes tour or prayer session with the singers.</li>
                            <li><strong>Luxury Hospitality</strong>Light snacks and drinks during the concert, followed by a fully catered dinner after the concert.</li>
                            <li><strong>Personalized Gifts</strong>A flash drive with The Revived Way album songs, a customized insulated bottle bearing your name, and a personalized wooden appreciation keepsake.</li>
                            <li><strong>Recognition</strong>Public acknowledgement during the concert by the MC and Revived Music.</li>
                            <li><strong>Photo Opportunity</strong>Access to the designated photo booth and backdrop, including a photo with Revived Music.</li>
                        </ul>
                    </article>
                </div>
            </div>
        </section>
    @endif

    @if ($sessions->isNotEmpty())
        <section class="public-detail-section" aria-labelledby="programme-heading">
            <div class="container">
                <p class="public-detail-kicker">What to expect</p>
                <h2 id="programme-heading" class="public-detail-heading">Event Programme</h2>

                <div class="programme-list">
                    @foreach ($sessions as $session)
                        <article class="programme-row">
                            <div class="programme-time">
                                {{ $session->starts_at?->format('D, g:i A') ?: 'Time TBA' }}
                            </div>

                            <div>
                                <h3>{{ $session->name }}</h3>
                                @if ($session->description)
                                    <p>{{ $session->description }}</p>
                                @endif
                                @if ($session->venue_name)
                                    <p>{{ $session->venue_name }}</p>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($gallery->isNotEmpty())
        <section class="public-detail-section alt" aria-labelledby="gallery-heading">
            <div class="container">
                <p class="public-detail-kicker">Moments</p>
                <h2 id="gallery-heading" class="public-detail-heading">Photo Gallery</h2>

                <div
                    class="gallery-viewport"
                    data-gallery-viewport
                    aria-label="Event photo gallery"
                >
                    <div class="gallery-track">
                    @foreach ($gallery->concat($gallery) as $photo)
                        @php
                            $galleryUrl = Str::startsWith($photo['image_path'], ['http://', 'https://'])
                                ? $photo['image_path']
                                : asset('storage/' . ltrim($photo['image_path'], '/'));
                            $isGalleryClone = $loop->iteration > $gallery->count();
                            $galleryIndex = ($loop->iteration - 1) % $gallery->count();
                        @endphp

                        <a
                            class="gallery-item"
                            href="{{ $galleryUrl }}"
                            rel="noopener"
                            data-gallery-lightbox-trigger
                            data-gallery-index="{{ $galleryIndex }}"
                            data-gallery-caption="{{ $photo['caption'] ?? $event->name }}"
                            @if ($isGalleryClone)
                                data-gallery-clone
                                aria-hidden="true"
                                tabindex="-1"
                            @endif
                        >
                            <img src="{{ $galleryUrl }}" alt="{{ $photo['caption'] ?? $event->name }}" loading="lazy">
                            @if (filled($photo['caption'] ?? null))
                                <span class="gallery-caption">{{ $photo['caption'] }}</span>
                            @endif
                        </a>
                    @endforeach
                    </div>
                </div>
            </div>
        </section>

        <div
            class="gallery-lightbox"
            data-gallery-lightbox-dialog
            role="dialog"
            aria-modal="true"
            aria-label="Photo viewer"
            hidden
        >
            <div class="gallery-lightbox-toolbar">
                <button
                    class="gallery-lightbox-button"
                    type="button"
                    data-lightbox-close
                    aria-label="Close photo viewer"
                >×</button>
            </div>

            <div class="gallery-lightbox-stage" data-lightbox-stage>
                <button
                    class="gallery-lightbox-button gallery-lightbox-previous"
                    type="button"
                    data-lightbox-previous
                    aria-label="Previous photo"
                >‹</button>

                <img class="gallery-lightbox-image" data-lightbox-image src="" alt="">

                <button
                    class="gallery-lightbox-button gallery-lightbox-next"
                    type="button"
                    data-lightbox-next
                    aria-label="Next photo"
                >›</button>
            </div>

            <div class="gallery-lightbox-footer" aria-live="polite">
                <p class="gallery-lightbox-caption" data-lightbox-caption></p>
            </div>
        </div>
    @endif

    @if ($speakers->isNotEmpty())
        <section class="public-detail-section" aria-labelledby="speakers-heading">
            <div class="container">
                <p class="public-detail-kicker">Meet the people</p>
                <h2 id="speakers-heading" class="public-detail-heading">Speakers & Guest Singers</h2>

                <div class="speaker-grid">
                    @foreach ($speakers as $speaker)
                        <article class="speaker-card">
                            @if (filled($speaker['image_path'] ?? null))
                                @php
                                    $speakerUrl = Str::startsWith($speaker['image_path'], ['http://', 'https://'])
                                        ? $speaker['image_path']
                                        : asset('storage/' . ltrim($speaker['image_path'], '/'));
                                @endphp
                                <img class="speaker-photo" src="{{ $speakerUrl }}" alt="{{ $speaker['name'] }}" loading="lazy">
                            @endif

                            <div class="speaker-copy">
                                <h3>{{ $speaker['name'] }}</h3>
                                @if (filled($speaker['role'] ?? null))
                                    <p>{{ $speaker['role'] }}</p>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($policies->isNotEmpty() || $event->venue_address || $event->map_url)
        <section class="public-detail-section alt" aria-labelledby="policies-heading">
            <div class="container">
                <p class="public-detail-kicker">Plan your visit</p>
                <h2 id="policies-heading" class="public-detail-heading">Event Information & Policies</h2>

                <div class="policy-grid">
                    @foreach ($policies as $title => $content)
                        <article class="detail-card">
                            <span class="detail-card-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24">
                                    <circle cx="12" cy="12" r="9"></circle>
                                    <path d="M12 11v5"></path>
                                    <path d="M12 8h.01"></path>
                                </svg>
                            </span>
                            <div class="detail-card-copy">
                                <h3>{{ $title }}</h3>
                                <p>{!! nl2br(e($content)) !!}</p>
                            </div>
                        </article>
                    @endforeach

                    @if ($event->venue_address || $event->map_url)
                        <article class="detail-card">
                            <span class="detail-card-icon" aria-hidden="true">
                                <svg viewBox="0 0 24 24">
                                    <path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"></path>
                                    <circle cx="12" cy="10" r="2.5"></circle>
                                </svg>
                            </span>
                            <div class="detail-card-copy">
                                <h3>Location</h3>
                                @if ($event->venue_address)
                                    <p>{{ $event->venue_address }}</p>
                                @endif
                                @if ($event->map_url)
                                    <div class="share-row">
                                        <a class="share-link" href="{{ $event->map_url }}" target="_blank" rel="noopener">
                                            Open in Google Maps
                                        </a>
                                    </div>
                                @endif
                            </div>
                        </article>
                    @endif
                </div>
            </div>
        </section>
    @endif

    @if ($faqs->isNotEmpty())
        <section class="public-detail-section" aria-labelledby="faq-heading">
            <div class="container">
                <div class="faq-section-header">
                    <p class="public-detail-kicker">Helpful answers</p>
                    <h2 id="faq-heading" class="public-detail-heading">Common Questions</h2>
                </div>

                <div class="faq-list">
                    @foreach ($faqs as $faqIndex => $faq)
                        <details class="faq-item">
                            <summary>
                                <span class="faq-number">{{ str_pad($faqIndex + 1, 2, '0', STR_PAD_LEFT) }}</span>
                                <span class="faq-question">{{ $faq['question'] }}</span>
                                <span class="faq-toggle" aria-hidden="true"></span>
                            </summary>
                            <div class="faq-answer">{!! nl2br(e($faq['answer'])) !!}</div>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="public-detail-section alt" aria-labelledby="organizer-heading">
        <div class="container">
            <p class="public-detail-kicker">Stay connected</p>
            <h2 id="organizer-heading" class="public-detail-heading">Organizer & Sharing</h2>

            <div class="detail-card-grid">
                <article class="detail-card">
                    <h3>{{ $organization?->name ?: 'Event Organizer' }}</h3>
                    @if ($organization?->description)
                        <p>{{ $organization->description }}</p>
                    @endif
                    @if ($contactEmail)
                        <p><a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a></p>
                    @endif
                    @if ($contactPhone)
                        <p><a href="tel:{{ preg_replace('/\s+/', '', $contactPhone) }}">{{ $contactPhone }}</a></p>
                    @endif
                </article>

                <article class="detail-card">
                    <h3>Share this event</h3>
                    <p>Invite friends, family, and your community.</p>
                    <div class="share-row">
                        <a class="share-link" href="https://wa.me/?text={{ rawurlencode($shareText . ' ' . $shareUrl) }}" target="_blank" rel="noopener">WhatsApp</a>
                        <a class="share-link" href="https://www.facebook.com/sharer/sharer.php?u={{ rawurlencode($shareUrl) }}" target="_blank" rel="noopener">Facebook</a>
                        <button class="share-link" type="button" data-copy-event-link="{{ $shareUrl }}">Copy Link</button>
                    </div>
                </article>

                @if ($event->website_url || $event->facebook_url || $event->instagram_url || $event->youtube_url)
                    <article class="detail-card">
                        <h3>Follow the event</h3>
                        <div class="share-row">
                            @foreach (['Website' => $event->website_url, 'Facebook' => $event->facebook_url, 'Instagram' => $event->instagram_url, 'YouTube' => $event->youtube_url] as $label => $url)
                                @if ($url)
                                    <a class="share-link" href="{{ $url }}" target="_blank" rel="noopener">{{ $label }}</a>
                                @endif
                            @endforeach
                        </div>
                    </article>
                @endif
            </div>
        </div>
    </section>

    @if ($relatedEvents->isNotEmpty())
        <section class="public-detail-section" aria-labelledby="related-heading">
            <div class="container">
                <p class="public-detail-kicker">More to discover</p>
                <h2 id="related-heading" class="public-detail-heading">Related Events</h2>

                <div class="related-grid">
                    @foreach ($relatedEvents as $related)
                        <article class="related-card">
                            <h3>{{ $related->name }}</h3>
                            <p>
                                {{ $related->starts_at?->format('d M Y, g:i A') }}
                                @if ($related->venue) · {{ $related->venue }} @endif
                            </p>
                            <a class="public-action secondary" href="{{ route('public.events.show', ['event' => $related->slug]) }}">
                                View Event
                            </a>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="public-detail-section">
        <div class="container">
            <div class="final-cta">
                <h2>{{ $event->final_cta_title ?: 'Secure your place at ' . $event->name }}</h2>
                <p>{{ $event->final_cta_body ?: 'Join us for a memorable event and reserve your place while availability remains.' }}</p>

                @if ($ticketSalesOpen && ! $isPast)
                    <a class="final-cta-action public-action desktop-ticket-cta" href="{{ $ticketUrl }}">Secure Your Seat</a>
                @elseif ($event->registration_is_open && ! $isPast)
                    <a class="final-cta-action public-action" href="{{ $registerUrl }}">Register Now</a>
                @endif
            </div>
        </div>
    </section>

</main>


@if ($ticketSalesOpen && ! $isPast)
    <a
        class="mobile-ticket-cta"
        href="{{ $ticketUrl }}"
    >
        Secure Your Seat
    </a>
@endif

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


<script>
    document.addEventListener('DOMContentLoaded', function () {
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const countdown = document.querySelector('[data-event-countdown]');

        if (countdown) {
            const startTime = new Date(countdown.dataset.startsAt).getTime();
            const endTime = countdown.dataset.endsAt
                ? new Date(countdown.dataset.endsAt).getTime()
                : null;
            const countdownGrid = countdown.querySelector('[data-countdown-grid]');
            const countdownMessage = countdown.querySelector('[data-countdown-message]');
            const fields = {
                days: countdown.querySelector('[data-countdown-days]'),
                hours: countdown.querySelector('[data-countdown-hours]'),
                minutes: countdown.querySelector('[data-countdown-minutes]'),
                seconds: countdown.querySelector('[data-countdown-seconds]'),
            };
            let timer = null;

            const showCountdownMessage = function (message) {
                countdownGrid.hidden = true;
                countdownMessage.textContent = message;
                countdownMessage.hidden = false;
            };

            const updateCountdown = function () {
                const now = Date.now();

                if (!Number.isFinite(startTime)) {
                    showCountdownMessage('Event time will be announced soon');
                    return;
                }

                if (now >= startTime) {
                    showCountdownMessage(
                        endTime && now >= endTime
                            ? 'This event has ended'
                            : 'The event is happening now'
                    );

                    if (timer) {
                        window.clearInterval(timer);
                    }

                    return;
                }

                const remainingSeconds = Math.floor((startTime - now) / 1000);
                const days = Math.floor(remainingSeconds / 86400);
                const hours = Math.floor((remainingSeconds % 86400) / 3600);
                const minutes = Math.floor((remainingSeconds % 3600) / 60);
                const seconds = remainingSeconds % 60;

                fields.days.textContent = String(days).padStart(2, '0');
                fields.hours.textContent = String(hours).padStart(2, '0');
                fields.minutes.textContent = String(minutes).padStart(2, '0');
                fields.seconds.textContent = String(seconds).padStart(2, '0');
            };

            updateCountdown();
            timer = window.setInterval(updateCountdown, 1000);
        }

        document.querySelectorAll('[data-gallery-viewport]').forEach(function (viewport) {
            const track = viewport.querySelector('.gallery-track');
            const firstClone = track?.querySelector('[data-gallery-clone]');

            if (reducedMotion || !track || !firstClone) {
                return;
            }

            let paused = false;
            let previousTime = null;
            const pixelsPerSecond = 32;

            const setPaused = function (value) {
                paused = value;
                previousTime = null;
            };

            viewport.addEventListener('gallery:pause', function () {
                setPaused(true);
            });
            viewport.addEventListener('gallery:resume', function () {
                setPaused(false);
            });

            const animateGallery = function (currentTime) {
                const firstItem = track.firstElementChild;
                const loopWidth = firstItem
                    ? firstClone.offsetLeft - firstItem.offsetLeft
                    : 0;

                if (!paused && previousTime !== null && loopWidth > 0) {
                    viewport.scrollLeft += pixelsPerSecond * ((currentTime - previousTime) / 1000);

                    if (viewport.scrollLeft >= loopWidth) {
                        viewport.scrollLeft -= loopWidth;
                    }
                }

                previousTime = currentTime;
                window.requestAnimationFrame(animateGallery);
            };

            viewport.addEventListener('pointerenter', function () {
                setPaused(true);
            });
            viewport.addEventListener('pointerleave', function () {
                setPaused(false);
            });
            viewport.addEventListener('focusin', function () {
                setPaused(true);
            });
            viewport.addEventListener('focusout', function () {
                setPaused(false);
            });
            viewport.addEventListener('touchstart', function () {
                setPaused(true);
            }, { passive: true });
            viewport.addEventListener('touchend', function () {
                setPaused(false);
            }, { passive: true });

            document.addEventListener('visibilitychange', function () {
                setPaused(document.hidden);
            });

            window.requestAnimationFrame(animateGallery);
        });

        const lightbox = document.querySelector('[data-gallery-lightbox-dialog]');
        const galleryTriggers = Array.from(
            document.querySelectorAll('[data-gallery-lightbox-trigger]')
        );
        const originalGalleryItems = galleryTriggers.filter(function (item) {
            return !item.hasAttribute('data-gallery-clone');
        });

        if (lightbox && originalGalleryItems.length) {
            const lightboxImage = lightbox.querySelector('[data-lightbox-image]');
            const lightboxCaption = lightbox.querySelector('[data-lightbox-caption]');
            const closeButton = lightbox.querySelector('[data-lightbox-close]');
            const previousButton = lightbox.querySelector('[data-lightbox-previous]');
            const nextButton = lightbox.querySelector('[data-lightbox-next]');
            const lightboxStage = lightbox.querySelector('[data-lightbox-stage]');
            let activeIndex = 0;
            let lastFocusedElement = null;
            let touchStartX = null;

            const pauseGallery = function () {
                document.querySelectorAll('[data-gallery-viewport]').forEach(function (viewport) {
                    viewport.dispatchEvent(new Event('gallery:pause'));
                });
            };

            const resumeGallery = function () {
                document.querySelectorAll('[data-gallery-viewport]').forEach(function (viewport) {
                    viewport.dispatchEvent(new Event('gallery:resume'));
                });
            };

            const renderLightboxImage = function () {
                const item = originalGalleryItems[activeIndex];
                const image = item.querySelector('img');

                lightboxImage.src = item.href;
                lightboxImage.alt = image?.alt || item.dataset.galleryCaption || 'Event photo';
                lightboxCaption.textContent = item.dataset.galleryCaption || '';
            };

            const showPhoto = function (direction) {
                activeIndex = (
                    activeIndex + direction + originalGalleryItems.length
                ) % originalGalleryItems.length;
                renderLightboxImage();
            };

            const openLightbox = function (index, trigger) {
                activeIndex = index;
                lastFocusedElement = trigger;
                renderLightboxImage();
                lightbox.hidden = false;
                document.body.classList.add('gallery-lightbox-open');
                pauseGallery();
                closeButton.focus();
            };

            const closeLightbox = function () {
                if (lightbox.hidden) {
                    return;
                }

                lightbox.hidden = true;
                lightboxImage.src = '';
                document.body.classList.remove('gallery-lightbox-open');
                resumeGallery();
                lastFocusedElement?.focus();
            };

            galleryTriggers.forEach(function (trigger) {
                trigger.addEventListener('click', function (event) {
                    event.preventDefault();
                    openLightbox(Number(trigger.dataset.galleryIndex) || 0, trigger);
                });
            });

            closeButton.addEventListener('click', closeLightbox);
            previousButton.addEventListener('click', function () {
                showPhoto(-1);
            });
            nextButton.addEventListener('click', function () {
                showPhoto(1);
            });

            lightbox.addEventListener('click', function (event) {
                if (event.target === lightbox) {
                    closeLightbox();
                }
            });

            document.addEventListener('keydown', function (event) {
                if (lightbox.hidden) {
                    return;
                }

                if (event.key === 'Escape') {
                    closeLightbox();
                } else if (event.key === 'ArrowLeft') {
                    showPhoto(-1);
                } else if (event.key === 'ArrowRight') {
                    showPhoto(1);
                }
            });

            lightboxStage.addEventListener('touchstart', function (event) {
                touchStartX = event.changedTouches[0]?.clientX ?? null;
            }, { passive: true });

            lightboxStage.addEventListener('touchend', function (event) {
                if (touchStartX === null) {
                    return;
                }

                const touchEndX = event.changedTouches[0]?.clientX ?? touchStartX;
                const distance = touchEndX - touchStartX;
                touchStartX = null;

                if (Math.abs(distance) < 45) {
                    return;
                }

                showPhoto(distance > 0 ? -1 : 1);
            }, { passive: true });
        }

        const faqItems = Array.from(document.querySelectorAll('.faq-item'));

        faqItems.forEach(function (item) {
            item.addEventListener('toggle', function () {
                if (!item.open) {
                    return;
                }

                faqItems.forEach(function (otherItem) {
                    if (otherItem !== item && otherItem.open) {
                        otherItem.removeAttribute('open');
                    }
                });
            });
        });

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

        document.querySelectorAll('[data-copy-event-link]').forEach(function (button) {
            button.addEventListener('click', async function () {
                try {
                    await navigator.clipboard.writeText(button.dataset.copyEventLink);
                    button.textContent = 'Copied';
                    window.setTimeout(function () {
                        button.textContent = 'Copy Link';
                    }, 1800);
                } catch (error) {
                    window.prompt('Copy this event link:', button.dataset.copyEventLink);
                }
            });
        });
    });
</script>

</body>
</html>
