@php
    use Illuminate\Support\Carbon;
    use Illuminate\Support\Str;

    $eventStart = $event->starts_at
        ? Carbon::parse($event->starts_at)
        : null;

    $eventEnd = $event->ends_at
        ? Carbon::parse($event->ends_at)
        : null;

    $eventImage = $event->registration_banner_image_path;
    $eventImageUrl = null;

    if ($eventImage) {
        if (
            Str::startsWith(
                $eventImage,
                ['http://', 'https://']
            )
        ) {
            $eventImageUrl = $eventImage;
        } elseif (
            Str::startsWith(
                $eventImage,
                ['storage/', '/storage/']
            )
        ) {
            $eventImageUrl = asset(
                ltrim(
                    $eventImage,
                    '/'
                )
            );
        } else {
            $eventImageUrl = asset(
                'storage/' .
                ltrim(
                    $eventImage,
                    '/'
                )
            );
        }
    }

    $displayCurrency =
        $ticketTypes->first()?->currency
        ?? 'TZS';
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Tickets - {{ $event->name }} | eLive Events
    </title>

    <meta
        name="description"
        content="Buy tickets for {{ $event->name }} securely through eLive Events."
    >

    <link
        rel="icon"
        href="{{ asset('favicon.ico') }}"
    >

    <link
        rel="stylesheet"
        href="{{ asset('css/creato-font.css') }}"
    >

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

            --elive-bg: #F6F8FC;
            --elive-surface: #FFFFFF;
            --elive-border: #E5EAF1;
            --elive-muted: #667085;

            --elive-success: #15803D;
            --elive-success-bg: #ECFDF3;

            --elive-warning: #B54708;
            --elive-warning-bg: #FFFAEB;

            --elive-danger: #B42318;
            --elive-danger-bg: #FEF3F2;
        }

        * {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            margin: 0;

            background:
                linear-gradient(
                    180deg,
                    #FFFFFF 0,
                    var(--elive-bg) 420px
                );

            color: #0F172A;

            font-family:
                'Creato Display',
                ui-sans-serif,
                system-ui,
                sans-serif;

            -webkit-font-smoothing: antialiased;
        }

        button,
        input {
            font: inherit;
        }

        button {
            cursor: pointer;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .container {
            width: min(
                1180px,
                calc(100% - 32px)
            );

            margin-inline: auto;
        }

        .site-header {
            position: sticky;
            top: 0;
            z-index: 50;

            background:
                rgba(255, 255, 255, .94);

            backdrop-filter: blur(12px);

            border-bottom:
                1px solid var(--elive-border);
        }

        .header-inner {
            min-height: 72px;

            display: flex;
            align-items: center;
            justify-content: space-between;

            gap: 20px;
        }

        .brand {
            color: var(--elive-navy);

            font-size: 22px;
            font-weight: 900;
        }

        .back-link {
            color: var(--elive-blue);

            font-size: 14px;
            font-weight: 800;
        }

        .hero {
            padding:
                42px 0
                30px;
        }

        .hero-card {
            overflow: hidden;

            display: grid;

            grid-template-columns:
                minmax(0, 1.25fr)
                minmax(300px, .75fr);

            background: var(--elive-navy);

            border-radius: 26px;

            color: #FFFFFF;

            box-shadow:
                0 18px 55px
                rgba(22, 25, 67, .16);
        }

        .hero-content {
            padding:
                42px
                42px
                38px;
        }

        .eyebrow {
            margin: 0 0 12px;

            color: #80D4FA;

            font-size: 13px;
            font-weight: 900;

            letter-spacing: .1em;
            text-transform: uppercase;
        }

        .hero h1 {
            max-width: 720px;

            margin: 0;

            font-size: clamp(
                32px,
                5vw,
                52px
            );

            line-height: 1.06;
        }

        .hero-meta {
            margin-top: 22px;

            display: flex;
            flex-wrap: wrap;

            gap: 10px;
        }

        .hero-meta span {
            padding:
                8px
                12px;

            border:
                1px solid
                rgba(255, 255, 255, .16);

            border-radius: 999px;

            background:
                rgba(255, 255, 255, .08);

            font-size: 14px;
            font-weight: 700;
        }

        .reservation-notice {
            margin-top: 24px;

            padding:
                14px
                16px;

            border:
                1px solid
                rgba(255, 152, 0, .5);

            border-radius: 14px;

            background:
                rgba(255, 152, 0, .13);

            color: #FFE1B2;

            font-size: 14px;
            line-height: 1.55;
        }

        .hero-visual {
            min-height: 320px;

            background:
                linear-gradient(
                    135deg,
                    var(--elive-blue),
                    #065276
                );
        }

        .hero-image {
            width: 100%;
            height: 100%;

            object-fit: cover;

            display: block;
        }

        .hero-placeholder {
            width: 100%;
            height: 100%;

            min-height: 320px;

            display: grid;
            place-items: center;

            background:
                linear-gradient(
                    135deg,
                    var(--elive-blue),
                    #074E72
                );

            color:
                rgba(255, 255, 255, .92);

            font-size: 28px;
            font-weight: 900;
        }

        .page-content {
            padding:
                18px 0
                76px;
        }

        .checkout-layout {
            display: grid;

            grid-template-columns:
                minmax(0, 1.45fr)
                minmax(320px, .75fr);

            gap: 24px;

            align-items: start;
        }

        .stack {
            display: grid;
            gap: 22px;
        }

        .panel {
            padding: 26px;

            background: var(--elive-surface);

            border:
                1px solid var(--elive-border);

            border-radius: 20px;

            box-shadow:
                0 8px 28px
                rgba(22, 25, 67, .045);
        }

        .panel-title {
            margin: 0;

            color: var(--elive-navy);

            font-size: 25px;
            font-weight: 900;
        }

        .panel-description {
            margin:
                7px 0
                0;

            color: var(--elive-muted);

            font-size: 14px;
            line-height: 1.6;
        }

        .error-box {
            margin-bottom: 22px;

            padding:
                15px
                17px;

            border:
                1px solid #FDA29B;

            border-radius: 14px;

            background:
                var(--elive-danger-bg);

            color:
                var(--elive-danger);
        }

        .error-box strong {
            display: block;

            margin-bottom: 7px;
        }

        .error-box ul {
            margin:
                0 0
                0 18px;

            padding: 0;
        }

        .ticket-list {
            margin-top: 22px;

            display: grid;
            gap: 14px;
        }

        .ticket-card {
            padding: 20px;

            border:
                1px solid var(--elive-border);

            border-radius: 16px;

            transition:
                border-color .15s ease,
                box-shadow .15s ease,
                transform .15s ease;
        }

        .ticket-card.selected {
            border-color:
                rgba(0, 122, 178, .65);

            box-shadow:
                0 0 0 3px
                rgba(0, 122, 178, .08);
        }

        .ticket-card.unavailable {
            background: #FAFAFA;
        }

        .ticket-top {
            display: grid;

            grid-template-columns:
                minmax(0, 1fr)
                auto;

            gap: 20px;

            align-items: start;
        }

        .ticket-name {
            margin: 0;

            color: var(--elive-navy);

            font-size: 20px;
            font-weight: 900;
        }

        .ticket-description {
            margin:
                6px 0
                0;

            color: var(--elive-muted);

            font-size: 14px;
            line-height: 1.55;
        }

        .ticket-price {
            text-align: right;

            white-space: nowrap;
        }

        .ticket-price small {
            display: block;

            margin-bottom: 2px;

            color: var(--elive-muted);

            font-size: 12px;
            font-weight: 800;
        }

        .ticket-price strong {
            color: var(--elive-navy);

            font-size: 20px;
        }

        .ticket-details {
            margin-top: 14px;

            display: flex;
            flex-wrap: wrap;

            gap: 7px;
        }

        .status-pill {
            padding:
                6px
                9px;

            border-radius: 999px;

            background: #F2F4F7;

            color: #475467;

            font-size: 12px;
            font-weight: 800;
        }

        .status-pill.success {
            background:
                var(--elive-success-bg);

            color:
                var(--elive-success);
        }

        .status-pill.warning {
            background:
                var(--elive-warning-bg);

            color:
                var(--elive-warning);
        }

        .status-pill.danger {
            background:
                var(--elive-danger-bg);

            color:
                var(--elive-danger);
        }

        .ticket-actions {
            margin-top: 18px;

            padding-top: 17px;

            border-top:
                1px solid #F0F2F5;

            display: flex;
            justify-content: space-between;
            align-items: center;

            gap: 18px;
        }

        .quantity-control {
            display: inline-grid;

            grid-template-columns:
                40px
                52px
                40px;

            align-items: center;

            overflow: hidden;

            border:
                1px solid #D0D5DD;

            border-radius: 12px;

            background: #FFFFFF;
        }

        .quantity-button {
            height: 42px;

            border: 0;

            background: #F8FAFC;

            color: var(--elive-navy);

            font-size: 21px;
            font-weight: 800;
        }

        .quantity-button:hover:not(:disabled) {
            background: #EEF3F8;
        }

        .quantity-button:disabled {
            opacity: .4;
            cursor: not-allowed;
        }

        .quantity-input {
            width: 52px;
            height: 42px;

            border: 0;
            border-inline:
                1px solid #EAECF0;

            outline: none;

            background: #FFFFFF;

            color: var(--elive-navy);

            text-align: center;

            font-size: 16px;
            font-weight: 900;

            -moz-appearance: textfield;
        }

        .quantity-input::-webkit-inner-spin-button,
        .quantity-input::-webkit-outer-spin-button {
            margin: 0;

            -webkit-appearance: none;
        }

        .line-total {
            text-align: right;
        }

        .line-total small {
            display: block;

            color: var(--elive-muted);

            font-size: 12px;
        }

        .line-total strong {
            display: block;

            margin-top: 3px;

            color: var(--elive-navy);

            font-size: 16px;
        }

        .field-grid {
            margin-top: 22px;

            display: grid;

            grid-template-columns:
                1fr
                1fr;

            gap: 16px;
        }

        .field.full {
            grid-column: 1 / -1;
        }

        .field label {
            display: block;

            margin-bottom: 7px;

            color: #344054;

            font-size: 13px;
            font-weight: 800;
        }

        .required {
            color: var(--elive-danger);
        }

        .field input {
            width: 100%;

            min-height: 48px;

            padding:
                0 14px;

            border:
                1px solid #D0D5DD;

            border-radius: 12px;

            outline: none;

            background: #FFFFFF;

            color: #101828;
        }

        .field input:focus {
            border-color: var(--elive-blue);

            box-shadow:
                0 0 0 3px
                rgba(0, 122, 178, .10);
        }

        .field-error {
            margin-top: 6px;

            color: var(--elive-danger);

            font-size: 12px;
            font-weight: 700;
        }

        .summary-panel {
            position: sticky;
            top: 96px;
        }

        .summary-items {
            margin-top: 20px;

            display: grid;
            gap: 13px;
        }

        .summary-empty {
            padding:
                18px
                14px;

            border-radius: 12px;

            background: #F8FAFC;

            color: var(--elive-muted);

            font-size: 14px;
            text-align: center;
        }

        .summary-row {
            display: grid;

            grid-template-columns:
                minmax(0, 1fr)
                auto;

            gap: 16px;

            align-items: start;

            font-size: 14px;
        }

        .summary-name {
            color: #344054;
        }

        .summary-name small {
            display: block;

            margin-top: 2px;

            color: var(--elive-muted);
        }

        .summary-value {
            color: var(--elive-navy);

            font-weight: 800;

            white-space: nowrap;
        }

        .summary-divider {
            margin:
                20px 0;

            height: 1px;

            background: var(--elive-border);
        }

        .summary-total {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;

            gap: 16px;
        }

        .summary-total span {
            color: #475467;

            font-size: 14px;
            font-weight: 800;
        }

        .summary-total strong {
            color: var(--elive-navy);

            font-size: 25px;
        }

        .checkout-button {
            width: 100%;

            min-height: 52px;

            margin-top: 22px;

            border: 0;
            border-radius: 13px;

            background: var(--elive-orange);

            color: #1F2937;

            font-weight: 900;

            transition:
                transform .12s ease,
                opacity .12s ease;
        }

        .checkout-button:hover:not(:disabled) {
            transform: translateY(-1px);
        }

        .checkout-button:disabled {
            opacity: .5;
            cursor: not-allowed;
        }

        .payment-note {
            margin-top: 13px;

            color: var(--elive-muted);

            font-size: 12px;
            line-height: 1.55;
            text-align: center;
        }

        .secure-line {
            margin-top: 18px;

            padding-top: 17px;

            border-top:
                1px solid var(--elive-border);

            color: var(--elive-muted);

            font-size: 12px;
            line-height: 1.5;
        }

        @media (
            max-width: 920px
        ) {
            .hero-card {
                grid-template-columns: 1fr;
            }

            .hero-visual {
                min-height: 240px;
            }

            .hero-placeholder {
                min-height: 240px;
            }

            .checkout-layout {
                grid-template-columns: 1fr;
            }

            .summary-panel {
                position: static;
            }
        }

        @media (
            max-width: 640px
        ) {
            .container {
                width:
                    min(
                        100% - 22px,
                        1180px
                    );
            }

            .header-inner {
                min-height: 64px;
            }

            .brand {
                font-size: 19px;
            }

            .hero {
                padding-top: 22px;
            }

            .hero-content {
                padding:
                    28px
                    22px;
            }

            .panel {
                padding: 20px;
            }

            .ticket-top {
                grid-template-columns: 1fr;
            }

            .ticket-price {
                text-align: left;
            }

            .ticket-actions {
                align-items: flex-end;
            }

            .field-grid {
                grid-template-columns: 1fr;
            }

            .field.full {
                grid-column: auto;
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

        <a
            href="{{ route(
                'public.events.show',
                [
                    'event' => $event->slug,
                ]
            ) }}"
            class="back-link"
        >
            ← Event details
        </a>

    </div>
</header>

<main>

    <section class="hero">
        <div class="container">

            <div class="hero-card">

                <div class="hero-content">

                    <p class="eyebrow">
                        Official Ticket Sales
                    </p>

                    <h1>
                        {{ $event->name }}
                    </h1>

                    <div class="hero-meta">

                        @if ($eventStart)
                            <span>
                                {{ $eventStart->format('d M Y') }}
                            </span>
                        @endif

                        @if ($event->venue)
                            <span>
                                {{ $event->venue }}
                            </span>
                        @endif

                        @if ($event->organization)
                            <span>
                                {{ $event->organization->name }}
                            </span>
                        @endif

                    </div>

                    <div class="reservation-notice">
                        Once you continue to payment,
                        your selected tickets are reserved for

                        <strong>
                            {{ $reservationMinutes }}
                            {{ Str::plural(
                                'minute',
                                $reservationMinutes
                            ) }}
                        </strong>.

                        Complete payment before the reservation expires.
                    </div>

                </div>

                <div class="hero-visual">

                    @if ($eventImageUrl)

                        <img
                            class="hero-image"
                            src="{{ $eventImageUrl }}"
                            alt="{{ $event->name }}"
                        >

                    @else

                        <div class="hero-placeholder">
                            eLive Events
                        </div>

                    @endif

                </div>

            </div>

        </div>
    </section>

    <section class="page-content">
        <div class="container">

            @if ($errors->any())

                <div class="error-box">
                    <strong>
                        Please check your information.
                    </strong>

                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>
                                {{ $error }}
                            </li>
                        @endforeach
                    </ul>
                </div>

            @endif

            @if ($ticketTypes->isEmpty())

                <div class="panel">

                    <h2 class="panel-title">
                        No tickets are currently on sale.
                    </h2>

                    <p class="panel-description">
                        Please check again later or contact
                        the event organizer.
                    </p>

                </div>

            @else

                <form
                    method="POST"
                    action="{{ route(
                        'public.tickets.store',
                        [
                            'event' => $event->slug,
                        ]
                    ) }}"
                    id="ticket-checkout-form"
                >
                    @csrf

                    <div class="checkout-layout">

                        <div class="stack">

                            <section class="panel">

                                <h2 class="panel-title">
                                    Choose your tickets
                                </h2>

                                <p class="panel-description">
                                    Select the number of tickets
                                    you want to purchase.
                                </p>

                                @error('tickets')
                                    <div class="field-error">
                                        {{ $message }}
                                    </div>
                                @enderror

                                <div class="ticket-list">

                                    @foreach ($ticketTypes as $ticketType)

                                        @php
                                            $available =
                                                $ticketType
                                                    ->available_quantity;

                                            $minimum =
                                                max(
                                                    1,
                                                    (int)
                                                    $ticketType
                                                        ->min_per_order
                                                );

                                            $maximum =
                                                max(
                                                    0,
                                                    (int)
                                                    $ticketType
                                                        ->public_purchase_max
                                                );

                                            $soldOut =
                                                $available !== null
                                                && $available <= 0;

                                            $purchasable =
                                                ! $soldOut
                                                && $maximum >= $minimum;

                                            $oldQuantity =
                                                (int) old(
                                                    'tickets.'
                                                    . $ticketType->id,
                                                    0
                                                );

                                            $oldQuantity =
                                                max(
                                                    0,
                                                    min(
                                                        $oldQuantity,
                                                        $maximum
                                                    )
                                                );

                                            $lowStock =
                                                $available !== null
                                                && $available > 0
                                                && $available <= 5;
                                        @endphp

                                        <article
                                            class="ticket-card
                                                {{ $oldQuantity > 0 ? 'selected' : '' }}
                                                {{ ! $purchasable ? 'unavailable' : '' }}"
                                            data-ticket-card
                                            data-ticket-id="{{ $ticketType->id }}"
                                            data-ticket-name="{{ e($ticketType->name) }}"
                                            data-price="{{ (float) $ticketType->price }}"
                                            data-currency="{{ $ticketType->currency }}"
                                        >

                                            <div class="ticket-top">

                                                <div>

                                                    <h3 class="ticket-name">
                                                        {{ $ticketType->name }}
                                                    </h3>

                                                    @if ($ticketType->description)

                                                        <p class="ticket-description">
                                                            {{ $ticketType->description }}
                                                        </p>

                                                    @endif

                                                    <div class="ticket-details">

                                                        @if ($soldOut)

                                                            <span class="status-pill danger">
                                                                SOLD OUT
                                                            </span>

                                                        @elseif (! $purchasable)

                                                            <span class="status-pill danger">
                                                                Not enough availability
                                                            </span>

                                                        @elseif ($lowStock)

                                                            <span class="status-pill warning">
                                                                Only
                                                                {{ $available }}
                                                                remaining
                                                            </span>

                                                        @elseif ($available === null)

                                                            <span class="status-pill success">
                                                                Available
                                                            </span>

                                                        @else

                                                            <span class="status-pill success">
                                                                {{ number_format($available) }}
                                                                remaining
                                                            </span>

                                                        @endif


                                                    </div>

                                                </div>

                                                <div class="ticket-price">

                                                    <small>
                                                        {{ $ticketType->currency }}
                                                    </small>

                                                    <strong>
                                                        {{
                                                            number_format(
                                                                (float)
                                                                $ticketType->price,
                                                                0
                                                            )
                                                        }}
                                                    </strong>

                                                </div>

                                            </div>

                                            <div class="ticket-actions">

                                                <div class="quantity-control">

                                                    <button
                                                        type="button"
                                                        class="quantity-button"
                                                        data-quantity-minus
                                                        {{ ! $purchasable ? 'disabled' : '' }}
                                                        aria-label="Reduce {{ $ticketType->name }} quantity"
                                                    >
                                                        −
                                                    </button>

                                                    <input
                                                        type="number"
                                                        class="quantity-input"
                                                        name="tickets[{{ $ticketType->id }}]"
                                                        value="{{ $oldQuantity }}"
                                                        min="0"
                                                        max="{{ $maximum }}"
                                                        step="1"
                                                        data-quantity-input
                                                        data-minimum="{{ $minimum }}"
                                                        data-maximum="{{ $maximum }}"
                                                        {{ ! $purchasable ? 'disabled' : '' }}
                                                        aria-label="{{ $ticketType->name }} quantity"
                                                    >

                                                    <button
                                                        type="button"
                                                        class="quantity-button"
                                                        data-quantity-plus
                                                        {{ ! $purchasable ? 'disabled' : '' }}
                                                        aria-label="Increase {{ $ticketType->name }} quantity"
                                                    >
                                                        +
                                                    </button>

                                                </div>

                                                <div class="line-total">
                                                    <small>
                                                        Subtotal
                                                    </small>

                                                    <strong data-line-total>
                                                        {{ $ticketType->currency }}
                                                        {{
                                                            number_format(
                                                                (float)
                                                                $ticketType->price
                                                                * $oldQuantity,
                                                                0
                                                            )
                                                        }}
                                                    </strong>
                                                </div>

                                            </div>

                                        </article>

                                    @endforeach

                                </div>

                            </section>

                            <section class="panel">

                                <h2 class="panel-title">
                                    Your details
                                </h2>

                                <p class="panel-description">
                                    We will use these details
                                    for your order and ticket delivery.
                                </p>

                                <div class="field-grid">

                                    <div class="field full">

                                        <label for="buyer_name">
                                            Full name
                                            <span class="required">*</span>
                                        </label>

                                        <input
                                            type="text"
                                            id="buyer_name"
                                            name="buyer_name"
                                            value="{{ old('buyer_name') }}"
                                            maxlength="150"
                                            autocomplete="name"
                                            required
                                            placeholder="Enter your full name"
                                        >

                                        @error('buyer_name')
                                            <div class="field-error">
                                                {{ $message }}
                                            </div>
                                        @enderror

                                    </div>

                                    <div class="field">

                                        <label for="buyer_phone">
                                            Phone number
                                        </label>

                                        <input
                                            type="tel"
                                            id="buyer_phone"
                                            name="buyer_phone"
                                            value="{{ old('buyer_phone') }}"
                                            maxlength="30"
                                            autocomplete="tel"
                                            placeholder="e.g. 2557XXXXXXXX"
                                        >

                                        @error('buyer_phone')
                                            <div class="field-error">
                                                {{ $message }}
                                            </div>
                                        @enderror

                                    </div>

                                    <div class="field">

                                        <label for="buyer_email">
                                            Email address
                                        </label>

                                        <input
                                            type="email"
                                            id="buyer_email"
                                            name="buyer_email"
                                            value="{{ old('buyer_email') }}"
                                            maxlength="255"
                                            autocomplete="email"
                                            placeholder="you@example.com"
                                        >

                                        @error('buyer_email')
                                            <div class="field-error">
                                                {{ $message }}
                                            </div>
                                        @enderror

                                    </div>

                                </div>

                            </section>

                        </div>

                        <aside>

                            <section class="panel summary-panel">

                                <h2 class="panel-title">
                                    Order summary
                                </h2>

                                <p class="panel-description">
                                    Review your selected tickets
                                    before payment.
                                </p>

                                <div
                                    class="summary-items"
                                    id="summary-items"
                                >
                                    <div
                                        class="summary-empty"
                                        id="summary-empty"
                                    >
                                        No tickets selected yet.
                                    </div>
                                </div>

                                <div class="summary-divider"></div>

                                <div class="summary-total">

                                    <span>
                                        Total
                                    </span>

                                    <strong id="grand-total">
                                        {{ $displayCurrency }} 0
                                    </strong>

                                </div>

                                <button
                                    type="submit"
                                    class="checkout-button"
                                    id="checkout-button"
                                >
                                    Continue to Payment
                                </button>

                                <p class="payment-note">
                                    Your reservation starts when
                                    this order is created.
                                    You will then be redirected
                                    securely to Pesapal.
                                </p>

                                <div class="secure-line">
                                    Prices and ticket availability
                                    are revalidated by eLive Events
                                    before the reservation is created.
                                    Browser calculations are for
                                    display only.
                                </div>

                            </section>

                        </aside>

                    </div>

                </form>

            @endif

        </div>
    </section>

</main>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form =
            document.getElementById(
                'ticket-checkout-form'
            );

        if (! form) {
            return;
        }

        const cards =
            Array.from(
                form.querySelectorAll(
                    '[data-ticket-card]'
                )
            );

        const summaryItems =
            document.getElementById(
                'summary-items'
            );

        const summaryEmpty =
            document.getElementById(
                'summary-empty'
            );

        const grandTotal =
            document.getElementById(
                'grand-total'
            );

        const checkoutButton =
            document.getElementById(
                'checkout-button'
            );

        const defaultCurrency =
            @json($displayCurrency);

        const formatMoney =
            function (
                currency,
                amount
            ) {
                return (
                    currency
                    + ' '
                    + Number(amount)
                        .toLocaleString(
                            undefined,
                            {
                                maximumFractionDigits: 0,
                            }
                        )
                );
            };

        const normalizeQuantity =
            function (
                input,
                requested
            ) {
                const minimum =
                    Number(
                        input.dataset.minimum
                        || 1
                    );

                const maximum =
                    Number(
                        input.dataset.maximum
                        || 0
                    );

                let value =
                    Number(requested || 0);

                if (
                    ! Number.isFinite(value)
                    || value < 0
                ) {
                    value = 0;
                }

                value =
                    Math.floor(value);

                if (value > maximum) {
                    value = maximum;
                }

                if (
                    value > 0
                    && value < minimum
                ) {
                    value = minimum;
                }

                return value;
            };

        const refreshSummary =
            function () {
                let total = 0;
                let selectedCount = 0;

                summaryItems
                    .querySelectorAll(
                        '.summary-row'
                    )
                    .forEach(
                        function (row) {
                            row.remove();
                        }
                    );

                cards.forEach(
                    function (card) {
                        const input =
                            card.querySelector(
                                '[data-quantity-input]'
                            );

                        if (! input) {
                            return;
                        }

                        const quantity =
                            normalizeQuantity(
                                input,
                                input.value
                            );

                        input.value =
                            quantity;

                        const price =
                            Number(
                                card.dataset.price
                                || 0
                            );

                        const currency =
                            card.dataset.currency
                            || defaultCurrency;

                        const lineTotal =
                            quantity * price;

                        const lineTotalElement =
                            card.querySelector(
                                '[data-line-total]'
                            );

                        if (lineTotalElement) {
                            lineTotalElement
                                .textContent =
                                formatMoney(
                                    currency,
                                    lineTotal
                                );
                        }

                        card.classList.toggle(
                            'selected',
                            quantity > 0
                        );

                        if (quantity <= 0) {
                            return;
                        }

                        selectedCount++;
                        total += lineTotal;

                        const row =
                            document.createElement(
                                'div'
                            );

                        row.className =
                            'summary-row';

                        const name =
                            document.createElement(
                                'div'
                            );

                        name.className =
                            'summary-name';

                        name.textContent =
                            card.dataset.ticketName;

                        const small =
                            document.createElement(
                                'small'
                            );

                        small.textContent =
                            quantity
                            + ' × '
                            + formatMoney(
                                currency,
                                price
                            );

                        name.appendChild(
                            small
                        );

                        const value =
                            document.createElement(
                                'div'
                            );

                        value.className =
                            'summary-value';

                        value.textContent =
                            formatMoney(
                                currency,
                                lineTotal
                            );

                        row.appendChild(
                            name
                        );

                        row.appendChild(
                            value
                        );

                        summaryItems.appendChild(
                            row
                        );
                    }
                );

                summaryEmpty.style.display =
                    selectedCount > 0
                        ? 'none'
                        : 'block';

                grandTotal.textContent =
                    formatMoney(
                        defaultCurrency,
                        total
                    );

                checkoutButton.disabled =
                    selectedCount === 0;
            };

        cards.forEach(
            function (card) {
                const input =
                    card.querySelector(
                        '[data-quantity-input]'
                    );

                const minus =
                    card.querySelector(
                        '[data-quantity-minus]'
                    );

                const plus =
                    card.querySelector(
                        '[data-quantity-plus]'
                    );

                if (! input) {
                    return;
                }

                const minimum =
                    Number(
                        input.dataset.minimum
                        || 1
                    );

                const maximum =
                    Number(
                        input.dataset.maximum
                        || 0
                    );

                if (minus) {
                    minus.addEventListener(
                        'click',
                        function () {
                            const current =
                                normalizeQuantity(
                                    input,
                                    input.value
                                );

                            if (
                                current <= minimum
                            ) {
                                input.value = 0;
                            } else {
                                input.value =
                                    current - 1;
                            }

                            refreshSummary();
                        }
                    );
                }

                if (plus) {
                    plus.addEventListener(
                        'click',
                        function () {
                            const current =
                                normalizeQuantity(
                                    input,
                                    input.value
                                );

                            if (current === 0) {
                                input.value =
                                    Math.min(
                                        minimum,
                                        maximum
                                    );
                            } else {
                                input.value =
                                    Math.min(
                                        maximum,
                                        current + 1
                                    );
                            }

                            refreshSummary();
                        }
                    );
                }

                input.addEventListener(
                    'change',
                    function () {
                        input.value =
                            normalizeQuantity(
                                input,
                                input.value
                            );

                        refreshSummary();
                    }
                );

                input.addEventListener(
                    'input',
                    refreshSummary
                );
            }
        );

        form.addEventListener(
            'submit',
            function (event) {
                let hasTickets = false;

                cards.forEach(
                    function (card) {
                        const input =
                            card.querySelector(
                                '[data-quantity-input]'
                            );

                        if (
                            input
                            && Number(input.value) > 0
                        ) {
                            hasTickets = true;
                        }
                    }
                );

                if (! hasTickets) {
                    event.preventDefault();

                    window.alert(
                        'Please select at least one ticket.'
                    );
                }
            }
        );

        refreshSummary();
    });
</script>

</body>
</html>