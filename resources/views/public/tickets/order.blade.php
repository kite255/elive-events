@php
    use Illuminate\Support\Carbon;

    $eventStart =
        $event?->starts_at
            ? Carbon::parse($event->starts_at)
            : null;

    $eventEnd =
        $event?->ends_at
            ? Carbon::parse($event->ends_at)
            : null;
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="robots"
        content="noindex,nofollow"
    >

    <title>
        My Tickets - {{ $event?->name ?? 'eLive Events' }}
    </title>

    @if (
        file_exists(public_path('build/manifest.json'))
        || file_exists(public_path('hot'))
    )
        @vite([
            'resources/css/app.css',
            'resources/js/app.js',
        ])
    @endif

    <style>
        :root {
            --elive-navy: #161943;
            --elive-blue: #007AB2;
            --elive-orange: #FF9800;

            --background: #F6F8FC;
            --surface: #FFFFFF;
            --border: #E6EBF2;

            --text: #101828;
            --muted: #667085;

            --success: #15803D;
            --success-bg: #ECFDF3;

            --info: #0369A1;
            --info-bg: #EFF8FF;
        }

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;

            min-height: 100vh;

            background: var(--background);
            color: var(--text);

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

        a {
            color: inherit;
            text-decoration: none;
        }

        .container {
            width: min(
                1040px,
                calc(100% - 32px)
            );

            margin-inline: auto;
        }

        /*
        |--------------------------------------------------------------------------
        | Header
        |--------------------------------------------------------------------------
        */

        .site-header {
            background: #FFFFFF;

            border-bottom:
                1px solid var(--border);
        }

        .header-inner {
            min-height: 72px;

            display: flex;
            align-items: center;
            justify-content: space-between;

            gap: 20px;
        }

        .brand {
            display: inline-flex;
            align-items: center;

            color: var(--elive-navy);

            font-size: 22px;
            font-weight: 900;
        }

        .event-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            min-height: 40px;

            padding:
                8px
                14px;

            border:
                1px solid var(--border);

            border-radius: 10px;

            color: var(--elive-blue);

            font-size: 13px;
            font-weight: 800;

            transition:
                transform .15s ease,
                border-color .15s ease,
                box-shadow .15s ease;
        }

        .event-link:hover {
            transform: translateY(-1px);

            border-color:
                rgba(0, 122, 178, .35);

            box-shadow:
                0 7px 20px
                rgba(22, 25, 67, .06);
        }

        /*
        |--------------------------------------------------------------------------
        | Hero
        |--------------------------------------------------------------------------
        */

        .hero {
            padding:
                40px 0
                22px;
        }

        .hero-card {
            position: relative;

            overflow: hidden;

            padding: 34px;

            border-radius: 24px;

            background:
                linear-gradient(
                    135deg,
                    var(--elive-navy) 0%,
                    #22295F 68%,
                    #283372 100%
                );

            color: #FFFFFF;

            box-shadow:
                0 18px 48px
                rgba(22, 25, 67, .15);
        }

        .hero-card::before {
            content: '';

            position: absolute;

            width: 280px;
            height: 280px;

            right: -100px;
            top: -130px;

            border-radius: 50%;

            background:
                rgba(0, 122, 178, .24);
        }

        .hero-card::after {
            content: '';

            position: absolute;

            width: 180px;
            height: 180px;

            right: 80px;
            bottom: -130px;

            border-radius: 50%;

            background:
                rgba(255, 152, 0, .12);
        }

        .hero-content {
            position: relative;

            z-index: 2;
        }

        .eyebrow {
            margin: 0 0 10px;

            color: #98DCF9;

            font-size: 12px;
            font-weight: 900;

            letter-spacing: .11em;
            text-transform: uppercase;
        }

        h1 {
            margin: 0;

            font-size: clamp(
                34px,
                7vw,
                52px
            );

            line-height: 1.05;

            letter-spacing: -.025em;
        }

        .hero-event-name {
            margin:
                13px 0
                0;

            max-width: 680px;

            color:
                rgba(255, 255, 255, .83);

            font-size: 18px;
            font-weight: 700;

            line-height: 1.5;
        }

        .hero-meta {
            display: flex;
            flex-wrap: wrap;

            gap: 9px;

            margin-top: 23px;
        }

        .hero-meta-item {
            display: inline-flex;
            align-items: center;

            min-height: 32px;

            padding:
                7px
                11px;

            border:
                1px solid
                rgba(255, 255, 255, .13);

            border-radius: 999px;

            background:
                rgba(255, 255, 255, .10);

            color:
                rgba(255, 255, 255, .92);

            font-size: 13px;
            font-weight: 700;
        }

        /*
        |--------------------------------------------------------------------------
        | Content
        |--------------------------------------------------------------------------
        */

        .content {
            padding:
                2px 0
                72px;
        }

        .panel {
            margin-top: 20px;

            padding: 26px;

            border:
                1px solid var(--border);

            border-radius: 20px;

            background: #FFFFFF;

            box-shadow:
                0 8px 28px
                rgba(22, 25, 67, .045);
        }

        .panel-heading {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;

            gap: 20px;
        }

        .panel h2 {
            margin: 0;

            color: var(--elive-navy);

            font-size: 23px;
            line-height: 1.25;
        }

        .panel-description {
            margin:
                7px 0
                0;

            color: var(--muted);

            font-size: 14px;
            line-height: 1.6;
        }

        .paid-badge {
            display: inline-flex;
            align-items: center;

            padding:
                7px
                11px;

            border-radius: 999px;

            background:
                var(--success-bg);

            color:
                var(--success);

            font-size: 11px;
            font-weight: 900;

            letter-spacing: .04em;
            text-transform: uppercase;

            white-space: nowrap;
        }

        /*
        |--------------------------------------------------------------------------
        | Order Details
        |--------------------------------------------------------------------------
        */

        .order-grid {
            display: grid;

            grid-template-columns:
                repeat(
                    2,
                    minmax(0, 1fr)
                );

            gap: 14px;

            margin-top: 22px;
        }

        .detail {
            padding: 16px;

            border-radius: 14px;

            background: #F8FAFC;

            border:
                1px solid #EEF2F6;
        }

        .detail-label {
            display: block;

            margin-bottom: 5px;

            color: var(--muted);

            font-size: 12px;
            font-weight: 700;
        }

        .detail-value {
            display: block;

            color: var(--elive-navy);

            font-size: 14px;
            font-weight: 900;

            overflow-wrap: anywhere;
        }

        /*
        |--------------------------------------------------------------------------
        | Tickets
        |--------------------------------------------------------------------------
        */

        .ticket-list {
            display: grid;

            gap: 14px;

            margin-top: 22px;
        }

        .ticket-card {
            display: grid;

            grid-template-columns:
                minmax(0, 1fr)
                auto;

            align-items: center;

            gap: 20px;

            padding: 20px;

            border:
                1px solid var(--border);

            border-radius: 17px;

            background:
                linear-gradient(
                    180deg,
                    #FFFFFF 0%,
                    #FBFCFE 100%
                );

            transition:
                transform .15s ease,
                box-shadow .15s ease,
                border-color .15s ease;
        }

        .ticket-card:hover {
            transform: translateY(-1px);

            border-color:
                rgba(0, 122, 178, .26);

            box-shadow:
                0 10px 25px
                rgba(22, 25, 67, .055);
        }

        .ticket-type {
            margin: 0;

            color: var(--elive-navy);

            font-size: 19px;
            font-weight: 900;

            line-height: 1.3;
        }

        .ticket-number {
            margin-top: 7px;

            color: var(--muted);

            font-size: 13px;
            font-weight: 600;

            overflow-wrap: anywhere;
        }

        .ticket-holder {
            margin-top: 10px;

            color: #344054;

            font-size: 14px;
            font-weight: 800;
        }

        .ticket-side {
            display: flex;
            flex-direction: column;
            align-items: flex-end;

            gap: 8px;
        }

        .ticket-status {
            display: inline-flex;
            align-items: center;

            padding:
                7px
                10px;

            border-radius: 999px;

            background:
                var(--success-bg);

            color:
                var(--success);

            font-size: 11px;
            font-weight: 900;

            text-transform: uppercase;

            letter-spacing: .04em;
        }

        .ticket-price {
            color: var(--elive-navy);

            font-size: 14px;
            font-weight: 900;
        }

        /*
        |--------------------------------------------------------------------------
        | Notice
        |--------------------------------------------------------------------------
        */

        .security-note {
            margin-top: 20px;

            padding: 15px 16px;

            border-radius: 14px;

            background:
                var(--info-bg);

            color:
                var(--info);

            font-size: 13px;
            font-weight: 600;

            line-height: 1.65;
        }

        .empty-state {
            padding: 22px;

            border:
                1px dashed #CDD5DF;

            border-radius: 15px;

            background: #FAFBFC;

            color: var(--muted);

            font-size: 14px;
            line-height: 1.7;

            text-align: center;
        }

        /*
        |--------------------------------------------------------------------------
        | Footer
        |--------------------------------------------------------------------------
        */

        .footer {
            padding:
                0 0
                34px;

            text-align: center;

            color: #98A2B3;

            font-size: 12px;
        }

        /*
        |--------------------------------------------------------------------------
        | Responsive
        |--------------------------------------------------------------------------
        */

        @media (
            max-width: 640px
        ) {
            .container {
                width:
                    min(
                        100% - 22px,
                        1040px
                    );
            }

            .header-inner {
                min-height: 64px;
            }

            .brand {
                font-size: 19px;
            }

            .event-link {
                padding:
                    7px
                    10px;

                font-size: 12px;
            }

            .hero {
                padding-top: 20px;
            }

            .hero-card {
                padding:
                    26px
                    20px;
            }

            .panel {
                padding: 20px;
            }

            .panel-heading {
                flex-direction: column;
            }

            .order-grid {
                grid-template-columns:
                    1fr;
            }

            .ticket-card {
                grid-template-columns:
                    1fr;
            }

            .ticket-side {
                align-items: flex-start;
            }
        }
    </style>
</head>

<body>

<header class="site-header">
    <div class="container header-inner">

        <a
            href="{{ route('home') }}"
            class="brand"
        >
            eLive Events
        </a>

        @if (
            $event
            && filled($event->slug)
        )
            <a
                href="{{
                    route(
                        'public.events.show',
                        [
                            'event' =>
                                $event->slug,
                        ]
                    )
                }}"
                class="event-link"
            >
                Event Details
            </a>
        @endif

    </div>
</header>

<main>

    <section class="hero">
        <div class="container">

            <div class="hero-card">

                <div class="hero-content">

                    <p class="eyebrow">
                        Your Purchase
                    </p>

                    <h1>
                        My Tickets
                    </h1>

                    <p class="hero-event-name">
                        {{ $event?->name }}
                    </p>

                    <div class="hero-meta">

                        @if ($eventStart)
                            <span class="hero-meta-item">
                                {{
                                    $eventStart
                                        ->format(
                                            'd M Y'
                                        )
                                }}
                            </span>
                        @endif

                        @if (
                            $eventStart
                            && $eventEnd
                        )
                            <span class="hero-meta-item">
                                {{
                                    $eventStart
                                        ->format(
                                            'H:i'
                                        )
                                }}
                                –
                                {{
                                    $eventEnd
                                        ->format(
                                            'H:i'
                                        )
                                }}
                            </span>
                        @elseif ($eventStart)
                            <span class="hero-meta-item">
                                {{
                                    $eventStart
                                        ->format(
                                            'H:i'
                                        )
                                }}
                            </span>
                        @endif

                        @if (
                            $event
                            && filled($event->venue)
                        )
                            <span class="hero-meta-item">
                                {{ $event->venue }}
                            </span>
                        @endif

                        @if ($event?->organization)
                            <span class="hero-meta-item">
                                {{
                                    $event
                                        ->organization
                                        ->name
                                }}
                            </span>
                        @endif

                    </div>

                </div>

            </div>

        </div>
    </section>

    <section class="content">
        <div class="container">

            <section class="panel">

                <div class="panel-heading">

                    <div>

                        <h2>
                            Order Details
                        </h2>

                        <p class="panel-description">
                            Your payment has been confirmed
                            and your tickets have been issued.
                        </p>

                    </div>

                    <span class="paid-badge">
                        Paid
                    </span>

                </div>

                <div class="order-grid">

                    <div class="detail">

                        <span class="detail-label">
                            Order Number
                        </span>

                        <span class="detail-value">
                            {{ $order->order_number }}
                        </span>

                    </div>

                    <div class="detail">

                        <span class="detail-label">
                            Buyer
                        </span>

                        <span class="detail-value">
                            {{ $order->buyer_name }}
                        </span>

                    </div>

                    <div class="detail">

                        <span class="detail-label">
                            Number of Tickets
                        </span>

                        <span class="detail-value">
                            {{ $order->quantity }}
                        </span>

                    </div>

                    <div class="detail">

                        <span class="detail-label">
                            Total Paid
                        </span>

                        <span class="detail-value">
                            {{
                                strtoupper(
                                    (string) $order->currency
                                )
                            }}

                            {{
                                number_format(
                                    (float) $order->total,
                                    0
                                )
                            }}
                        </span>

                    </div>

                    @if ($order->paid_at)
                        <div class="detail">

                            <span class="detail-label">
                                Payment Date
                            </span>

                            <span class="detail-value">
                                {{
                                    $order
                                        ->paid_at
                                        ->format(
                                            'd M Y, H:i'
                                        )
                                }}
                            </span>

                        </div>
                    @endif

                    @if (
                        filled($order->buyer_email)
                    )
                        <div class="detail">

                            <span class="detail-label">
                                Email
                            </span>

                            <span class="detail-value">
                                {{ $order->buyer_email }}
                            </span>

                        </div>
                    @endif

                </div>

            </section>

            <section class="panel">

                <div class="panel-heading">

                    <div>

                        <h2>
                            Your Tickets
                        </h2>

                        <p class="panel-description">
                            You have
                            {{ $tickets->count() }}
                            issued
                            {{
                                $tickets->count() === 1
                                    ? 'ticket'
                                    : 'tickets'
                            }}.
                        </p>

                    </div>

                </div>

                <div class="ticket-list">

                    @forelse (
                        $tickets
                        as $ticket
                    )

                        <article class="ticket-card">

                            <div>

                                <h3 class="ticket-type">
                                    {{
                                        $ticket
                                            ->ticketType
                                            ?->name
                                        ?? 'Event Ticket'
                                    }}
                                </h3>

                                <div class="ticket-number">
                                    Ticket:
                                    {{ $ticket->ticket_number }}
                                </div>

                                <div class="ticket-holder">
                                    {{ $ticket->holder_name }}
                                </div>

                            </div>

                            <div class="ticket-side">

                                <span class="ticket-status">
                                    {{ $ticket->status }}
                                </span>

                                <span class="ticket-price">
                                    {{
                                        strtoupper(
                                            (string)
                                            $ticket->currency
                                        )
                                    }}

                                    {{
                                        number_format(
                                            (float)
                                            $ticket->price,
                                            0
                                        )
                                    }}
                                </span>

                            </div>

                        </article>

                    @empty

                        <div class="empty-state">
                            Your payment has been confirmed,
                            but ticket issuance is still being
                            completed. Please refresh this page
                            shortly.
                        </div>

                    @endforelse

                </div>

                <div class="security-note">
                    Keep this purchase link private.
                    Each ticket will have its own secure QR
                    credential for entry to the event.
                </div>

            </section>

        </div>
    </section>

</main>

<footer class="footer">
    <div class="container">
        Powered by eLive Events
    </div>
</footer>

</body>
</html>
