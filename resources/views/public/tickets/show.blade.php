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

        .countdown-card {
            margin-top: 22px;
            padding: 24px;
            border: 1px solid rgba(35, 63, 126, .12);
            border-radius: 18px;
            background: linear-gradient(135deg, var(--elive-navy), var(--elive-blue));
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
            background: linear-gradient(135deg, #FFFFFF, #F8FAFC);
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

        .gallery-grid,
        .speaker-grid,
        .related-grid,
        .ticket-grid,
        .detail-card-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
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

        .ticket-tier,
        .detail-card,
        .related-card {
            padding: 26px;
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

        .programme-list,
        .faq-list {
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

        .faq-item {
            border: 1px solid var(--elive-border);
            border-radius: 15px;
            background: #FFFFFF;
        }

        .faq-item summary {
            padding: 20px 22px;
            color: var(--elive-navy);
            font-weight: 800;
            cursor: pointer;
        }

        .faq-answer {
            padding: 0 22px 22px;
            color: var(--elive-muted);
            line-height: 1.7;
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
            padding: clamp(34px, 6vw, 68px);
            border-radius: 28px;
            background:
                radial-gradient(circle at 90% 10%, rgba(255, 152, 0, .40), transparent 34%),
                linear-gradient(135deg, var(--elive-navy), #242C74 56%, var(--elive-blue));
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
            margin: 18px 0 26px;
            color: rgba(255, 255, 255, .82);
            font-size: 16px;
            line-height: 1.7;
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

        @media (max-width: 900px) {
            .details-grid {
                grid-template-columns: 1fr;
            }

            .info-card {
                position: static;
            }

            .gallery-grid,
            .speaker-grid,
            .related-grid,
            .ticket-grid,
            .detail-card-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .hero-summary {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .hero-stat:nth-child(2) {
                border-right: 0;
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

            .programme-row {
                grid-template-columns: 1fr;
                gap: 8px;
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

            .gallery-grid,
            .speaker-grid,
            .related-grid,
            .ticket-grid,
            .detail-card-grid {
                grid-template-columns: 1fr;
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
                                <a href="{{ $ticketUrl }}" class="hero-action primary">
                                    Secure Your Seat
                                </a>
                            @elseif ($event->registration_is_open && ! $isPast)
                                <a href="{{ $registerUrl }}" class="hero-action primary">
                                    Register Now
                                </a>
                            @endif

                            <a href="#event-details" class="hero-action">
                                Explore Event
                            </a>
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

    @if ($publicTicketTypes->isNotEmpty())
        <section id="tickets" class="public-detail-section alt" aria-labelledby="tickets-heading">
            <div class="container">
                <p class="public-detail-kicker">Choose your experience</p>
                <h2 id="tickets-heading" class="public-detail-heading">Ticket Options</h2>

                <div class="ticket-grid">
                    @foreach ($publicTicketTypes as $ticketType)
                        @php
                            $remaining = $ticketType->remainingCapacity();
                            $soldOut = $remaining !== null && $remaining <= 0;
                            $lowStock = $remaining !== null
                                && $remaining > 0
                                && $remaining <= max(5, (int) ceil($ticketType->capacity * .1));
                            $tierSalesOpen = $ticketSalesOpen
                                && (! $ticketType->sales_start_at || $ticketType->sales_start_at->lte(now()))
                                && (! $ticketType->sales_end_at || $ticketType->sales_end_at->gte(now()));
                        @endphp

                        <article class="ticket-tier">
                            <h3>{{ $ticketType->name }}</h3>

                            <div class="ticket-price">
                                {{ $ticketType->price > 0
                                    ? $ticketType->currency . ' ' . number_format((float) $ticketType->price)
                                    : 'Free' }}
                            </div>

                            @if ($ticketType->description)
                                <p class="ticket-copy">{{ $ticketType->description }}</p>
                            @endif

                            <p class="ticket-stock {{ $soldOut ? 'stock-out' : ($lowStock ? 'stock-low' : 'stock-ok') }}">
                                {{ $soldOut
                                    ? 'Sold Out'
                                    : ($remaining === null ? 'Available' : number_format($remaining) . ' remaining') }}
                            </p>

                            @if ($tierSalesOpen && ! $soldOut)
                                <a class="public-action" href="{{ $ticketUrl }}">Buy Tickets</a>
                            @elseif (! $soldOut)
                                <span class="ticket-stock stock-out">Sales Closed</span>
                            @endif
                        </article>
                    @endforeach
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

                <div class="gallery-grid">
                    @foreach ($gallery as $photo)
                        @php
                            $galleryUrl = Str::startsWith($photo['image_path'], ['http://', 'https://'])
                                ? $photo['image_path']
                                : asset('storage/' . ltrim($photo['image_path'], '/'));
                        @endphp

                        <a class="gallery-item" href="{{ $galleryUrl }}" target="_blank" rel="noopener">
                            <img src="{{ $galleryUrl }}" alt="{{ $photo['caption'] ?? $event->name }}" loading="lazy">
                            @if (filled($photo['caption'] ?? null))
                                <span class="gallery-caption">{{ $photo['caption'] }}</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if ($speakers->isNotEmpty())
        <section class="public-detail-section" aria-labelledby="speakers-heading">
            <div class="container">
                <p class="public-detail-kicker">Meet the people</p>
                <h2 id="speakers-heading" class="public-detail-heading">Speakers & Performers</h2>

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

                <div class="detail-card-grid">
                    @foreach ($policies as $title => $content)
                        <article class="detail-card">
                            <h3>{{ $title }}</h3>
                            <p>{!! nl2br(e($content)) !!}</p>
                        </article>
                    @endforeach

                    @if ($event->venue_address || $event->map_url)
                        <article class="detail-card">
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
                        </article>
                    @endif
                </div>
            </div>
        </section>
    @endif

    @if ($faqs->isNotEmpty())
        <section class="public-detail-section" aria-labelledby="faq-heading">
            <div class="container">
                <p class="public-detail-kicker">Helpful answers</p>
                <h2 id="faq-heading" class="public-detail-heading">Common Questions</h2>

                <div class="faq-list">
                    @foreach ($faqs as $faq)
                        <details class="faq-item">
                            <summary>{{ $faq['question'] }}</summary>
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
                    <a class="public-action" href="{{ $ticketUrl }}">Secure Your Seat</a>
                @elseif ($event->registration_is_open && ! $isPast)
                    <a class="public-action" href="{{ $registerUrl }}">Register Now</a>
                @endif
            </div>
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


<script>
    document.addEventListener('DOMContentLoaded', function () {
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
