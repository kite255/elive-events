<x-filament-panels::page.simple>
    <style>
        :root {
            --elive-navy: #161943;
            --elive-blue: #007AB2;
            --elive-orange: #FF9800;

            --elive-background: #F7F8FC;
            --elive-surface: #FFFFFF;
            --elive-muted: #667085;
            --elive-border: #E6E8EF;

            --elive-navy-hover: #20265C;
            --elive-blue-hover: #006B9D;
        }

        .elive-login-page {
            background: var(--elive-background);
            font-family:
                'Creato Display',
                ui-sans-serif,
                system-ui,
                sans-serif;
        }

        .elive-login-page button,
        .elive-login-page input,
        .elive-login-page label,
        .elive-login-page a,
        .elive-login-page h1,
        .elive-login-page h2,
        .elive-login-page p,
        .elive-login-page span {
            font-family:
                'Creato Display',
                ui-sans-serif,
                system-ui,
                sans-serif;
        }

        .elive-login-page .fi-simple-main {
            width: min(840px, calc(100vw - 32px)) !important;
            max-width: 840px !important;
            padding: 0 !important;
            overflow: hidden;
            border-radius: 22px;
            background: var(--elive-surface);
            box-shadow: 0 18px 48px rgba(22, 25, 67, 0.11);
        }

        .elive-login-page .fi-simple-header,
        .elive-login-page .fi-logo {
            display: none !important;
        }

        .elive-login-shell {
            min-height: 450px;
            display: grid;
            grid-template-columns: minmax(0, 0.86fr) minmax(0, 1.14fr);
            background: var(--elive-surface);
        }

        /*
        |--------------------------------------------------------------------------
        | Left Branding Panel
        |--------------------------------------------------------------------------
        */

        .elive-login-brand {
            position: relative;
            overflow: hidden;
            padding: 30px 30px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background: var(--elive-navy);
            color: #FFFFFF;
        }

        .elive-login-brand::before {
            content: "";
            position: absolute;
            width: 220px;
            height: 220px;
            right: -110px;
            top: -90px;
            border-radius: 999px;
            background: rgba(0, 122, 178, 0.18);
            pointer-events: none;
        }

        .elive-login-brand::after {
            content: "";
            position: absolute;
            width: 150px;
            height: 150px;
            left: -80px;
            bottom: -90px;
            border-radius: 999px;
            background: rgba(255, 152, 0, 0.12);
            pointer-events: none;
        }

        .elive-login-brand-top,
        .elive-login-brand-copy,
        .elive-login-brand-footer {
            position: relative;
            z-index: 2;
        }

        .elive-login-brand-logo {
            display: flex;
            align-items: center;
            width: 120px;
            min-height: 44px;
        }

        .elive-login-brand-logo img {
            width: 120px;
            max-width: 100%;
            height: auto;
            display: block;
            object-fit: contain;
        }

        .elive-login-brand-copy {
            max-width: 275px;
            margin-top: 28px;
            margin-bottom: auto;
        }

        .elive-login-kicker {
            margin: 0 0 9px;
            color: var(--elive-orange);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.13em;
            text-transform: uppercase;
        }

        .elive-login-brand-copy h1 {
            margin: 0;
            color: #FFFFFF !important;
            font-size: clamp(26px, 2.5vw, 32px);
            line-height: 1.06;
            letter-spacing: -0.035em;
        }

        .elive-login-brand-copy p {
            margin: 10px 0 0;
            max-width: 270px;
            color: rgba(255, 255, 255, 0.80);
            font-size: 12px;
            line-height: 1.55;
        }

        .elive-login-brand-footer {
            margin-top: auto;
            padding-top: 24px;
            color: rgba(255, 255, 255, 0.68);
            font-size: 11px;
        }

        /*
        |--------------------------------------------------------------------------
        | Right Login Panel
        |--------------------------------------------------------------------------
        */

        .elive-login-form-panel {
            padding: 32px 36px 30px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: var(--elive-surface);
        }

        .elive-login-form-header {
            margin-bottom: 12px;
        }

        .elive-login-form-header .eyebrow {
            margin: 0 0 7px;
            color: var(--elive-orange);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .elive-login-form-header h2 {
            margin: 0;
            color: var(--elive-navy);
            font-size: 24px;
            line-height: 1.1;
            letter-spacing: -0.025em;
        }

        .elive-login-form-header p {
            margin: 6px 0 0;
            color: var(--elive-muted);
            font-size: 12px;
            line-height: 1.55;
        }

        /*
        |--------------------------------------------------------------------------
        | Filament Form Styling
        |--------------------------------------------------------------------------
        */

        .elive-login-page .fi-fo-field-wrp {
            margin-bottom: 9px;
        }

        .elive-login-page .fi-fo-field-wrp-label,
        .elive-login-page .fi-fo-field-wrp-label label,
        .elive-login-page .fi-fo-field-wrp-label-text,
        .elive-login-page .fi-fo-field-wrp-label span {
            color: #334155 !important;
            opacity: 1 !important;
            visibility: visible !important;
        }

        .elive-login-page .fi-fo-field-wrp-label {
            display: flex !important;
            align-items: center !important;
            min-height: 18px;
            margin-bottom: 5px !important;
            font-size: 12px !important;
            font-weight: 700 !important;
        }

        .elive-login-page .fi-fo-field-wrp-required-mark {
            color: #EF4444 !important;
            opacity: 1 !important;
            visibility: visible !important;
        }

        /*
         * Filament 5 may render the actual label text through nested
         * elements whose utility classes do not match older selectors.
         * Force every form label inside the right panel to remain visible.
         */
        .elive-login-form-panel label {
            color: #334155 !important;
            opacity: 1 !important;
            visibility: visible !important;
        }

        .elive-login-form-panel label span,
        .elive-login-form-panel label div,
        .elive-login-form-panel label p {
            color: inherit !important;
            opacity: 1 !important;
            visibility: visible !important;
        }

        .elive-login-form-panel label .fi-fo-field-wrp-required-mark,
        .elive-login-form-panel .fi-fo-field-wrp-required-mark {
            color: #EF4444 !important;
        }

        /*
         * Keep checkbox text visible as well.
         */
        .elive-login-form-panel input[type="checkbox"] + span,
        .elive-login-form-panel input[type="checkbox"] ~ span {
            color: #475569 !important;
            opacity: 1 !important;
            visibility: visible !important;
        }

        .elive-login-page .fi-input-wrp {
            min-height: 39px !important;
            border: 1px solid #CBD5E1 !important;
            border-radius: 10px !important;
            background: #FFFFFF !important;
            box-shadow: none !important;
        }

        .elive-login-page .fi-input-wrp:focus-within {
            border-color: var(--elive-blue) !important;
            box-shadow: 0 0 0 3px rgba(0, 122, 178, 0.12) !important;
        }

        .elive-login-page .fi-input {
            min-height: 37px !important;
            color: #0F172A !important;
            font-size: 12.5px !important;
        }

        .elive-login-page input::placeholder {
            color: #94A3B8 !important;
            opacity: 1 !important;
        }

        .elive-login-page .fi-checkbox-label,
        .elive-login-page .fi-checkbox-label span,
        .elive-login-page label:has(input[type="checkbox"]),
        .elive-login-page label:has(.fi-checkbox-input) {
            color: #475569 !important;
            opacity: 1 !important;
            visibility: visible !important;
            font-size: 12px !important;
            font-weight: 600 !important;
        }

        .elive-login-page .fi-checkbox-input {
            border-radius: 5px !important;
        }

        .elive-login-page .fi-btn.fi-color-primary,
        .elive-login-page button[type="submit"] {
            min-height: 41px !important;
            border-radius: 10px !important;
            background: var(--elive-navy) !important;
            color: #FFFFFF !important;
            font-size: 13px !important;
            font-weight: 800 !important;
            box-shadow: 0 6px 14px rgba(22, 25, 67, 0.14) !important;
            transition:
                background 0.2s ease,
                transform 0.2s ease,
                box-shadow 0.2s ease !important;
        }

        .elive-login-page .fi-btn.fi-color-primary:hover,
        .elive-login-page button[type="submit"]:hover {
            background: var(--elive-blue) !important;
            transform: translateY(-1px);
            box-shadow: 0 9px 18px rgba(0, 122, 178, 0.18) !important;
        }

        .elive-login-page .fi-link {
            color: var(--elive-blue) !important;
        }

        .elive-login-page .fi-link:hover {
            color: var(--elive-navy) !important;
        }

        .elive-login-back {
            margin-top: 8px;
            text-align: center;
        }

        .elive-login-back a {
            color: var(--elive-muted);
            font-size: 12px;
            font-weight: 700;
            text-decoration: none;
            transition: color 0.2s ease;
        }

        .elive-login-back a:hover {
            color: var(--elive-blue);
        }

        /*
        |--------------------------------------------------------------------------
        | Responsive
        |--------------------------------------------------------------------------
        */

        @media (max-width: 880px) {
            .elive-login-page .fi-simple-main {
                width: min(580px, calc(100vw - 24px)) !important;
            }

            .elive-login-shell {
                grid-template-columns: 1fr;
            }

            .elive-login-brand {
                min-height: 230px;
                padding: 28px 30px 26px;
            }

            .elive-login-brand-copy {
                margin: 26px 0 0;
            }

            .elive-login-brand-copy h1 {
                font-size: 28px;
            }

            .elive-login-brand-footer {
                display: none;
            }

            .elive-login-form-panel {
                padding: 34px 30px 30px;
            }
        }

        @media (max-width: 520px) {
            .elive-login-page .fi-simple-main {
                width: calc(100vw - 16px) !important;
                border-radius: 20px;
            }

            .elive-login-brand {
                min-height: 205px;
                padding: 24px 22px;
            }

            .elive-login-brand-logo,
            .elive-login-brand-logo img {
                width: 112px;
            }

            .elive-login-brand-logo {
                min-height: 42px;
            }

            .elive-login-brand-copy {
                margin-top: 22px;
            }

            .elive-login-brand-copy h1 {
                font-size: 31px;
            }

            .elive-login-brand-copy p {
                font-size: 12px;
            }

            .elive-login-form-panel {
                padding: 30px 22px 26px;
            }

            .elive-login-form-header h2 {
                font-size: 25px;
            }
        }
    </style>

    <div class="elive-login-shell">

        <section class="elive-login-brand">

            <div class="elive-login-brand-top">
                <div class="elive-login-brand-logo">
                    <img
                        src="{{ asset('eLive_W.png') }}"
                        alt="eLive Events"
                    >
                </div>
            </div>

            <div class="elive-login-brand-copy">

                <p class="elive-login-kicker">
                    eLive Events
                </p>

                <h1>
                    Manage events with confidence.
                </h1>

                <p>
                    Registration, badges, communication, QR check-in,
                    attendance and reporting in one professional platform.
                </p>

            </div>

            <div class="elive-login-brand-footer">
                © {{ date('Y') }} eLive Events
            </div>

        </section>


        <section class="elive-login-form-panel">

            <div class="elive-login-form-header">

                <p class="eyebrow">
                    Login Portal
                </p>

                <h2>
                    Welcome back
                </h2>

                <p>
                    Sign in to continue to your eLive Events dashboard.
                </p>

            </div>

            {{ $this->content }}

            <div class="elive-login-back">
                <a href="{{ route('home') }}">
                    Back to eLive Events
                </a>
            </div>

        </section>

    </div>
</x-filament-panels::page.simple>
