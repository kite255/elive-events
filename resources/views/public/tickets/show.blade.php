@php
    $eventStart =
        $event?->starts_at;

    $eventEnd =
        $event?->ends_at;
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
        {{ $ticket->ticket_number }}
        - {{ $event?->name ?? 'eLive Events' }}
    </title>

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
            --used: #A16207;
            --used-bg: #FEFCE8;
        }

        * {
            box-sizing: border-box;
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
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .container {
            width: min(
                760px,
                calc(100% - 30px)
            );

            margin-inline: auto;
        }

        .header {
            padding: 24px 0 14px;
        }

        .header-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }

        .brand {
            color: var(--elive-navy);
            font-size: 20px;
            font-weight: 900;
        }

        .back-link {
            color: var(--elive-blue);
            font-size: 13px;
            font-weight: 800;
        }

        .ticket-wrapper {
            padding: 8px 0 60px;
        }

        .ticket {
            overflow: hidden;

            border:
                1px solid var(--border);

            border-radius: 26px;
            background: #FFFFFF;

            box-shadow:
                0 20px 55px
                rgba(22, 25, 67, .10);
        }

        .ticket-top {
            position: relative;
            overflow: hidden;

            padding:
                30px
                30px
                28px;

            background:
                linear-gradient(
                    135deg,
                    var(--elive-navy),
                    #22295F
                );

            color: #FFFFFF;
        }

        .ticket-top::before {
            content: '';

            position: absolute;

            width: 250px;
            height: 250px;

            right: -110px;
            top: -120px;

            border-radius: 50%;

            background:
                rgba(0, 122, 178, .24);
        }

        .ticket-top-content {
            position: relative;
            z-index: 2;
        }

        .eyebrow {
            margin: 0 0 9px;

            color: #9DDCF7;

            font-size: 12px;
            font-weight: 900;

            letter-spacing: .10em;
            text-transform: uppercase;
        }

        .event-name {
            margin: 0;

            font-size:
                clamp(
                    28px,
                    7vw,
                    42px
                );

            line-height: 1.08;
            font-weight: 900;
        }

        .ticket-type {
            margin-top: 12px;

            color:
                rgba(255, 255, 255, .82);

            font-size: 18px;
            font-weight: 800;
        }

        .ticket-body {
            padding: 28px;
        }

        .status-row {
            display: flex;
            align-items: center;
            justify-content: space-between;

            gap: 18px;
            margin-bottom: 22px;
        }

        .status {
            display: inline-flex;

            padding:
                7px
                11px;

            border-radius: 999px;

            font-size: 11px;
            font-weight: 900;

            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .status-issued {
            color: var(--success);
            background: var(--success-bg);
        }

        .status-used {
            color: var(--used);
            background: var(--used-bg);
        }

        .ticket-number {
            color: var(--elive-navy);

            font-size: 13px;
            font-weight: 900;

            text-align: right;
            overflow-wrap: anywhere;
        }

        .qr-section {
            display: flex;
            align-items: center;
            justify-content: center;

            padding:
                26px
                10px;

            border:
                1px solid var(--border);

            border-radius: 18px;
            background: #FFFFFF;
        }

        .qr-code {
            display: flex;
            align-items: center;
            justify-content: center;

            width: 100%;
            max-width: 340px;
        }

        .qr-code svg {
            display: block;

            width: 100%;
            height: auto;

            max-width: 320px;
        }

        .scan-note {
            margin:
                14px auto
                0;

            max-width: 470px;

            color: var(--muted);

            font-size: 13px;
            line-height: 1.6;

            text-align: center;
        }

        .details {
            display: grid;

            grid-template-columns:
                repeat(
                    2,
                    minmax(0, 1fr)
                );

            gap: 13px;
            margin-top: 24px;
        }

        .detail {
            padding: 15px;

            border-radius: 13px;

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
            color: var(--elive-navy);

            font-size: 14px;
            font-weight: 900;

            overflow-wrap: anywhere;
        }

        .notice {
            margin-top: 22px;

            padding: 15px;

            border-radius: 13px;

            background: #EFF8FF;
            color: #075985;

            font-size: 13px;
            line-height: 1.6;
        }

        .footer {
            padding: 20px 0 34px;

            color: #98A2B3;

            font-size: 12px;
            text-align: center;
        }

        @media (
            max-width: 620px
        ) {
            .ticket-top {
                padding: 25px 20px;
            }

            .ticket-body {
                padding: 20px;
            }

            .details {
                grid-template-columns: 1fr;
            }

            .status-row {
                align-items: flex-start;
                flex-direction: column;
            }

            .ticket-number {
                text-align: left;
            }
        }

        @media print {
            body {
                background: #FFFFFF;
            }

            .header,
            .footer {
                display: none;
            }

            .ticket-wrapper {
                padding: 0;
            }

            .ticket {
                box-shadow: none;
            }
        }
    </style>
</head>

<body>

<header class="header">
    <div class="container header-inner">

        <a
            href="{{ route('home') }}"
            class="brand"
        >
            eLive Events
        </a>

        <a
            href="{{
                route(
                    'public.ticket-orders.show',
                    [
                        'token' =>
                            $order->public_token,
                    ]
                )
            }}"
            class="back-link"
        >
            My Tickets
        </a>

    </div>
</header>

<main class="ticket-wrapper">
    <div class="container">

        <article class="ticket">

            <section class="ticket-top">

                <div class="ticket-top-content">

                    <p class="eyebrow">
                        Official Event Ticket
                    </p>

                    <h1 class="event-name">
                        {{ $event?->name }}
                    </h1>

                    <div class="ticket-type">
                        {{
                            $ticket
                                ->ticketType
                                ?->name
                            ?? 'Event Ticket'
                        }}
                    </div>

                </div>

            </section>

            <section class="ticket-body">

                <div class="status-row">

                    <span
                        class="
                            status
                            {{
                                $ticket->isUsed()
                                    ? 'status-used'
                                    : 'status-issued'
                            }}
                        "
                    >
                        {{ $ticket->status }}
                    </span>

                    <div class="ticket-number">
                        {{ $ticket->ticket_number }}
                    </div>

                </div>

                <div class="qr-section">

                    <div class="qr-code">
                        {!! $qrCode !!}
                    </div>

                </div>

                <p class="scan-note">
                    Present this QR code at the event entrance.
                    Each ticket has a unique secure credential.
                </p>

                <div class="details">

                    <div class="detail">

                        <span class="detail-label">
                            Ticket Holder
                        </span>

                        <span class="detail-value">
                            {{ $ticket->holder_name }}
                        </span>

                    </div>

                    <div class="detail">

                        <span class="detail-label">
                            Ticket Type
                        </span>

                        <span class="detail-value">
                            {{
                                $ticket
                                    ->ticketType
                                    ?->name
                                ?? 'Event Ticket'
                            }}
                        </span>

                    </div>

                    @if ($eventStart)
                        <div class="detail">

                            <span class="detail-label">
                                Event Date
                            </span>

                            <span class="detail-value">
                                {{
                                    $eventStart
                                        ->format(
                                            'd M Y'
                                        )
                                }}
                            </span>

                        </div>

                        <div class="detail">

                            <span class="detail-label">
                                Event Time
                            </span>

                            <span class="detail-value">
                                {{
                                    $eventStart
                                        ->format(
                                            'H:i'
                                        )
                                }}

                                @if ($eventEnd)
                                    -
                                    {{
                                        $eventEnd
                                            ->format(
                                                'H:i'
                                            )
                                    }}
                                @endif
                            </span>

                        </div>
                    @endif

                    @if (
                        $event
                        && filled($event->venue)
                    )
                        <div class="detail">

                            <span class="detail-label">
                                Venue
                            </span>

                            <span class="detail-value">
                                {{ $event->venue }}
                            </span>

                        </div>
                    @endif

                    <div class="detail">

                        <span class="detail-label">
                            Price
                        </span>

                        <span class="detail-value">
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

                </div>

                <div class="notice">
                    Do not share your QR code publicly.
                    The first valid scan will be used for
                    entry verification when ticket check-in
                    is enabled.
                </div>

            </section>

        </article>

    </div>
</main>

<footer class="footer">
    <div class="container">
        Powered by eLive Events
    </div>
</footer>

</body>
</html>