<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $event->registration_welcome_title ?: 'Register for ' . $event->name }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        :root {
            --elive-primary: {{ $branding['primary_color'] }};
            --elive-button: {{ $branding['button_color'] }};
            --elive-bg: {{ $branding['background_color'] }};
            --elive-text: #0f172a;
            --elive-muted: #64748b;
            --elive-border: #e2e8f0;
            --elive-soft: #f8fafc;
            --elive-danger: #dc2626;
            --elive-radius-lg: 24px;
            --elive-radius-md: 18px;
            --elive-radius-sm: 12px;
            --elive-shadow: 0 24px 60px rgba(15, 23, 42, 0.12);
            --elive-shadow-soft: 0 10px 26px rgba(15, 23, 42, 0.07);
        }

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            min-height: 100vh;
            background:
                radial-gradient(circle at top left, color-mix(in srgb, var(--elive-primary) 12%, transparent), transparent 34%),
                linear-gradient(180deg, #f8fafc 0%, var(--elive-bg) 42%, #eef2f7 100%) !important;
            color: var(--elive-text) !important;
            font-family:
                Inter,
                ui-sans-serif,
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif !important;
            -webkit-font-smoothing: antialiased;
            text-rendering: optimizeLegibility;
        }

        .elive-page {
            min-height: 100vh;
            padding: 36px 18px 28px !important;
        }

        .elive-container {
            width: min(100%, 1040px);
            margin: 0 auto !important;
        }

        .elive-shell {
            background: rgba(255, 255, 255, 0.96) !important;
            border: 1px solid rgba(226, 232, 240, 0.95) !important;
            border-radius: 28px !important;
            box-shadow: var(--elive-shadow) !important;
            backdrop-filter: blur(12px);
        }

        .elive-banner {
            position: relative;
            width: 100%;
            height: 220px;
            overflow: hidden;
            background: var(--elive-primary);
        }

        .elive-banner img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: 45% center;
        }

        .elive-banner::after {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(
                180deg,
                rgba(15, 23, 42, 0.00) 68%,
                rgba(15, 23, 42, 0.08) 100%
            );
            pointer-events: none;
        }

        .elive-content {
            padding: 34px !important;
        }

        .elive-heading {
            display: flex !important;
            align-items: center !important;
            gap: 18px !important;
            padding-bottom: 6px;
        }

        .elive-heading img {
            width: 78px !important;
            height: 78px !important;
            border-radius: 20px !important;
            border: 1px solid var(--elive-border) !important;
            box-shadow: 0 8px 24px rgba(15, 23, 42, 0.08);
        }

        .elive-title {
            font-size: clamp(28px, 4vw, 42px) !important;
            letter-spacing: -0.03em;
            color: var(--elive-primary) !important;
        }

        .elive-description {
            max-width: 760px;
            margin-top: 10px !important;
            color: var(--elive-muted) !important;
            font-size: 15px !important;
            line-height: 1.7;
        }

        .event-summary {
            margin-top: 26px !important;
            gap: 14px !important;
        }

        .event-summary > div {
            position: relative;
            overflow: hidden;
            min-height: 88px;
            padding: 16px !important;
            background:
                linear-gradient(145deg, #ffffff 0%, #f8fafc 100%) !important;
            border: 1px solid var(--elive-border) !important;
            border-radius: 18px !important;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.05);
        }

        .event-summary > div::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--elive-primary);
            opacity: 0.85;
        }

        .event-summary > div > div:first-child {
            color: var(--elive-muted) !important;
            letter-spacing: 0.08em;
            font-size: 10px !important;
        }

        .event-summary > div > div:last-child {
            margin-top: 8px !important;
            color: var(--elive-text);
            font-size: 15px !important;
            line-height: 1.45;
        }

        form {
            margin-top: 30px !important;
        }

        form > div {
            margin-top: 20px !important;
            padding: 24px !important;
            background: #ffffff !important;
            border: 1px solid var(--elive-border) !important;
            border-radius: 22px !important;
            box-shadow: var(--elive-shadow-soft) !important;
            transition:
                transform 160ms ease,
                box-shadow 160ms ease,
                border-color 160ms ease;
        }

        form > div:first-of-type {
            margin-top: 0 !important;
        }

        form > div:hover {
            border-color: color-mix(in srgb, var(--elive-primary) 28%, var(--elive-border)) !important;
            box-shadow: 0 14px 34px rgba(15, 23, 42, 0.09) !important;
        }

        form h2 {
            position: relative;
            margin: 0 0 20px !important;
            padding-left: 16px;
            color: var(--elive-primary) !important;
            font-size: 21px !important;
            letter-spacing: -0.02em;
        }

        form h2::before {
            content: "";
            position: absolute;
            top: 3px;
            bottom: 3px;
            left: 0;
            width: 5px;
            border-radius: 999px;
            background: var(--elive-primary);
        }

        form label {
            color: #1e293b;
            font-size: 13px;
            line-height: 1.45;
        }

        form input:not([type="checkbox"]):not([type="radio"]):not([type="hidden"]),
        form select,
        form textarea {
            min-height: 46px;
            width: 100%;
            border: 1px solid #cbd5e1 !important;
            border-radius: 13px !important;
            background: #ffffff !important;
            color: var(--elive-text);
            font: inherit;
            outline: none;
            transition:
                border-color 150ms ease,
                box-shadow 150ms ease,
                background 150ms ease;
        }

        form textarea {
            min-height: 116px;
            resize: vertical;
        }

        form input:not([type="checkbox"]):not([type="radio"]):focus,
        form select:focus,
        form textarea:focus {
            border-color: var(--elive-primary) !important;
            box-shadow: 0 0 0 4px color-mix(in srgb, var(--elive-primary) 15%, transparent);
            background: #ffffff !important;
        }

        form input::placeholder,
        form textarea::placeholder {
            color: #94a3b8;
        }

        form input[type="checkbox"],
        form input[type="radio"] {
            accent-color: var(--elive-primary);
        }

        .elive-field-invalid input,
        .elive-field-invalid select,
        .elive-field-invalid textarea {
            border-color: #dc2626 !important;
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.08);
        }

        .elive-field-invalid label {
            color: #991b1b;
        }

        .elive-validation-summary {
            margin-top: 22px;
            background: #fff7f7;
            color: #991b1b;
            border: 1px solid #fecaca;
            border-radius: 14px;
            padding: 14px 16px;
            font-weight: 700;
        }

        .elive-validation-summary ul {
            margin: 8px 0 0;
            padding-left: 18px;
        }

        .elive-session-day-group {
            display: grid;
            gap: 12px;
        }

        .elive-session-day-group[hidden] {
            display: none !important;
        }

        .elive-session-day-title {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 12px 14px;
            border-radius: 14px;
            background: color-mix(in srgb, var(--elive-primary) 7%, #ffffff);
            border: 1px solid color-mix(in srgb, var(--elive-primary) 20%, var(--elive-border));
        }

        .elive-session-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 12px;
        }

        .elive-session-card {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 16px;
            border: 1px solid var(--elive-border);
            border-radius: 16px;
            background: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
            cursor: pointer;
            transition:
                border-color 150ms ease,
                transform 150ms ease,
                box-shadow 150ms ease,
                opacity 150ms ease;
        }

        .elive-session-card:hover {
            transform: translateY(-1px);
            border-color: color-mix(in srgb, var(--elive-primary) 30%, var(--elive-border));
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.07);
        }

        .elive-session-card[data-full="1"] {
            cursor: not-allowed;
            opacity: 0.62;
        }

        .elive-session-card input {
            width: 19px;
            height: 19px;
            margin-top: 2px;
            flex: 0 0 auto;
        }

        .elive-session-type {
            display: inline-flex;
            align-items: center;
            margin-top: 7px;
            padding: 4px 8px;
            border-radius: 999px;
            background: #eef2ff;
            color: #3730a3;
            font-size: 10px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .elive-session-meta {
            display: block;
            margin-top: 5px;
            color: #64748b;
            font-size: 12px;
            line-height: 1.5;
        }

        .elive-session-capacity {
            display: block;
            margin-top: 7px;
            color: #475569;
            font-size: 11px;
            font-weight: 800;
        }

        .elive-session-empty {
            padding: 16px;
            border: 1px dashed var(--elive-border);
            border-radius: 14px;
            color: #64748b;
            font-size: 13px;
            text-align: center;
        }

        [data-merchandise-card] {
            background:
                linear-gradient(145deg, #ffffff 0%, #f8fafc 100%) !important;
            border: 1px solid var(--elive-border) !important;
            border-radius: 18px !important;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.05);
            transition:
                border-color 150ms ease,
                transform 150ms ease,
                box-shadow 150ms ease;
        }

        [data-merchandise-card]:hover {
            transform: translateY(-1px);
            border-color: color-mix(in srgb, var(--elive-primary) 30%, var(--elive-border)) !important;
            box-shadow: 0 12px 26px rgba(15, 23, 42, 0.08);
        }

        [data-merchandise-card] img {
            box-shadow: 0 8px 18px rgba(15, 23, 42, 0.08);
        }

        [data-payment-section] {
            background:
                linear-gradient(
                    145deg,
                    color-mix(in srgb, var(--elive-primary) 5%, #ffffff),
                    #ffffff
                ) !important;
        }

        [data-submit-button] {
            min-height: 54px;
            margin-top: 22px !important;
            border-radius: 15px !important;
            background:
                linear-gradient(
                    135deg,
                    var(--elive-button),
                    color-mix(in srgb, var(--elive-button) 78%, #0f172a)
                ) !important;
            box-shadow: 0 12px 26px color-mix(in srgb, var(--elive-button) 28%, transparent);
            letter-spacing: 0.01em;
            transition:
                transform 150ms ease,
                box-shadow 150ms ease,
                opacity 150ms ease;
        }

        [data-submit-button]:hover:not(:disabled) {
            transform: translateY(-1px);
            box-shadow: 0 16px 32px color-mix(in srgb, var(--elive-button) 34%, transparent);
        }

        [data-submit-button]:active:not(:disabled) {
            transform: translateY(0);
        }

        .elive-footer {
            padding: 8px 12px 0;
            color: #64748b !important;
            font-size: 12px !important;
            line-height: 1.7;
        }

        @media (max-width: 720px) {
            .elive-page {
                padding: 14px 10px 22px !important;
            }

            .elive-shell {
                border-radius: 20px !important;
            }

            .elive-banner {
                height: 150px;
            }

            .elive-banner img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                object-position: 45% center;
            }

            .elive-content {
                padding: 20px 16px !important;
            }

            .elive-heading {
                align-items: flex-start !important;
            }

            .elive-heading img {
                width: 62px !important;
                height: 62px !important;
            }

            .elive-title {
                font-size: 27px !important;
            }

            .event-summary {
                grid-template-columns: 1fr !important;
                gap: 10px !important;
                margin-top: 20px !important;
            }

            .event-summary > div {
                min-height: 0 !important;
                padding: 13px 14px !important;
                border-radius: 15px !important;
            }

            .event-summary > div > div:first-child {
                font-size: 9px !important;
            }

            .event-summary > div > div:last-child {
                margin-top: 5px !important;
                font-size: 14px !important;
                line-height: 1.35;
            }

            form > div {
                padding: 18px !important;
                border-radius: 18px !important;
            }

            form h2 {
                font-size: 19px !important;
            }

            [data-merchandise-card] {
                padding: 14px !important;
            }

            [data-merchandise-card] img {
                width: 86px !important;
                height: 86px !important;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                scroll-behavior: auto !important;
                transition: none !important;
            }
        }
    </style>

</head>

<body style="
    margin: 0;
    background: {{ $branding['background_color'] }};
    color: #0f172a;
    font-family: Arial, sans-serif;
">
    <div class="elive-page" style="min-height: 100vh; padding: 28px 16px;">
        <div class="elive-container" style="max-width: 980px; margin: 0 auto;">
            <div class="elive-shell" style="
                background: #ffffff;
                border-radius: 24px;
                overflow: hidden;
                box-shadow: 0 16px 35px rgba(15, 23, 42, 0.12);
                border: 1px solid #e5e7eb;
            ">
                @if ($branding['banner'])
                    <div class="elive-banner">
                        <img
                            src="{{ asset('storage/' . $branding['banner']) }}"
                            alt="{{ $event->name }} banner"
                            loading="eager"
                            decoding="async"
                        >
                    </div>
                @else
                    <div
                        class="elive-banner"
                        style="
                            height: 16px;
                            background: {{ $branding['primary_color'] }};
                        "
                    ></div>
                @endif

                <div class="elive-content" style="padding: 28px;">
                    <div class="elive-heading" style="display: flex; gap: 18px; align-items: center; flex-wrap: wrap;">
                        @if ($branding['logo'])
                            <img
                                src="{{ asset('storage/' . $branding['logo']) }}"
                                alt="Logo"
                                style="
                                    width: 72px;
                                    height: 72px;
                                    object-fit: contain;
                                    border-radius: 16px;
                                    border: 1px solid #e5e7eb;
                                    padding: 8px;
                                    background: white;
                                "
                            >
                        @endif

                        <div style="flex: 1; min-width: 260px;">
                            <h1 class="elive-title" style="
                                margin: 0;
                                color: {{ $branding['primary_color'] }};
                                font-size: 30px;
                                line-height: 1.2;
                                font-weight: 900;
                            ">
                                {{ $event->registration_welcome_title ?: 'Register for ' . $event->name }}
                            </h1>

                            <p class="elive-description" style="margin: 8px 0 0 0; color: #64748b; font-size: 15px;">
                                {{ $event->registration_welcome_message ?: 'Complete the form below to register for this event.' }}
                            </p>
                        </div>
                    </div>

                    <div class="event-summary" style="
                        margin-top: 24px;
                        display: grid;
                        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                        gap: 12px;
                    ">
                        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;padding:14px;">
                            <div style="font-size:11px;font-weight:800;text-transform:uppercase;color:#64748b;">Event</div>
                            <div style="font-size:15px;font-weight:800;margin-top:5px;">{{ $event->name }}</div>
                        </div>

                        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;padding:14px;">
                            <div style="font-size:11px;font-weight:800;text-transform:uppercase;color:#64748b;">Venue</div>
                            <div style="font-size:15px;font-weight:800;margin-top:5px;">{{ $event->venue ?: 'To be announced' }}</div>
                        </div>

                        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;padding:14px;">
                            <div style="font-size:11px;font-weight:800;text-transform:uppercase;color:#64748b;">Date</div>
                            <div style="font-size:15px;font-weight:800;margin-top:5px;">
                                @if ($event->starts_at && $event->ends_at)
                                    @if ($event->starts_at->isSameDay($event->ends_at))
                                        {{ $event->starts_at->format('d M Y, H:i') }}
                                        – {{ $event->ends_at->format('H:i') }}
                                    @else
                                        {{ $event->starts_at->format('d M Y') }}
                                        – {{ $event->ends_at->format('d M Y') }}
                                    @endif
                                @elseif ($event->starts_at)
                                    {{ $event->starts_at->format('d M Y, H:i') }}
                                @else
                                    To be announced
                                @endif
                            </div>
                        </div>

                    </div>

                    @if (session('error'))
                        <div style="
                            margin-top: 22px;
                            background: #fee2e2;
                            color: #991b1b;
                            border: 1px solid #fecaca;
                            border-radius: 14px;
                            padding: 14px;
                            font-weight: 700;
                        ">
                            {{ session('error') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="elive-validation-summary" role="alert">
                            Please review the highlighted field{{ $errors->count() === 1 ? '' : 's' }} below.
                            <ul>
                                @foreach ($errors->all() as $message)
                                    <li>{{ $message }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if (! $isOpen)
                        <div style="
                            margin-top: 24px;
                            background: #fff7ed;
                            color: #9a3412;
                            border: 1px solid #fed7aa;
                            border-radius: 18px;
                            padding: 20px;
                        ">
                            <h2 style="margin:0;font-size:20px;font-weight:900;">Registration Closed</h2>
                            <p style="margin:8px 0 0 0;">Public registration for this event is currently closed.</p>
                        </div>
                    @elseif ($isFull && ! $waitlistEnabled)
                        <div style="
                            margin-top: 24px;
                            background: #fee2e2;
                            color: #991b1b;
                            border: 1px solid #fecaca;
                            border-radius: 18px;
                            padding: 20px;
                        ">
                            <h2 style="margin:0;font-size:20px;font-weight:900;">Registration Full</h2>

                            <p style="margin:8px 0 0 0;">
                                {{ $event->registration_waitlist_message ?: 'This event has reached its registration capacity.' }}
                            </p>

                        </div>
                    @else
                        @if ($isFull && $waitlistEnabled)
                            <div style="
                                margin-top: 24px;
                                background: #fff7ed;
                                color: #9a3412;
                                border: 1px solid #fed7aa;
                                border-radius: 18px;
                                padding: 20px;
                            ">
                                <h2 style="margin:0;font-size:20px;font-weight:900;">Join Waitlist</h2>
                                <p style="margin:8px 0 0 0;">
                                    {{ $event->registration_waitlist_message ?: 'The event is full, but you can still join the waitlist.' }}
                                </p>

                                @if (! empty($registrationStats['waitlisted']))
                                    <p style="margin:8px 0 0 0;font-weight:800;">
                                        Waitlisted: {{ $registrationStats['waitlisted'] }}
                                    </p>
                                @endif
                            </div>
                        @endif

                        <form method="POST" action="{{ route('public.registration.store', $event) }}" data-registration-form style="margin-top: 28px;">
                            @csrf

                            @php
                                /*
                                 * Multi-purpose standard field configuration.
                                 *
                                 * These values are read from the Event model when the
                                 * corresponding database columns exist. Safe defaults
                                 * preserve the current registration experience.
                                 */
                                $standardFields = [
                                    'phone' => [
                                        'show' => (bool) ($event->registration_show_phone ?? true),
                                        'required' => (bool) ($event->registration_require_phone ?? true),
                                    ],
                                    'email' => [
                                        'show' => (bool) ($event->registration_show_email ?? true),
                                        'required' => (bool) ($event->registration_require_email ?? false),
                                    ],
                                    'organization' => [
                                        'show' => (bool) ($event->registration_show_organization ?? true),
                                        'required' => (bool) ($event->registration_require_organization ?? false),
                                    ],
                                    'position' => [
                                        'show' => (bool) ($event->registration_show_position ?? true),
                                        'required' => (bool) ($event->registration_require_position ?? false),
                                    ],
                                    'category' => [
                                        'show' => (bool) ($event->registration_show_category ?? true),
                                        'required' => (bool) ($event->registration_require_category ?? false),
                                    ],
                                    'badge_type' => [
                                        'show' => (bool) ($event->registration_show_badge_type ?? false),
                                        'required' => (bool) ($event->registration_require_badge_type ?? false),
                                    ],
                                ];

                                $allowDaySelection = (bool) (
                                    $allowDaySelection
                                    ?? $event->allowsDaySelection()
                                );

                                $allowAllDaysSelection = (bool) (
                                    $allowAllDaysSelection
                                    ?? $event->allowsAllDaysSelection()
                                );

                                $allowSessionRegistration = (bool) (
                                    $allowSessionRegistration
                                    ?? $event->allowsSessionRegistration()
                                );

                                $sectionLabels = $registrationSectionLabels
                                    ?? $event->registrationSectionLabels();

                                $availableCategories = collect($categories ?? []);
                                $availableBadgeTypes = collect($badgeTypes ?? []);

                                $isChurchLikeEvent = in_array(
                                    $event->event_type,
                                    ['church_event', 'community_event', 'charity_event'],
                                    true
                                );

                                $organizationFieldLabel = $isChurchLikeEvent
                                    ? 'Church / Congregation'
                                    : 'Organization / Company';

                                $positionFieldLabel = $isChurchLikeEvent
                                    ? 'Church Position / Responsibility'
                                    : 'Position / Title';

                                $categoryFieldLabel = $isChurchLikeEvent
                                    ? 'Participant Type'
                                    : 'Attendee Category';
                            @endphp

                            @if ($isFull && $waitlistEnabled)
                                <input type="hidden" name="join_waitlist" value="1">
                            @endif

                            <div style="
                                background: #ffffff;
                                border: 1px solid #e2e8f0;
                                border-radius: 20px;
                                padding: 22px;
                                box-shadow: 0 8px 22px rgba(15,23,42,0.06);
                            ">
                                <h2 style="
                                    margin: 0 0 18px 0;
                                    color: {{ $branding['primary_color'] }};
                                    font-size: 22px;
                                    font-weight: 900;
                                ">
                                    {{ $sectionLabels['personal'] ?? 'Personal Details' }}
                                </h2>

                                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;">
                                    <div id="field-full-name" class="@error('full_name') elive-field-invalid @enderror">
                                        <label style="display:block;font-weight:800;margin-bottom:7px;">Full Name *</label>
                                        <input
                                            name="full_name"
                                            value="{{ old('full_name') }}"
                                            required
                                            autocomplete="name"
                                            style="width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:14px;padding:12px;font-size:15px;"
                                        >
                                        @error('full_name')
                                            <div style="font-size:12px;color:#dc2626;margin-top:6px;font-weight:700;">
                                                {{ $message }}
                                            </div>
                                        @enderror
                                    </div>

                                    @if ($standardFields['phone']['show'])
                                        <div id="field-phone" class="@error('phone') elive-field-invalid @enderror">
                                            <label style="display:block;font-weight:800;margin-bottom:7px;">
                                                Phone Number
                                                @if ($standardFields['phone']['required'])
                                                    <span style="color:#dc2626;">*</span>
                                                @endif
                                            </label>
                                            <input
                                                type="tel"
                                                name="phone"
                                                value="{{ old('phone') }}"
                                                placeholder="255712345678"
                                                inputmode="tel"
                                                autocomplete="tel"
                                                @required($standardFields['phone']['required'])
                                                style="width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:14px;padding:12px;font-size:15px;"
                                            >
                                            @error('phone')
                                                <div style="font-size:12px;color:#dc2626;margin-top:6px;font-weight:700;">
                                                    {{ $message }}
                                                </div>
                                            @enderror
                                        </div>
                                    @endif

                                    @if ($standardFields['email']['show'])
                                        <div id="field-email" class="@error('email') elive-field-invalid @enderror">
                                            <label style="display:block;font-weight:800;margin-bottom:7px;">
                                                Email Address
                                                @if ($standardFields['email']['required'])
                                                    <span style="color:#dc2626;">*</span>
                                                @endif
                                            </label>
                                            <input
                                                type="email"
                                                name="email"
                                                value="{{ old('email') }}"
                                                autocomplete="email"
                                                @required($standardFields['email']['required'])
                                                style="width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:14px;padding:12px;font-size:15px;"
                                            >
                                            @error('email')
                                                <div style="font-size:12px;color:#dc2626;margin-top:6px;font-weight:700;">
                                                    {{ $message }}
                                                </div>
                                            @enderror
                                        </div>
                                    @endif
                                </div>
                            </div>

                            @php
                                $showOptionalStandardDetails =
                                    $standardFields['organization']['show']
                                    || $standardFields['position']['show']
                                    || $standardFields['category']['show']
                                    || $standardFields['badge_type']['show'];
                            @endphp

                            @if ($showOptionalStandardDetails)
                                <div style="
                                    margin-top:22px;
                                    background:#ffffff;
                                    border:1px solid #e2e8f0;
                                    border-radius:20px;
                                    padding:22px;
                                    box-shadow:0 8px 22px rgba(15,23,42,0.06);
                                ">
                                    <h2 style="
                                        margin:0 0 18px 0;
                                        color:{{ $branding['primary_color'] }};
                                        font-size:22px;
                                        font-weight:900;
                                    ">
                                        {{ $sectionLabels['professional'] ?? 'Professional / Registration Details' }}
                                    </h2>

                                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;">
                                        @if ($standardFields['organization']['show'])
                                            <div id="field-organization-name" class="@error('organization_name') elive-field-invalid @enderror">
                                                <label style="display:block;font-weight:800;margin-bottom:7px;">
                                                    {{ $organizationFieldLabel }}
                                                    @if ($standardFields['organization']['required'])
                                                        <span style="color:#dc2626;">*</span>
                                                    @endif
                                                </label>
                                                <input
                                                    name="organization_name"
                                                    value="{{ old('organization_name') }}"
                                                    @required($standardFields['organization']['required'])
                                                    style="width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:14px;padding:12px;font-size:15px;"
                                                >
                                                @error('organization_name')
                                                    <div style="font-size:12px;color:#dc2626;margin-top:6px;font-weight:700;">
                                                        {{ $message }}
                                                    </div>
                                                @enderror
                                            </div>
                                        @endif

                                        @if ($standardFields['position']['show'])
                                            <div id="field-position" class="@error('position') elive-field-invalid @enderror">
                                                <label style="display:block;font-weight:800;margin-bottom:7px;">
                                                    {{ $positionFieldLabel }}
                                                    @if ($standardFields['position']['required'])
                                                        <span style="color:#dc2626;">*</span>
                                                    @endif
                                                </label>
                                                <input
                                                    name="position"
                                                    value="{{ old('position') }}"
                                                    @required($standardFields['position']['required'])
                                                    style="width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:14px;padding:12px;font-size:15px;"
                                                >
                                                @error('position')
                                                    <div style="font-size:12px;color:#dc2626;margin-top:6px;font-weight:700;">
                                                        {{ $message }}
                                                    </div>
                                                @enderror
                                            </div>
                                        @endif

                                        @if ($standardFields['category']['show'])
                                            <div id="field-category-id" class="@error('category_id') elive-field-invalid @enderror">
                                                <label style="display:block;font-weight:800;margin-bottom:7px;">
                                                    {{ $categoryFieldLabel }}
                                                    @if ($standardFields['category']['required'])
                                                        <span style="color:#dc2626;">*</span>
                                                    @endif
                                                </label>

                                                @if ($availableCategories->isNotEmpty())
                                                    <select
                                                        name="category_id"
                                                        @required($standardFields['category']['required'])
                                                        style="width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:14px;padding:12px;font-size:15px;background:white;"
                                                    >
                                                        <option value="">Select {{ strtolower($categoryFieldLabel) }}</option>

                                                        @foreach ($availableCategories as $category)
                                                            <option
                                                                value="{{ $category->id }}"
                                                                @selected((string) old('category_id') === (string) $category->id)
                                                            >
                                                                {{ $category->name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                @else
                                                    <select
                                                        disabled
                                                        style="width:100%;box-sizing:border-box;border:1px solid #f59e0b;border-radius:14px;padding:12px;font-size:15px;background:#fffbeb;color:#92400e;"
                                                    >
                                                        <option>No participant types have been configured for this event</option>
                                                    </select>

                                                    <div style="font-size:12px;color:#92400e;margin-top:7px;font-weight:700;line-height:1.5;">
                                                        The organizer must add at least one attendee category before registration can continue.
                                                    </div>
                                                @endif

                                                @error('category_id')
                                                    <div style="font-size:12px;color:#dc2626;margin-top:6px;font-weight:700;">
                                                        {{ $message }}
                                                    </div>
                                                @enderror
                                            </div>
                                        @endif

                                        @if ($standardFields['badge_type']['show'] && $availableBadgeTypes->isNotEmpty())
                                            <div id="field-badge-type-id" class="@error('badge_type_id') elive-field-invalid @enderror">
                                                <label style="display:block;font-weight:800;margin-bottom:7px;">
                                                    Badge Type
                                                    @if ($standardFields['badge_type']['required'])
                                                        <span style="color:#dc2626;">*</span>
                                                    @endif
                                                </label>
                                                <select
                                                    name="badge_type_id"
                                                    @required($standardFields['badge_type']['required'])
                                                    style="width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:14px;padding:12px;font-size:15px;background:white;"
                                                >
                                                    <option value="">Select badge type</option>
                                                    @foreach ($availableBadgeTypes as $badgeType)
                                                        <option value="{{ $badgeType->id }}" @selected(old('badge_type_id') == $badgeType->id)>
                                                            {{ $badgeType->name }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                                @error('badge_type_id')
                                                    <div style="font-size:12px;color:#dc2626;margin-top:6px;font-weight:700;">
                                                        {{ $message }}
                                                    </div>
                                                @enderror
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            @if (($fields ?? collect())->isNotEmpty())
                                <div style="
                                    margin-top: 22px;
                                    background: #ffffff;
                                    border: 1px solid #e2e8f0;
                                    border-radius: 20px;
                                    padding: 22px;
                                    box-shadow: 0 8px 22px rgba(15,23,42,0.06);
                                ">
                                    <h2 style="
                                        margin: 0 0 18px 0;
                                        color: {{ $branding['primary_color'] }};
                                        font-size: 22px;
                                        font-weight: 900;
                                    ">
                                        {{ $sectionLabels['additional'] ?? 'Additional Information' }}
                                    </h2>

                                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:16px;">
                                        @foreach ($fields as $field)
                                            @php
                                                $fieldName = 'answers[' . $field->id . ']';
                                                $oldValue = old('answers.' . $field->id);
                                                $label = $field->label ?? $field->name ?? 'Field';
                                                $type = $field->field_type ?? $field->type ?? 'text';
                                                $placeholder = $field->placeholder ?? '';
                                                $helpText = $field->help_text ?? null;
                                                $isRequired = (bool) ($field->is_required ?? false);

                                                $rawOptions = $field->options ?? [];

                                                if (is_string($rawOptions)) {
                                                    $decodedOptions = json_decode($rawOptions, true);
                                                    $rawOptions = is_array($decodedOptions) ? $decodedOptions : [];
                                                }

                                                $options = collect($rawOptions);
                                            @endphp

                                            <div style="{{ in_array($type, ['textarea', 'checkbox', 'radio'], true) ? 'grid-column:1/-1;' : '' }}">
                                                <label style="display:block;font-weight:800;margin-bottom:7px;">
                                                    {{ $label }}
                                                    @if ($isRequired)
                                                        <span style="color:#dc2626;">*</span>
                                                    @endif
                                                </label>

                                                @if ($type === 'textarea')
                                                    <textarea
                                                        name="{{ $fieldName }}"
                                                        @required($isRequired)
                                                        placeholder="{{ $placeholder }}"
                                                        rows="4"
                                                        style="width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:14px;padding:12px;font-size:15px;"
                                                    >{{ $oldValue }}</textarea>
                                                @elseif ($type === 'select')
                                                    <select
                                                        name="{{ $fieldName }}"
                                                        @required($isRequired)
                                                        style="width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:14px;padding:12px;font-size:15px;background:white;"
                                                    >
                                                        <option value="">Select option</option>

                                                        @foreach ($options as $optionKey => $optionValue)
                                                            @php
                                                                $optionLabel = is_array($optionValue)
                                                                    ? ($optionValue['label'] ?? $optionValue['value'] ?? '')
                                                                    : $optionValue;

                                                                $optionRealValue = is_array($optionValue)
                                                                    ? ($optionValue['value'] ?? $optionLabel)
                                                                    : $optionKey;

                                                                if (is_int($optionKey)) {
                                                                    $optionRealValue = $optionLabel;
                                                                }
                                                            @endphp

                                                            <option value="{{ $optionRealValue }}" @selected($oldValue == $optionRealValue)>
                                                                {{ $optionLabel }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                @elseif ($type === 'radio')
                                                    <div style="display:flex;flex-wrap:wrap;gap:10px;">
                                                        @foreach ($options as $optionKey => $optionValue)
                                                            @php
                                                                $optionLabel = is_array($optionValue)
                                                                    ? ($optionValue['label'] ?? $optionValue['value'] ?? '')
                                                                    : $optionValue;

                                                                $optionRealValue = is_array($optionValue)
                                                                    ? ($optionValue['value'] ?? $optionLabel)
                                                                    : $optionKey;

                                                                if (is_int($optionKey)) {
                                                                    $optionRealValue = $optionLabel;
                                                                }
                                                            @endphp

                                                            <label style="display:flex;align-items:center;gap:8px;border:1px solid #cbd5e1;border-radius:12px;padding:10px 12px;">
                                                                <input
                                                                    type="radio"
                                                                    name="{{ $fieldName }}"
                                                                    value="{{ $optionRealValue }}"
                                                                    @required($isRequired && $loop->first)
                                                                    @checked($oldValue == $optionRealValue)
                                                                >
                                                                <span>{{ $optionLabel }}</span>
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                @elseif ($type === 'checkbox')
                                                    <div style="display:flex;flex-wrap:wrap;gap:10px;">
                                                        @foreach ($options as $optionKey => $optionValue)
                                                            @php
                                                                $optionLabel = is_array($optionValue)
                                                                    ? ($optionValue['label'] ?? $optionValue['value'] ?? '')
                                                                    : $optionValue;

                                                                $optionRealValue = is_array($optionValue)
                                                                    ? ($optionValue['value'] ?? $optionLabel)
                                                                    : $optionKey;

                                                                if (is_int($optionKey)) {
                                                                    $optionRealValue = $optionLabel;
                                                                }

                                                                $oldArray = is_array($oldValue) ? $oldValue : [];
                                                            @endphp

                                                            <label style="display:flex;align-items:center;gap:8px;border:1px solid #cbd5e1;border-radius:12px;padding:10px 12px;">
                                                                <input
                                                                    type="checkbox"
                                                                    name="answers[{{ $field->id }}][]"
                                                                    value="{{ $optionRealValue }}"
                                                                    @checked(in_array($optionRealValue, $oldArray, true))
                                                                >
                                                                <span>{{ $optionLabel }}</span>
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                @elseif ($type === 'date')
                                                    <input
                                                        type="date"
                                                        name="{{ $fieldName }}"
                                                        value="{{ $oldValue }}"
                                                        @required($isRequired)
                                                        style="width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:14px;padding:12px;font-size:15px;"
                                                    >
                                                @elseif ($type === 'number')
                                                    <input
                                                        type="number"
                                                        name="{{ $fieldName }}"
                                                        value="{{ $oldValue }}"
                                                        placeholder="{{ $placeholder }}"
                                                        @required($isRequired)
                                                        style="width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:14px;padding:12px;font-size:15px;"
                                                    >
                                                @elseif ($type === 'email')
                                                    <input
                                                        type="email"
                                                        name="{{ $fieldName }}"
                                                        value="{{ $oldValue }}"
                                                        placeholder="{{ $placeholder }}"
                                                        @required($isRequired)
                                                        style="width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:14px;padding:12px;font-size:15px;"
                                                    >
                                                @elseif ($type === 'phone')
                                                    <input
                                                        type="tel"
                                                        name="{{ $fieldName }}"
                                                        value="{{ $oldValue }}"
                                                        placeholder="{{ $placeholder ?: '255712345678' }}"
                                                        @required($isRequired)
                                                        style="width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:14px;padding:12px;font-size:15px;"
                                                    >
                                                @else
                                                    <input
                                                        type="text"
                                                        name="{{ $fieldName }}"
                                                        value="{{ $oldValue }}"
                                                        placeholder="{{ $placeholder }}"
                                                        @required($isRequired)
                                                        style="width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:14px;padding:12px;font-size:15px;"
                                                    >
                                                @endif

                                                @if ($helpText)
                                                    <div style="font-size:12px;color:#64748b;margin-top:6px;">
                                                        {{ $helpText }}
                                                    </div>
                                                @endif

                                                @error('answers.' . $field->id)
                                                    <div style="font-size:12px;color:#dc2626;margin-top:6px;font-weight:700;">
                                                        {{ $message }}
                                                    </div>
                                                @enderror
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if (
                                $allowDaySelection
                                && ($eventDays ?? collect())->count()
                            )
                                @php
                                    $oldEventDaysRaw = collect(old('event_days', []))
                                        ->map(fn ($value) => (string) $value);

                                    $allDaysPreviouslySelected = $oldEventDaysRaw
                                        ->contains('all');

                                    $oldEventDays = $oldEventDaysRaw
                                        ->filter(fn ($value) => is_numeric($value))
                                        ->map(fn ($id) => (int) $id)
                                        ->all();
                                @endphp

                                <div style="
                                    margin-top:22px;
                                    background:#ffffff;
                                    border:1px solid #e2e8f0;
                                    border-radius:20px;
                                    padding:22px;
                                    box-shadow:0 8px 22px rgba(15,23,42,0.06);
                                ">
                                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;">
                                        <div>
                                            <h2 style="
                                                margin:0;
                                                color:{{ $branding['primary_color'] }};
                                                font-size:22px;
                                                font-weight:900;
                                            ">
                                                {{ $sectionLabels['attendance'] ?? 'Attendance Selection' }}
                                            </h2>

                                            <p style="margin:7px 0 0;color:#64748b;font-size:14px;line-height:1.5;">
                                                Choose the event days you plan to attend.
                                                @if ($allowAllDaysSelection)
                                                    You may also select all available days at once.
                                                @endif
                                            </p>
                                        </div>

                                        <div style="
                                            background:#eef2ff;
                                            border:1px solid #c7d2fe;
                                            color:#3730a3;
                                            border-radius:12px;
                                            padding:10px 12px;
                                            font-size:12px;
                                            font-weight:800;
                                        ">
                                            Select at least one day
                                        </div>
                                    </div>

                                    @if ($allowAllDaysSelection)
                                    <label
                                        data-all-days-card
                                        style="
                                            display:flex;
                                            align-items:flex-start;
                                            gap:12px;
                                            margin-top:18px;
                                            padding:17px;
                                            border:2px solid {{ $branding['primary_color'] }};
                                            border-radius:16px;
                                            background:color-mix(in srgb, {{ $branding['primary_color'] }} 8%, #ffffff);
                                            cursor:pointer;
                                        "
                                    >
                                        <input
                                            type="checkbox"
                                            name="event_days[]"
                                            value="all"
                                            data-all-days-checkbox
                                            @checked($allDaysPreviouslySelected)
                                            style="
                                                width:20px;
                                                height:20px;
                                                margin-top:2px;
                                                flex:0 0 auto;
                                            "
                                        >

                                        <span style="display:block;min-width:0;">
                                            <strong style="
                                                display:block;
                                                color:{{ $branding['primary_color'] }};
                                                font-size:16px;
                                                line-height:1.35;
                                            ">
                                                All Event Days
                                            </strong>

                                            <span style="
                                                display:block;
                                                margin-top:5px;
                                                color:#475569;
                                                font-size:13px;
                                                line-height:1.5;
                                            ">
                                                Register me for every available day of this event.
                                            </span>
                                        </span>
                                    </label>
                                    @endif

                                    <div style="
                                        display:grid;
                                        grid-template-columns:repeat(auto-fit,minmax(250px,1fr));
                                        gap:12px;
                                        margin-top:12px;
                                    ">
                                        @foreach ($eventDays as $day)
                                            @php
                                                $daySelected = in_array(
                                                    (int) $day->id,
                                                    $oldEventDays,
                                                    true
                                                );
                                            @endphp

                                            <label style="
                                                display:flex;
                                                align-items:flex-start;
                                                gap:12px;
                                                padding:16px;
                                                border:1px solid #e2e8f0;
                                                border-radius:16px;
                                                background:#f8fafc;
                                                cursor:pointer;
                                            ">
                                                <input
                                                    type="checkbox"
                                                    name="event_days[]"
                                                    value="{{ $day->id }}"
                                                    data-event-day-checkbox
                                                    @checked($daySelected || $allDaysPreviouslySelected)
                                                    style="
                                                        width:19px;
                                                        height:19px;
                                                        margin-top:2px;
                                                        flex:0 0 auto;
                                                    "
                                                >

                                                <span style="display:block;min-width:0;">
                                                    <strong style="
                                                        display:block;
                                                        color:#0f172a;
                                                        font-size:15px;
                                                        line-height:1.35;
                                                    ">
                                                        {{ $day->name }}
                                                    </strong>

                                                    <span style="
                                                        display:block;
                                                        margin-top:5px;
                                                        color:#64748b;
                                                        font-size:13px;
                                                        line-height:1.5;
                                                    ">
                                                        {{ $day->event_date?->format('d M Y') }}

                                                        @if ($day->starts_at)
                                                            — {{ $day->starts_at?->format('H:i') }}
                                                        @endif

                                                        @if ($day->ends_at)
                                                            to {{ $day->ends_at?->format('H:i') }}
                                                        @endif
                                                    </span>

                                                    @if (filled($day->venue_name))
                                                        <span style="
                                                            display:block;
                                                            margin-top:4px;
                                                            color:#475569;
                                                            font-size:12px;
                                                            font-weight:700;
                                                        ">
                                                            {{ $day->venue_name }}
                                                        </span>
                                                    @endif
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>

                                    @error('event_days')
                                        <div style="
                                            margin-top:12px;
                                            color:#dc2626;
                                            font-size:13px;
                                            font-weight:800;
                                        ">
                                            {{ $message }}
                                        </div>
                                    @enderror

                                    @error('event_days.*')
                                        <div style="
                                            margin-top:12px;
                                            color:#dc2626;
                                            font-size:13px;
                                            font-weight:800;
                                        ">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                </div>
                            @endif

                            @if (
                                $allowSessionRegistration
                                && ($eventSessions ?? collect())->isNotEmpty()
                            )
                                @php
                                    $oldEventSessions = collect(
                                        old('event_sessions', [])
                                    )
                                        ->filter(
                                            fn ($value) =>
                                                is_numeric($value)
                                        )
                                        ->map(
                                            fn ($value) =>
                                                (int) $value
                                        )
                                        ->all();

                                    $sessionsByDay = $eventSessions
                                        ->groupBy('event_day_id');
                                @endphp

                                <div
                                    data-session-section
                                    style="
                                        margin-top:22px;
                                        background:#ffffff;
                                        border:1px solid #e2e8f0;
                                        border-radius:20px;
                                        padding:22px;
                                        box-shadow:0 8px 22px rgba(15,23,42,0.06);
                                    "
                                >
                                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;">
                                        <div>
                                            <h2 style="
                                                margin:0;
                                                color:{{ $branding['primary_color'] }};
                                                font-size:22px;
                                                font-weight:900;
                                            ">
                                                {{ $sectionLabels['sessions'] ?? 'Sessions / Activities' }}
                                            </h2>

                                            <p style="margin:7px 0 0;color:#64748b;font-size:14px;line-height:1.5;">
                                                Select the sessions or activities you would like to attend.
                                                @if ($allowDaySelection)
                                                    Only sessions belonging to your selected event days are shown.
                                                @else
                                                    Available sessions are shown for all event days assigned to your registration.
                                                @endif
                                            </p>
                                        </div>

                                        <div style="
                                            background:#f8fafc;
                                            border:1px solid #e2e8f0;
                                            border-radius:12px;
                                            padding:10px 12px;
                                            font-size:12px;
                                            color:#475569;
                                            font-weight:800;
                                        ">
                                            Session selection is optional unless specified by the organizer.
                                        </div>
                                    </div>

                                    <div
                                        data-session-groups
                                        style="
                                            display:grid;
                                            gap:18px;
                                            margin-top:18px;
                                        "
                                    >
                                        @foreach ($eventDays as $day)
                                            @php
                                                $daySessions = $sessionsByDay
                                                    ->get($day->id, collect());
                                            @endphp

                                            @if ($daySessions->isNotEmpty())
                                                <div
                                                    class="elive-session-day-group"
                                                    data-session-day-group="{{ $day->id }}"
                                                    @if ($allowDaySelection) hidden @endif
                                                >
                                                    <div class="elive-session-day-title">
                                                        <div>
                                                            <strong style="
                                                                display:block;
                                                                color:{{ $branding['primary_color'] }};
                                                                font-size:15px;
                                                            ">
                                                                {{ $day->name }}
                                                            </strong>

                                                            <span style="
                                                                display:block;
                                                                margin-top:3px;
                                                                color:#64748b;
                                                                font-size:12px;
                                                            ">
                                                                {{ $day->event_date?->format('d M Y') }}
                                                            </span>
                                                        </div>

                                                        <span style="
                                                            font-size:11px;
                                                            font-weight:900;
                                                            color:#475569;
                                                        ">
                                                            {{ $daySessions->count() }}
                                                            {{ \Illuminate\Support\Str::plural('session', $daySessions->count()) }}
                                                        </span>
                                                    </div>

                                                    <div class="elive-session-grid">
                                                        @foreach ($daySessions as $session)
                                                            @php
                                                                $capacity = $session->capacity;
                                                                $registeredCount = (int) (
                                                                    $session->registered_attendees_count
                                                                    ?? 0
                                                                );

                                                                $remainingCapacity = $capacity !== null
                                                                    && (int) $capacity > 0
                                                                        ? max(
                                                                            (int) $capacity
                                                                            - $registeredCount,
                                                                            0
                                                                        )
                                                                        : null;

                                                                $sessionIsFull =
                                                                    $remainingCapacity !== null
                                                                    && $remainingCapacity <= 0;

                                                                $sessionSelected = in_array(
                                                                    (int) $session->id,
                                                                    $oldEventSessions,
                                                                    true
                                                                );

                                                                $sessionType = ucwords(
                                                                    str_replace(
                                                                        '_',
                                                                        ' ',
                                                                        $session->session_type
                                                                            ?: 'session'
                                                                    )
                                                                );
                                                            @endphp

                                                            <label
                                                                class="elive-session-card"
                                                                data-session-card
                                                                data-session-day="{{ $day->id }}"
                                                                data-full="{{ $sessionIsFull ? '1' : '0' }}"
                                                            >
                                                                <input
                                                                    type="checkbox"
                                                                    name="event_sessions[]"
                                                                    value="{{ $session->id }}"
                                                                    data-session-checkbox
                                                                    data-session-day="{{ $day->id }}"
                                                                    @checked($sessionSelected && ! $sessionIsFull)
                                                                    @disabled($sessionIsFull)
                                                                >

                                                                <span style="display:block;min-width:0;">
                                                                    <strong style="
                                                                        display:block;
                                                                        color:#0f172a;
                                                                        font-size:15px;
                                                                        line-height:1.35;
                                                                    ">
                                                                        {{ $session->name }}
                                                                    </strong>

                                                                    <span class="elive-session-type">
                                                                        {{ $sessionType }}
                                                                    </span>

                                                                    @if ($session->starts_at || $session->ends_at)
                                                                        <span class="elive-session-meta">
                                                                            @if ($session->starts_at)
                                                                                {{ $session->starts_at->format('H:i') }}
                                                                            @endif

                                                                            @if ($session->ends_at)
                                                                                @if ($session->starts_at)
                                                                                    –
                                                                                @endif
                                                                                {{ $session->ends_at->format('H:i') }}
                                                                            @endif
                                                                        </span>
                                                                    @endif

                                                                    @if (filled($session->venue_name))
                                                                        <span class="elive-session-meta">
                                                                            {{ $session->venue_name }}
                                                                        </span>
                                                                    @endif

                                                                    @if (filled($session->description))
                                                                        <span class="elive-session-meta">
                                                                            {{ \Illuminate\Support\Str::limit($session->description, 120) }}
                                                                        </span>
                                                                    @endif

                                                                    @if ($sessionIsFull)
                                                                        <span
                                                                            class="elive-session-capacity"
                                                                            style="color:#b91c1c;"
                                                                        >
                                                                            Session full
                                                                        </span>
                                                                    @elseif ($remainingCapacity !== null)
                                                                        <span class="elive-session-capacity">
                                                                            {{ $remainingCapacity }}
                                                                            {{ \Illuminate\Support\Str::plural('place', $remainingCapacity) }}
                                                                            available
                                                                        </span>
                                                                    @else
                                                                        <span class="elive-session-capacity">
                                                                            Open capacity
                                                                        </span>
                                                                    @endif
                                                                </span>
                                                            </label>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>

                                    <div
                                        data-session-empty
                                        class="elive-session-empty"
                                        hidden
                                        style="margin-top:16px;"
                                    >
                                        @if ($allowDaySelection)
                                            Select an event day above to view the sessions available for that day.
                                        @else
                                            No public sessions are currently available.
                                        @endif
                                    </div>

                                    @error('event_sessions')
                                        <div style="
                                            margin-top:12px;
                                            color:#dc2626;
                                            font-size:13px;
                                            font-weight:800;
                                        ">
                                            {{ $message }}
                                        </div>
                                    @enderror

                                    @error('event_sessions.*')
                                        <div style="
                                            margin-top:12px;
                                            color:#dc2626;
                                            font-size:13px;
                                            font-weight:800;
                                        ">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                </div>
                            @endif

                            @if (($merchandiseItems ?? collect())->count())
                                <div style="
                                    margin-top: 22px;
                                    background: #ffffff;
                                    border: 1px solid #e2e8f0;
                                    border-radius: 20px;
                                    padding: 22px;
                                    box-shadow: 0 8px 22px rgba(15,23,42,0.06);
                                ">
                                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;">
                                        <div>
                                            <h2 style="
                                                margin: 0;
                                                color: {{ $branding['primary_color'] }};
                                                font-size: 22px;
                                                font-weight: 900;
                                            ">
                                                Merchandise Order
                                            </h2>

                                            <p style="margin:7px 0 0;color:#64748b;font-size:14px;line-height:1.5;">
                                                Select the items you would like to order, including the preferred size, color and quantity.
                                                Payment instructions for paid items will be provided after registration.
                                            </p>
                                        </div>

                                        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:10px 12px;font-size:12px;color:#475569;">
                                            Optional items may be skipped.
                                        </div>
                                    </div>

                                    <div style="display:grid;gap:16px;margin-top:18px;">
                                        @foreach ($merchandiseItems as $item)
                                            @php
                                                $oldSelected = $item->selection_type === 'required'
                                                    || (bool) old('merchandise.' . $item->id . '.selected', false);

                                                $oldVariantId = old('merchandise.' . $item->id . '.variant_id');
                                                $oldQuantity = old('merchandise.' . $item->id . '.quantity', 1);
                                                $maximumQuantity = max(1, (int) $item->maximum_per_attendee);
                                                $showItemImage = method_exists($item, 'shouldShowImage')
                                                    ? $item->shouldShowImage()
                                                    : ((bool) $item->show_image
                                                        && (bool) $event->show_merchandise_images
                                                        && filled($item->image_path));
                                            @endphp

                                            <div
                                                class="merchandise-card"
                                                data-merchandise-card
                                                data-required="{{ $item->selection_type === 'required' ? '1' : '0' }}"
                                                style="border:1px solid #e2e8f0;border-radius:18px;padding:18px;background:#f8fafc;"
                                            >
                                                <div style="display:flex;gap:16px;align-items:flex-start;flex-wrap:wrap;">
                                                    @if ($showItemImage)
                                                        <img
                                                            src="{{ asset('storage/' . $item->image_path) }}"
                                                            alt="{{ $item->name }}"
                                                            style="width:110px;height:110px;object-fit:cover;border-radius:16px;border:1px solid #e2e8f0;background:white;"
                                                        >
                                                    @endif

                                                    <div style="flex:1;min-width:240px;">
                                                        <div style="display:flex;justify-content:space-between;gap:12px;align-items:flex-start;flex-wrap:wrap;">
                                                            <div>
                                                                <h3 style="margin:0;font-size:18px;font-weight:900;color:#0f172a;">
                                                                    {{ $item->name }}
                                                                </h3>

                                                                @if (filled($item->description))
                                                                    <p style="margin:6px 0 0;color:#64748b;font-size:14px;line-height:1.5;">
                                                                        {{ $item->description }}
                                                                    </p>
                                                                @endif
                                                            </div>

                                                            <span style="
                                                                display:inline-flex;
                                                                align-items:center;
                                                                border-radius:999px;
                                                                padding:6px 10px;
                                                                font-size:11px;
                                                                font-weight:900;
                                                                background:{{ $item->selection_type === 'required' ? '#fee2e2' : '#e2e8f0' }};
                                                                color:{{ $item->selection_type === 'required' ? '#991b1b' : '#334155' }};
                                                            ">
                                                                {{ $item->selection_type === 'required' ? 'Required' : 'Optional' }}
                                                            </span>
                                                        </div>

                                                        @if ($item->selection_type === 'required')
                                                            <input
                                                                type="hidden"
                                                                name="merchandise[{{ $item->id }}][selected]"
                                                                value="1"
                                                            >
                                                        @else
                                                            <label style="display:flex;align-items:center;gap:10px;margin-top:14px;font-weight:800;cursor:pointer;">
                                                                <input
                                                                    type="checkbox"
                                                                    name="merchandise[{{ $item->id }}][selected]"
                                                                    value="1"
                                                                    data-merchandise-toggle
                                                                    @checked($oldSelected)
                                                                    style="width:18px;height:18px;"
                                                                >
                                                                <span>Add this item to my order</span>
                                                            </label>
                                                        @endif

                                                        <div
                                                            data-merchandise-fields
                                                            style="margin-top:16px;{{ $oldSelected ? '' : 'display:none;' }}"
                                                        >
                                                            @if ($item->activeVariants->isEmpty())
                                                                <div style="
                                                                    background:#fff7ed;
                                                                    color:#9a3412;
                                                                    border:1px solid #fed7aa;
                                                                    border-radius:12px;
                                                                    padding:12px;
                                                                    font-size:13px;
                                                                    font-weight:700;
                                                                ">
                                                                    This item is currently unavailable because no active variants have been configured.
                                                                </div>
                                                            @else
                                                                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;">
                                                                    <div>
                                                                        <label style="display:block;font-weight:800;margin-bottom:7px;">
                                                                            Size / Color Variant
                                                                            <span style="color:#dc2626;">*</span>
                                                                        </label>

                                                                        <select
                                                                            name="merchandise[{{ $item->id }}][variant_id]"
                                                                            data-variant-select
                                                                            style="width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:14px;padding:12px;font-size:15px;background:white;"
                                                                        >
                                                                            <option value="">Select size and color</option>

                                                                            @foreach ($item->activeVariants as $variant)
                                                                                @php
                                                                                    $remaining = method_exists($variant, 'remainingQuantity')
                                                                                        ? $variant->remainingQuantity()
                                                                                        : max(0, (int) $variant->stock_quantity);

                                                                                    $variantName = method_exists($variant, 'displayName')
                                                                                        ? $variant->displayName()
                                                                                        : $variant->name;

                                                                                    $variantPrice = (float) ($variant->price ?? 0);
                                                                                    $variantCurrency = $variant->currency ?: 'TZS';
                                                                                @endphp

                                                                                <option
                                                                                    value="{{ $variant->id }}"
                                                                                    data-price="{{ $variantPrice }}"
                                                                                    data-currency="{{ $variantCurrency }}"
                                                                                    data-stock="{{ $remaining }}"
                                                                                    @selected((string) $oldVariantId === (string) $variant->id)
                                                                                    @disabled($remaining <= 0)
                                                                                >
                                                                                    {{ $variantName }}
                                                                                    — {{ $variantPrice > 0 ? $variantCurrency . ' ' . number_format($variantPrice, 2) : 'Free' }}
                                                                                </option>
                                                                            @endforeach
                                                                        </select>

                                                                        @error('merchandise.' . $item->id . '.variant_id')
                                                                            <div style="font-size:12px;color:#dc2626;margin-top:6px;font-weight:700;">
                                                                                {{ $message }}
                                                                            </div>
                                                                        @enderror
                                                                    </div>

                                                                    <div>
                                                                        <label style="display:block;font-weight:800;margin-bottom:7px;">
                                                                            Quantity
                                                                            <span style="color:#dc2626;">*</span>
                                                                        </label>

                                                                        <input
                                                                            type="number"
                                                                            name="merchandise[{{ $item->id }}][quantity]"
                                                                            value="{{ $oldQuantity }}"
                                                                            min="1"
                                                                            max="{{ $maximumQuantity }}"
                                                                            data-attendee-maximum="{{ $maximumQuantity }}"
                                                                            data-quantity-input
                                                                            style="width:100%;box-sizing:border-box;border:1px solid #cbd5e1;border-radius:14px;padding:12px;font-size:15px;"
                                                                        >

                                                                        <div style="font-size:12px;color:#64748b;margin-top:6px;">
                                                                            Maximum allowed: {{ $maximumQuantity }}
                                                                        </div>

                                                                        @error('merchandise.' . $item->id . '.quantity')
                                                                            <div style="font-size:12px;color:#dc2626;margin-top:6px;font-weight:700;">
                                                                                {{ $message }}
                                                                            </div>
                                                                        @enderror
                                                                    </div>
                                                                </div>

                                                                <div style="margin-top:14px;background:white;border:1px solid #e2e8f0;border-radius:14px;padding:14px;">
                                                                    <div style="display:flex;justify-content:space-between;gap:12px;font-size:14px;color:#475569;">
                                                                        <span>Unit price</span>
                                                                        <strong data-unit-price>—</strong>
                                                                    </div>

                                                                    <div style="display:flex;justify-content:space-between;gap:12px;font-size:16px;margin-top:9px;color:#0f172a;">
                                                                        <span style="font-weight:900;">Total</span>
                                                                        <strong data-total-price>—</strong>
                                                                    </div>

                                                                </div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if (($merchandiseItems ?? collect())->isNotEmpty())
                            <div
                                data-payment-section
                                style="
                                    margin-top:22px;
                                    background:#ffffff;
                                    border:1px solid #e2e8f0;
                                    border-radius:20px;
                                    padding:22px;
                                    box-shadow:0 8px 22px rgba(15,23,42,0.06);
                                "
                            >
                                <h2 style="
                                    margin:0;
                                    color:{{ $branding['primary_color'] }};
                                    font-size:22px;
                                    font-weight:900;
                                ">
                                    Payment
                                </h2>

                                <p style="margin:7px 0 0;color:#64748b;font-size:14px;line-height:1.5;">
                                    Payment is only required when a paid item or paid registration option is selected.
                                </p>

                                <div style="
                                    margin-top:16px;
                                    background:#f8fafc;
                                    border:1px solid #e2e8f0;
                                    border-radius:14px;
                                    padding:14px;
                                ">
                                    <div style="display:flex;justify-content:space-between;gap:12px;font-size:14px;color:#475569;">
                                        <span>Amount payable</span>
                                        <strong data-payment-total>No payment required</strong>
                                    </div>

                                    <div style="margin-top:8px;font-size:12px;color:#64748b;">
                                        Final payment instructions will be shown after registration.
                                    </div>
                                </div>
                            </div>

                            @endif

                            <button
                                type="submit"
                                data-submit-button
                                data-default-label="{{ $isFull && $waitlistEnabled ? 'Join Waitlist' : 'Submit Registration' }}"
                                data-order-label="Submit Registration and Order"
                                style="
                                    margin-top: 22px;
                                    width: 100%;
                                    border: none;
                                    border-radius: 16px;
                                    padding: 15px 18px;
                                    background: {{ $branding['button_color'] }};
                                    color: white;
                                    font-size: 16px;
                                    font-weight: 900;
                                    cursor: pointer;
                                "
                            >
                                {{ $isFull && $waitlistEnabled
                                    ? 'Join Waitlist'
                                    : 'Submit Registration' }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="elive-footer" style="text-align:center;color:#64748b;font-size:13px;margin-top:18px;">
                Powered by eLive Events
                @if ($branding['support_email'])
                    | Support: {{ $branding['support_email'] }}
                @endif
                @if ($branding['support_phone'])
                    | {{ $branding['support_phone'] }}
                @endif
            </div>
        </div>
    </div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const firstInvalidField = document.querySelector('.elive-field-invalid');

        if (firstInvalidField) {
            window.setTimeout(() => {
                firstInvalidField.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center',
                });

                const focusable = firstInvalidField.querySelector(
                    'input:not([type="hidden"]), select, textarea'
                );

                focusable?.focus({ preventScroll: true });
            }, 120);
        }

        const allowDaySelection = @json($allowDaySelection);
        const allowAllDaysSelection = @json($allowAllDaysSelection);
        const allowSessionRegistration = @json($allowSessionRegistration);

        const formatMoney = (amount, currency) => {
            const numericAmount = Number(amount || 0);

            if (numericAmount <= 0) {
                return 'Free';
            }

            return `${currency || 'TZS'} ${numericAmount.toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            })}`;
        };

        const registrationForm = document.querySelector('[data-registration-form]');
        const submitButton = document.querySelector('[data-submit-button]');
        const paymentTotal = document.querySelector('[data-payment-total]');
        const allDaysCheckbox = document.querySelector('[data-all-days-checkbox]');
        const eventDayCheckboxes = Array.from(
            document.querySelectorAll('[data-event-day-checkbox]')
        );

        const sessionSection = document.querySelector(
            '[data-session-section]'
        );

        const sessionDayGroups = Array.from(
            document.querySelectorAll('[data-session-day-group]')
        );

        const sessionCheckboxes = Array.from(
            document.querySelectorAll('[data-session-checkbox]')
        );

        const sessionEmpty = document.querySelector(
            '[data-session-empty]'
        );

        const selectedEventDayIds = () => {
            if (!allowDaySelection) {
                return sessionDayGroups
                    .map(
                        (group) =>
                            String(
                                group.dataset.sessionDayGroup
                                || ''
                            )
                    )
                    .filter(Boolean);
            }

            return eventDayCheckboxes
                .filter((checkbox) => checkbox.checked)
                .map((checkbox) => String(checkbox.value));
        };

        const refreshSessionVisibility = () => {
            if (!sessionSection || !allowSessionRegistration) {
                return;
            }

            const selectedDays = new Set(
                selectedEventDayIds()
            );

            let visibleGroups = 0;

            sessionDayGroups.forEach((group) => {
                const dayId = String(
                    group.dataset.sessionDayGroup || ''
                );

                const visible =
                    !allowDaySelection
                    || selectedDays.has(dayId);

                group.hidden = !visible;

                if (visible) {
                    visibleGroups += 1;
                }
            });

            sessionCheckboxes.forEach((checkbox) => {
                const dayId = String(
                    checkbox.dataset.sessionDay || ''
                );

                const daySelected =
                    !allowDaySelection
                    || selectedDays.has(dayId);

                const card = checkbox.closest(
                    '[data-session-card]'
                );

                const full = card?.dataset.full === '1';

                checkbox.disabled =
                    !daySelected || full;

                if (
                    allowDaySelection
                    && !daySelected
                ) {
                    checkbox.checked = false;
                }
            });

            if (sessionEmpty) {
                sessionEmpty.hidden = visibleGroups > 0;
            }
        };

        const refreshAllDaysSelection = () => {
            if (
                !allowDaySelection
                || !allowAllDaysSelection
                || !allDaysCheckbox
                || eventDayCheckboxes.length === 0
            ) {
                return;
            }

            const everyDaySelected = eventDayCheckboxes.every(
                (checkbox) => checkbox.checked
            );

            const noDaySelected = eventDayCheckboxes.every(
                (checkbox) => !checkbox.checked
            );

            allDaysCheckbox.checked = everyDaySelected;
            allDaysCheckbox.indeterminate = !everyDaySelected && !noDaySelected;
        };

        if (
            allowDaySelection
            && allowAllDaysSelection
        ) {
            allDaysCheckbox?.addEventListener('change', () => {
            eventDayCheckboxes.forEach((checkbox) => {
                checkbox.checked = allDaysCheckbox.checked;
            });

            allDaysCheckbox.indeterminate = false;

                refreshSessionVisibility();
            });
        }

        if (allowDaySelection) {
            eventDayCheckboxes.forEach((checkbox) => {
                checkbox.addEventListener('change', () => {
                    refreshAllDaysSelection();
                    refreshSessionVisibility();
                });
            });
        }

        refreshAllDaysSelection();
        refreshSessionVisibility();

        const refreshSubmitLabel = () => {
            if (!submitButton) {
                return;
            }

            const hasSelectedMerchandise = Array.from(
                document.querySelectorAll('[data-merchandise-card]')
            ).some((card) => {
                const required = card.dataset.required === '1';
                const toggle = card.querySelector('[data-merchandise-toggle]');

                return required || Boolean(toggle?.checked);
            });

            submitButton.textContent = hasSelectedMerchandise
                ? submitButton.dataset.orderLabel
                : submitButton.dataset.defaultLabel;
        };

        const refreshPaymentTotal = () => {
            if (!paymentTotal) {
                return;
            }

            let total = 0;
            let currency = 'TZS';

            document.querySelectorAll('[data-merchandise-card]').forEach((card) => {
                const required = card.dataset.required === '1';
                const toggle = card.querySelector('[data-merchandise-toggle]');
                const selected = required || Boolean(toggle?.checked);

                if (!selected) {
                    return;
                }

                const variantSelect = card.querySelector('[data-variant-select]');
                const quantityInput = card.querySelector('[data-quantity-input]');
                const option = variantSelect?.options[variantSelect.selectedIndex];

                if (!option?.value) {
                    return;
                }

                const price = Number(option.dataset.price || 0);
                const quantity = Number(quantityInput?.value || 1);

                currency = option.dataset.currency || currency;
                total += price * quantity;
            });

            paymentTotal.textContent = total > 0
                ? formatMoney(total, currency)
                : 'No payment required';
        };

        registrationForm?.addEventListener('submit', (event) => {
            if (
                allowDaySelection
                && eventDayCheckboxes.length > 0
                && (
                    !allowAllDaysSelection
                    || !allDaysCheckbox?.checked
                )
                && !eventDayCheckboxes.some(
                    (checkbox) => checkbox.checked
                )
            ) {
                event.preventDefault();

                window.alert('Please select at least one event day.');

                return;
            }

            if (!submitButton) {
                return;
            }

            submitButton.disabled = true;
            submitButton.style.opacity = '0.7';
            submitButton.style.cursor = 'not-allowed';
            submitButton.textContent = 'Submitting...';
        });

        document.querySelectorAll('[data-merchandise-card]').forEach((card) => {
            const required = card.dataset.required === '1';
            const toggle = card.querySelector('[data-merchandise-toggle]');
            const fields = card.querySelector('[data-merchandise-fields]');
            const variantSelect = card.querySelector('[data-variant-select]');
            const quantityInput = card.querySelector('[data-quantity-input]');
            const unitPrice = card.querySelector('[data-unit-price]');
            const totalPrice = card.querySelector('[data-total-price]');

            const refreshVisibility = () => {
                const selected = required || Boolean(toggle?.checked);

                fields.style.display = selected ? 'block' : 'none';

                if (variantSelect) {
                    variantSelect.disabled = !selected;
                    variantSelect.required = selected;
                }

                if (quantityInput) {
                    quantityInput.disabled = !selected;
                    quantityInput.required = selected;
                }

                if (!selected) {
                    if (variantSelect) {
                        variantSelect.value = '';
                    }

                    if (quantityInput) {
                        quantityInput.value = 1;
                        quantityInput.max = quantityInput.dataset.attendeeMaximum || 1;
                    }
                }

                refreshSummary();
                refreshSubmitLabel();
                refreshPaymentTotal();
            };

            const refreshSummary = () => {
                if (!variantSelect || !quantityInput || !unitPrice || !totalPrice) {
                    return;
                }

                const option = variantSelect.options[variantSelect.selectedIndex];
                const price = Number(option?.dataset.price || 0);
                const currency = option?.dataset.currency || 'TZS';
                const stock = Number(option?.dataset.stock || 0);
                const attendeeMaximum = Number(
                    quantityInput.dataset.attendeeMaximum || quantityInput.max || 1
                );

                const effectiveMaximum = option?.value
                    ? Math.max(1, Math.min(attendeeMaximum, Math.max(1, stock)))
                    : attendeeMaximum;

                quantityInput.max = effectiveMaximum;

                let quantity = Number(quantityInput.value || 1);
                quantity = Math.max(1, Math.min(quantity, effectiveMaximum));
                quantityInput.value = quantity;

                if (!option || !option.value) {
                    unitPrice.textContent = '—';
                    totalPrice.textContent = '—';
                    return;
                }

                unitPrice.textContent = formatMoney(price, currency);
                totalPrice.textContent = formatMoney(price * quantity, currency);
            };

            toggle?.addEventListener('change', refreshVisibility);
            variantSelect?.addEventListener('change', () => {
                refreshSummary();
                refreshPaymentTotal();
            });

            quantityInput?.addEventListener('input', () => {
                refreshSummary();
                refreshPaymentTotal();
            });

            quantityInput?.addEventListener('change', () => {
                refreshSummary();
                refreshPaymentTotal();
            });

            refreshVisibility();
        });

        refreshSubmitLabel();
        refreshPaymentTotal();
    });
</script>

</body>
</html>
