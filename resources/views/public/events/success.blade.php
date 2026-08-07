<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    @php
        $successTitle = match ($attendee->status) {
            'pending_approval' => 'Registration Received',
            'waitlisted' => 'Waitlist Registration Received',
            'rejected' => 'Registration Not Approved',
            'cancelled' => 'Registration Cancelled',
            default => 'Registration Successful',
        };

        $successMessage = match ($attendee->status) {
            'pending_approval' =>
                'Your registration has been received and is awaiting approval.',
            'waitlisted' =>
                'Your registration has been received and you have been added to the waitlist.',
            'rejected' =>
                'Your registration was not approved. Contact the event organizer for assistance.',
            'cancelled' =>
                'This registration has been cancelled.',
            default =>
                $event->registration_success_message
                    ?: 'Thank you. Your registration has been received successfully.',
        };

        $statusLabel = strtoupper(
            str_replace('_', ' ', $attendee->status)
        );

        $statusTone = match ($attendee->status) {
            'registered', 'confirmed', 'approved', 'checked_in' => 'success',
            'pending_approval' => 'warning',
            'waitlisted' => 'info',
            'rejected', 'cancelled' => 'danger',
            default => 'neutral',
        };

        $canViewBadge = $attendee->public_token
            && in_array(
                $attendee->status,
                [
                    'registered',
                    'confirmed',
                    'approved',
                    'checked_in',
                ],
                true
            );

        $registrationUrl = $attendee->public_token
            ? $attendee->publicUrl()
            : null;

        $selectedEventDays =
            $attendee->eventDays ?? collect();

        $selectedAllDays = $selectedEventDays->contains(
            fn ($day) =>
                data_get(
                    $day,
                    'pivot.selection_source'
                ) === 'public_registration_all_days'
        );

        $autoAssignedDays = $selectedEventDays->contains(
            fn ($day) =>
                data_get(
                    $day,
                    'pivot.selection_source'
                ) === 'public_registration_auto_days'
        );

        $allowDaySelection = $event->allowsDaySelection();
        $allowAllDaysSelection = $event->allowsAllDaysSelection();
        $allowSessionRegistration = $event->allowsSessionRegistration();

        $attendanceSectionTitle = $allowDaySelection
            ? 'Selected Attendance'
            : 'Attendance Schedule';

        $selectedEventSessions =
            $attendee->eventSessions ?? collect();

        $selectedSessionsByDay =
            $selectedEventSessions->groupBy('event_day_id');

        $merchandiseSelections =
            $attendee->merchandiseSelections ?? collect();

        $paymentTotal = (float) $merchandiseSelections->sum(
            fn ($selection) =>
                (float) ($selection->total_price ?? 0)
        );

        $paymentCurrency =
            $merchandiseSelections
                ->firstWhere('currency', '!=', null)
                ?->currency
            ?? 'TZS';

        $paymentRequired = $paymentTotal > 0;

        $paymentStatus = $paymentRequired
            ? strtoupper(
                str_replace(
                    '_',
                    ' ',
                    (string) (
                        $merchandiseSelections
                            ->pluck('payment_status')
                            ->filter()
                            ->first()
                        ?? 'pending'
                    )
                )
            )
            : 'NOT REQUIRED';
    @endphp

    <title>{{ $successTitle }} - {{ $event->name }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        :root {
            --elive-primary: {{ $branding['primary_color'] }};
            --elive-button: {{ $branding['button_color'] }};
            --elive-background: {{ $branding['background_color'] }};
            --elive-text: #0f172a;
            --elive-muted: #64748b;
            --elive-border: #e2e8f0;
            --elive-soft: #f8fafc;
            --elive-success: #16a34a;
            --elive-warning: #d97706;
            --elive-info: #2563eb;
            --elive-danger: #dc2626;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background:
                radial-gradient(
                    circle at top left,
                    color-mix(in srgb, var(--elive-primary) 13%, transparent),
                    transparent 36%
                ),
                linear-gradient(
                    180deg,
                    #f8fafc 0%,
                    var(--elive-background) 46%,
                    #eef2f7 100%
                );
            color: var(--elive-text);
            font-family:
                Inter,
                ui-sans-serif,
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        .page {
            min-height: 100vh;
            padding: 36px 18px;
        }

        .container {
            width: min(100%, 920px);
            margin: 0 auto;
        }

        .shell {
            overflow: hidden;
            background: rgba(255, 255, 255, 0.97);
            border: 1px solid rgba(226, 232, 240, 0.95);
            border-radius: 28px;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.13);
            backdrop-filter: blur(12px);
        }

        .top-bar {
            height: 10px;
            background:
                linear-gradient(
                    90deg,
                    var(--elive-primary),
                    color-mix(in srgb, var(--elive-primary) 68%, #0f172a)
                );
        }

        .content {
            padding: 36px;
        }

        .hero {
            text-align: center;
        }

        .logo {
            width: 82px;
            height: 82px;
            object-fit: contain;
            margin-bottom: 18px;
            padding: 8px;
            border: 1px solid var(--elive-border);
            border-radius: 20px;
            background: #ffffff;
            box-shadow: 0 10px 24px rgba(15, 23, 42, 0.08);
        }

        .status-icon {
            width: 76px;
            height: 76px;
            margin: 0 auto 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 999px;
            font-size: 38px;
            font-weight: 900;
        }

        .status-icon.success {
            background: #dcfce7;
            color: #166534;
        }

        .status-icon.warning {
            background: #ffedd5;
            color: #9a3412;
        }

        .status-icon.info {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .status-icon.danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-icon.neutral {
            background: #f1f5f9;
            color: #334155;
        }

        h1 {
            margin: 0;
            color: var(--elive-primary);
            font-size: clamp(30px, 5vw, 42px);
            line-height: 1.15;
            letter-spacing: -0.03em;
            font-weight: 900;
        }

        .lead {
            max-width: 620px;
            margin: 12px auto 0;
            color: #475569;
            font-size: 16px;
            line-height: 1.7;
        }

        .notice {
            margin-top: 24px;
            padding: 17px 18px;
            border-radius: 16px;
            text-align: left;
            font-weight: 700;
            line-height: 1.55;
        }

        .notice.success {
            background: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
        }

        .notice.warning {
            background: #fff7ed;
            color: #9a3412;
            border: 1px solid #fed7aa;
        }

        .notice.info {
            background: #eff6ff;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
        }

        .notice.danger {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .notice.neutral {
            background: #f8fafc;
            color: #334155;
            border: 1px solid var(--elive-border);
        }

        .section {
            margin-top: 22px;
            padding: 22px;
            border: 1px solid var(--elive-border);
            border-radius: 20px;
            background: #ffffff;
            box-shadow: 0 10px 26px rgba(15, 23, 42, 0.07);
            text-align: left;
        }

        .section-title {
            position: relative;
            margin: 0 0 18px;
            padding-left: 16px;
            color: var(--elive-primary);
            font-size: 20px;
            font-weight: 900;
            letter-spacing: -0.02em;
        }

        .section-title::before {
            content: "";
            position: absolute;
            top: 2px;
            bottom: 2px;
            left: 0;
            width: 5px;
            border-radius: 999px;
            background: var(--elive-primary);
        }

        .summary-grid {
            display: grid;
            grid-template-columns:
                repeat(auto-fit, minmax(190px, 1fr));
            gap: 12px;
        }

        .summary-card {
            position: relative;
            overflow: hidden;
            min-height: 94px;
            padding: 16px;
            border: 1px solid var(--elive-border);
            border-radius: 16px;
            background:
                linear-gradient(
                    145deg,
                    #ffffff 0%,
                    #f8fafc 100%
                );
        }

        .summary-card::before {
            content: "";
            position: absolute;
            inset: 0 auto 0 0;
            width: 4px;
            background: var(--elive-primary);
            opacity: 0.8;
        }

        .summary-label {
            color: var(--elive-muted);
            font-size: 10px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        .summary-value {
            margin-top: 7px;
            color: var(--elive-text);
            font-size: 15px;
            font-weight: 800;
            line-height: 1.45;
            word-break: break-word;
        }

        .status-pill {
            display: inline-flex;
            margin-top: 7px;
            padding: 7px 10px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 900;
        }

        .status-pill.success {
            background: #dcfce7;
            color: #166534;
        }

        .status-pill.warning {
            background: #ffedd5;
            color: #9a3412;
        }

        .status-pill.info {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .status-pill.danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-pill.neutral {
            background: #f1f5f9;
            color: #334155;
        }

        .list {
            display: grid;
            gap: 10px;
        }

        .list-item {
            padding: 14px;
            border: 1px solid var(--elive-border);
            border-radius: 14px;
            background: var(--elive-soft);
        }

        .list-item-title {
            color: var(--elive-text);
            font-size: 14px;
            font-weight: 900;
        }

        .list-item-meta {
            margin-top: 5px;
            color: var(--elive-muted);
            font-size: 13px;
            line-height: 1.5;
        }

        .session-day-group {
            display: grid;
            gap: 10px;
        }

        .session-day-group + .session-day-group {
            margin-top: 16px;
        }

        .session-day-heading {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            padding: 11px 13px;
            border: 1px solid color-mix(
                in srgb,
                var(--elive-primary) 20%,
                var(--elive-border)
            );
            border-radius: 13px;
            background: color-mix(
                in srgb,
                var(--elive-primary) 5%,
                #ffffff
            );
        }

        .session-day-name {
            color: var(--elive-primary);
            font-size: 14px;
            font-weight: 900;
        }

        .session-day-date {
            margin-top: 3px;
            color: var(--elive-muted);
            font-size: 12px;
        }

        .session-card {
            padding: 14px;
            border: 1px solid var(--elive-border);
            border-radius: 14px;
            background: #ffffff;
        }

        .session-card + .session-card {
            margin-top: 10px;
        }

        .session-name {
            color: var(--elive-text);
            font-size: 14px;
            font-weight: 900;
        }

        .session-type {
            display: inline-flex;
            margin-top: 6px;
            padding: 4px 8px;
            border-radius: 999px;
            background: #eef2ff;
            color: #3730a3;
            font-size: 10px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .session-meta {
            margin-top: 7px;
            color: var(--elive-muted);
            font-size: 12px;
            line-height: 1.55;
        }

        .order-row {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 14px;
            align-items: start;
            padding: 14px 0;
            border-bottom: 1px solid var(--elive-border);
        }

        .order-row:last-child {
            padding-bottom: 0;
            border-bottom: 0;
        }

        .order-name {
            font-weight: 900;
        }

        .order-meta {
            margin-top: 5px;
            color: var(--elive-muted);
            font-size: 13px;
            line-height: 1.5;
        }

        .order-total {
            white-space: nowrap;
            font-weight: 900;
        }

        .payment-box {
            padding: 16px;
            border: 1px solid color-mix(
                in srgb,
                var(--elive-primary) 24%,
                var(--elive-border)
            );
            border-radius: 16px;
            background:
                linear-gradient(
                    145deg,
                    color-mix(
                        in srgb,
                        var(--elive-primary) 5%,
                        #ffffff
                    ),
                    #ffffff
                );
        }

        .payment-line {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            align-items: center;
        }

        .payment-line + .payment-line {
            margin-top: 10px;
        }

        .payment-label {
            color: var(--elive-muted);
            font-size: 13px;
        }

        .payment-value {
            color: var(--elive-text);
            font-weight: 900;
        }

        .capacity-bar {
            height: 10px;
            margin-top: 12px;
            overflow: hidden;
            border-radius: 999px;
            background: #e2e8f0;
        }

        .capacity-progress {
            height: 100%;
            border-radius: 999px;
            background: var(--elive-primary);
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 12px;
            margin-top: 24px;
        }

        .button {
            min-height: 46px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 18px;
            border: 0;
            border-radius: 14px;
            text-decoration: none;
            font: inherit;
            font-weight: 900;
            cursor: pointer;
            transition:
                transform 150ms ease,
                box-shadow 150ms ease,
                opacity 150ms ease;
        }

        .button:hover {
            transform: translateY(-1px);
        }

        .button-primary {
            color: #ffffff;
            background:
                linear-gradient(
                    135deg,
                    var(--elive-button),
                    color-mix(
                        in srgb,
                        var(--elive-button) 78%,
                        #0f172a
                    )
                );
            box-shadow: 0 12px 26px color-mix(
                in srgb,
                var(--elive-button) 28%,
                transparent
            );
        }

        .button-secondary {
            color: #ffffff;
            background: #334155;
        }

        .button-success {
            color: #ffffff;
            background: #16a34a;
        }

        .button-light {
            color: #334155;
            background: #f8fafc;
            border: 1px solid var(--elive-border);
        }

        .copy-feedback {
            margin-top: 10px;
            min-height: 18px;
            color: #047857;
            font-size: 12px;
            font-weight: 800;
            text-align: center;
        }

        .footer {
            margin-top: 26px;
            color: var(--elive-muted);
            font-size: 12px;
            line-height: 1.6;
            text-align: center;
        }

        @media (max-width: 700px) {
            .page {
                padding: 14px 10px 22px;
            }

            .shell {
                border-radius: 20px;
            }

            .content {
                padding: 24px 16px;
            }

            .section {
                padding: 18px;
                border-radius: 18px;
            }

            .summary-grid {
                grid-template-columns: 1fr;
            }

            .order-row {
                grid-template-columns: 1fr;
            }

            .actions {
                display: grid;
                grid-template-columns: 1fr;
            }

            .button {
                width: 100%;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                transition: none !important;
            }
        }
    </style>
</head>

<body>
    <div class="page">
        <div class="container">
            <div class="shell">
                <div class="top-bar"></div>

                <div class="content">
                    <div class="hero">
                        @if ($branding['logo'])
                            <img
                                class="logo"
                                src="{{ asset('storage/' . $branding['logo']) }}"
                                alt="{{ $event->name }} logo"
                            >
                        @endif

                        <div class="status-icon {{ $statusTone }}">
                            @switch($statusTone)
                                @case('success')
                                    ✓
                                    @break

                                @case('warning')
                                    !
                                    @break

                                @case('info')
                                    i
                                    @break

                                @case('danger')
                                    ×
                                    @break

                                @default
                                    ✓
                            @endswitch
                        </div>

                        <h1>{{ $successTitle }}</h1>

                        <p class="lead">
                            {{ $successMessage }}
                        </p>

                        <div class="notice {{ $statusTone }}">
                            @switch($attendee->status)
                                @case('pending_approval')
                                    Your registration is pending approval.
                                    Use the registration-status button below to
                                    check the approval and badge status later.
                                    @break

                                @case('waitlisted')
                                    You are currently on the waitlist.
                                    Use the registration-status button below to
                                    check for updates later.
                                    @break

                                @case('rejected')
                                    This registration is not approved.
                                    Contact the organizer for clarification.
                                    @break

                                @case('cancelled')
                                    This registration has been cancelled.
                                    Contact the organizer when assistance is needed.
                                    @break

                                @default
                                    Your registration is confirmed.
                                    Your badge and check-in QR code will be available
                                    through your private registration page.
                            @endswitch
                        </div>
                    </div>

                    <section class="section">
                        <h2 class="section-title">
                            Registration Summary
                        </h2>

                        <div class="summary-grid">
                            <div class="summary-card">
                                <div class="summary-label">Attendee</div>
                                <div class="summary-value">
                                    {{ $attendee->full_name }}
                                </div>
                            </div>

                            <div class="summary-card">
                                <div class="summary-label">Event</div>
                                <div class="summary-value">
                                    {{ $event->name }}
                                </div>
                            </div>

                            <div class="summary-card">
                                <div class="summary-label">Status</div>
                                <div class="status-pill {{ $statusTone }}">
                                    {{ $statusLabel }}
                                </div>
                            </div>

                            <div class="summary-card">
                                <div class="summary-label">
                                    Badge Number
                                </div>

                                <div class="summary-value">
                                    {{ $attendee->badge_number ?: 'Will be generated' }}
                                </div>
                            </div>

                            @if ($event->starts_at)
                                <div class="summary-card">
                                    <div class="summary-label">
                                        {{ $event->isMultiDay() ? 'Event Period' : 'Event Date' }}
                                    </div>

                                    <div class="summary-value">
                                        @if (
                                            $event->isMultiDay()
                                            && $event->ends_at
                                        )
                                            {{ $event->starts_at->format('d M Y, H:i') }}
                                            <br>
                                            to
                                            {{ $event->ends_at->format('d M Y, H:i') }}
                                        @else
                                            {{ $event->starts_at->format('d M Y, H:i') }}
                                        @endif
                                    </div>
                                </div>
                            @endif

                            @if ($event->venue)
                                <div class="summary-card">
                                    <div class="summary-label">Venue</div>
                                    <div class="summary-value">
                                        {{ $event->venue }}
                                    </div>
                                </div>
                            @endif

                            @if ($attendee->phone)
                                <div class="summary-card">
                                    <div class="summary-label">Phone</div>
                                    <div class="summary-value">
                                        {{ $attendee->phone }}
                                    </div>
                                </div>
                            @endif

                            @if ($attendee->email)
                                <div class="summary-card">
                                    <div class="summary-label">Email</div>
                                    <div class="summary-value">
                                        {{ $attendee->email }}
                                    </div>
                                </div>
                            @endif
                        </div>
                    </section>

                    @if ($selectedEventDays->isNotEmpty())
                        <section class="section">
                            <div style="
                                display:flex;
                                justify-content:space-between;
                                align-items:flex-start;
                                gap:14px;
                                flex-wrap:wrap;
                                margin-bottom:18px;
                            ">
                                <div>
                                    <h2 class="section-title" style="margin-bottom:0;">
                                        {{ $attendanceSectionTitle }}
                                    </h2>

                                    <div class="list-item-meta" style="margin-top:8px;">
                                        {{ $selectedEventDays->count() }}
                                        {{ \Illuminate\Support\Str::plural('event day', $selectedEventDays->count()) }}
                                        {{ $allowDaySelection ? 'selected' : 'included in your registration' }}
                                    </div>
                                </div>

                                @if (
                                    $allowAllDaysSelection
                                    && $selectedAllDays
                                )
                                    <span class="status-pill success" style="margin-top:0;">
                                        ALL EVENT DAYS
                                    </span>
                                @elseif (
                                    ! $allowDaySelection
                                    && $autoAssignedDays
                                )
                                    <span class="status-pill info" style="margin-top:0;">
                                        AUTOMATICALLY ASSIGNED
                                    </span>
                                @endif
                            </div>

                            <div class="list">
                                @foreach ($selectedEventDays as $day)
                                    <div class="list-item">
                                        <div class="list-item-title">
                                            {{ $day->name }}
                                        </div>

                                        <div class="list-item-meta">
                                            {{ $day->event_date?->format('d M Y') ?: 'Date to be announced' }}

                                            @if ($day->starts_at)
                                                — {{ $day->starts_at?->format('H:i') }}
                                            @endif

                                            @if ($day->ends_at)
                                                to {{ $day->ends_at?->format('H:i') }}
                                            @endif

                                            @if (filled($day->venue_name))
                                                <br>
                                                Venue: {{ $day->venue_name }}
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endif

                    @if (
                        $allowSessionRegistration
                        && $selectedEventSessions->isNotEmpty()
                    )
                        <section class="section">
                            <div style="
                                display:flex;
                                justify-content:space-between;
                                align-items:flex-start;
                                gap:14px;
                                flex-wrap:wrap;
                                margin-bottom:18px;
                            ">
                                <div>
                                    <h2 class="section-title" style="margin-bottom:0;">
                                        Selected Sessions / Activities
                                    </h2>

                                    <div class="list-item-meta" style="margin-top:8px;">
                                        {{ $selectedEventSessions->count() }}
                                        {{ \Illuminate\Support\Str::plural(
                                            'session',
                                            $selectedEventSessions->count()
                                        ) }}
                                        selected during registration
                                    </div>
                                </div>
                            </div>

                            @foreach ($selectedEventDays as $day)
                                @php
                                    $daySessions = $selectedSessionsByDay
                                        ->get($day->id, collect());
                                @endphp

                                @if ($daySessions->isNotEmpty())
                                    <div class="session-day-group">
                                        <div class="session-day-heading">
                                            <div>
                                                <div class="session-day-name">
                                                    {{ $day->name }}
                                                </div>

                                                <div class="session-day-date">
                                                    {{ $day->event_date?->format('d M Y')
                                                        ?: 'Date to be announced' }}
                                                </div>
                                            </div>

                                            <strong style="
                                                color:#475569;
                                                font-size:11px;
                                            ">
                                                {{ $daySessions->count() }}
                                                {{ \Illuminate\Support\Str::plural(
                                                    'session',
                                                    $daySessions->count()
                                                ) }}
                                            </strong>
                                        </div>

                                        <div>
                                            @foreach ($daySessions as $session)
                                                <div class="session-card">
                                                    <div class="session-name">
                                                        {{ $session->name }}
                                                    </div>

                                                    <span class="session-type">
                                                        {{ ucwords(
                                                            str_replace(
                                                                '_',
                                                                ' ',
                                                                $session->session_type
                                                                    ?: 'session'
                                                            )
                                                        ) }}
                                                    </span>

                                                    <div class="session-meta">
                                                        @if ($session->starts_at)
                                                            {{ $session->starts_at->format('H:i') }}
                                                        @endif

                                                        @if ($session->ends_at)
                                                            @if ($session->starts_at)
                                                                –
                                                            @endif
                                                            {{ $session->ends_at->format('H:i') }}
                                                        @endif

                                                        @if (filled($session->venue_name))
                                                            @if ($session->starts_at || $session->ends_at)
                                                                <br>
                                                            @endif

                                                            Venue: {{ $session->venue_name }}
                                                        @endif

                                                        @if (filled($session->description))
                                                            <br>
                                                            {{ \Illuminate\Support\Str::limit(
                                                                $session->description,
                                                                150
                                                            ) }}
                                                        @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </section>
                    @endif

                    @if ($merchandiseSelections->isNotEmpty())
                        <section class="section">
                            <h2 class="section-title">
                                Merchandise Order
                            </h2>

                            @foreach ($merchandiseSelections as $selection)
                                <div class="order-row">
                                    <div>
                                        <div class="order-name">
                                            {{ $selection->merchandise?->name ?: 'Merchandise Item' }}
                                        </div>

                                        <div class="order-meta">
                                            @php
                                                $variantName =
                                                    $selection->variant
                                                        && method_exists(
                                                            $selection->variant,
                                                            'displayName'
                                                        )
                                                        ? $selection->variant->displayName()
                                                        : (
                                                            $selection->variant?->name
                                                            ?: 'Standard'
                                                        );
                                            @endphp

                                            Variant: {{ $variantName }}<br>
                                            Quantity: {{ $selection->quantity }}<br>
                                            Status:
                                            {{ strtoupper(str_replace('_', ' ', (string) $selection->status)) }}
                                        </div>
                                    </div>

                                    <div class="order-total">
                                        {{ $selection->currency ?: 'TZS' }}
                                        {{ number_format((float) $selection->total_price, 2) }}
                                    </div>
                                </div>
                            @endforeach
                        </section>

                        <section class="section">
                            <h2 class="section-title">
                                Payment Summary
                            </h2>

                            <div class="payment-box">
                                <div class="payment-line">
                                    <span class="payment-label">
                                        Amount payable
                                    </span>

                                    <span class="payment-value">
                                        @if ($paymentRequired)
                                            {{ $paymentCurrency }}
                                            {{ number_format($paymentTotal, 2) }}
                                        @else
                                            No payment required
                                        @endif
                                    </span>
                                </div>

                                <div class="payment-line">
                                    <span class="payment-label">
                                        Payment status
                                    </span>

                                    <span class="payment-value">
                                        {{ $paymentStatus }}
                                    </span>
                                </div>

                                @if ($paymentRequired)
                                    <div class="list-item-meta" style="margin-top:12px;">
                                        Payment instructions will be provided
                                        by the event organizer.
                                    </div>
                                @endif
                            </div>
                        </section>
                    @endif

                    @if (! empty($registrationStats['capacity']))
                        @php
                            $capacity = max(
                                1,
                                (int) $registrationStats['capacity']
                            );

                            $accepted = min(
                                $capacity,
                                (int) $registrationStats['accepted']
                            );

                            $percentage = round(
                                ($accepted / $capacity) * 100
                            );
                        @endphp

                        <section class="section">
                            <h2 class="section-title">
                                Registration Capacity
                            </h2>

                            <div class="payment-line">
                                <span class="payment-label">
                                    Confirmed or pending attendees
                                </span>

                                <span class="payment-value">
                                    {{ $accepted }} / {{ $capacity }}
                                </span>
                            </div>

                            <div class="capacity-bar">
                                <div
                                    class="capacity-progress"
                                    style="width:{{ $percentage }}%;"
                                ></div>
                            </div>

                            <div class="list-item-meta">
                                Remaining slots:
                                {{ $registrationStats['remaining'] ?? 0 }}
                            </div>
                        </section>
                    @endif

                    <div class="actions">
                        @if ($canViewBadge)
                            <a
                                class="button button-primary"
                                href="{{ $registrationUrl }}"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                View My Badge
                            </a>
                        @elseif ($registrationUrl)
                            <a
                                class="button button-primary"
                                href="{{ $registrationUrl }}"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Check Registration Status
                            </a>
                        @endif

                        @if ($registrationUrl)
                            <button
                                type="button"
                                class="button button-light"
                                data-copy-registration-link
                                data-registration-url="{{ $registrationUrl }}"
                            >
                                Copy Status Link
                            </button>
                        @endif

                        @if ($branding['support_phone'])
                            <a
                                class="button button-success"
                                href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $branding['support_phone']) }}"
                                target="_blank"
                                rel="noopener noreferrer"
                            >
                                Contact Support
                            </a>
                        @elseif ($branding['support_email'])
                            <a
                                class="button button-secondary"
                                href="mailto:{{ $branding['support_email'] }}"
                            >
                                Contact Support
                            </a>
                        @endif
                    </div>

                    <div
                        class="copy-feedback"
                        data-copy-feedback
                        aria-live="polite"
                    ></div>

                    <div class="footer">
                        Powered by eLive Events
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const copyButton = document.querySelector(
                '[data-copy-registration-link]'
            );

            const feedback = document.querySelector(
                '[data-copy-feedback]'
            );

            copyButton?.addEventListener('click', async function () {
                const url = copyButton.dataset.registrationUrl;

                if (!url) {
                    return;
                }

                try {
                    await navigator.clipboard.writeText(url);

                    if (feedback) {
                        feedback.textContent =
                            'Registration-status link copied.';
                    }
                } catch (error) {
                    if (feedback) {
                        feedback.textContent =
                            'Copy failed. Open your registration page and save the link manually.';
                    }
                }
            });
        });
    </script>
</body>
</html>
