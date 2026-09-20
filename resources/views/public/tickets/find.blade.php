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

    <title>Find My Tickets - eLive Events</title>

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
            --danger: #B42318;
            --danger-bg: #FEF3F2;
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
            width: min(720px, calc(100% - 32px));
            margin-inline: auto;
        }

        .site-header {
            border-bottom: 1px solid var(--border);
            background: #FFFFFF;
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

        .events-link {
            color: var(--elive-blue);
            font-size: 14px;
            font-weight: 800;
        }

        main {
            padding: 56px 0 80px;
        }

        .card {
            overflow: hidden;
            border: 1px solid var(--border);
            border-radius: 24px;
            background: var(--surface);
            box-shadow: 0 18px 48px rgba(22, 25, 67, .08);
        }

        .hero {
            padding: 34px;
            background:
                linear-gradient(
                    135deg,
                    var(--elive-navy) 0%,
                    #22295F 100%
                );
            color: #FFFFFF;
        }

        .eyebrow {
            margin: 0 0 9px;
            color: #9DDAF5;
            font-size: 12px;
            font-weight: 900;
            letter-spacing: .1em;
            text-transform: uppercase;
        }

        h1 {
            margin: 0;
            font-size: clamp(32px, 7vw, 46px);
            line-height: 1.08;
        }

        .hero-copy {
            margin: 14px 0 0;
            max-width: 560px;
            color: rgba(255, 255, 255, .82);
            font-size: 15px;
            line-height: 1.7;
        }

        .body {
            padding: 32px 34px 36px;
        }

        .alert {
            margin-bottom: 22px;
            padding: 14px 16px;
            border-radius: 13px;
            font-size: 14px;
            line-height: 1.6;
        }

        .alert-success {
            background: var(--success-bg);
            color: var(--success);
        }

        .alert-error {
            background: var(--danger-bg);
            color: var(--danger);
        }

        .field {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            color: var(--elive-navy);
            font-size: 14px;
            font-weight: 800;
        }

        input {
            width: 100%;
            min-height: 48px;
            padding: 11px 13px;
            border: 1px solid #D0D5DD;
            border-radius: 11px;
            background: #FFFFFF;
            color: var(--text);
            font: inherit;
            outline: none;
        }

        input:focus {
            border-color: var(--elive-blue);
            box-shadow: 0 0 0 3px rgba(0, 122, 178, .10);
        }

        .hint {
            margin: 7px 0 0;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.55;
        }

        .button {
            width: 100%;
            min-height: 50px;
            border: 0;
            border-radius: 12px;
            background: var(--elive-orange);
            color: #FFFFFF;
            cursor: pointer;
            font-size: 15px;
            font-weight: 900;
        }

        .security-note {
            margin: 22px 0 0;
            color: var(--muted);
            font-size: 12px;
            line-height: 1.7;
            text-align: center;
        }

        @media (max-width: 640px) {
            main {
                padding-top: 24px;
            }

            .hero,
            .body {
                padding: 25px 20px;
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
            href="{{ route('public.events.index') }}"
            class="events-link"
        >
            Events
        </a>
    </div>
</header>

<main>
    <div class="container">

        <section class="card">

            <div class="hero">
                <p class="eyebrow">
                    Ticket Access
                </p>

                <h1>
                    Find My Tickets
                </h1>

                <p class="hero-copy">
                    Enter your order number and the email address or
                    phone number used during purchase. If the details
                    match a paid order, we will send your secure ticket
                    access link to that saved contact.
                </p>
            </div>

            <div class="body">

                @if (session('status'))
                    <div class="alert alert-success">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-error">
                        Please check the information below and try again.
                    </div>
                @endif

                <form
                    method="POST"
                    action="{{ route('public.ticket-recovery.store') }}"
                >
                    @csrf

                    <div class="field">
                        <label for="order_number">
                            Order Number
                        </label>

                        <input
                            id="order_number"
                            name="order_number"
                            type="text"
                            value="{{ old('order_number') }}"
                            placeholder="ELV-TKT-ECT26-..."
                            required
                            autocomplete="off"
                        >

                        @error('order_number')
                            <p class="hint">
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div class="field">
                        <label for="contact">
                            Email or Phone
                        </label>

                        <input
                            id="contact"
                            name="contact"
                            type="text"
                            value="{{ old('contact') }}"
                            placeholder="buyer@example.com or 0712345678"
                            required
                            autocomplete="email"
                        >

                        @error('contact')
                            <p class="hint">
                                {{ $message }}
                            </p>
                        @enderror

                        <p class="hint">
                            Use the same email address or phone number
                            used when purchasing the tickets.
                        </p>
                    </div>

                    <button
                        type="submit"
                        class="button"
                    >
                        Send My Tickets Link
                    </button>
                </form>

                <p class="security-note">
                    For your security, this page does not reveal whether
                    an order number or contact exists in our system.
                </p>

            </div>

        </section>

    </div>
</main>

</body>
</html>
