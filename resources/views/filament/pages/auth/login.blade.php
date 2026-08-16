<x-filament-panels::page.simple>
    <style>
        :root {
            --elive-blue: #233F7E;
            --elive-blue-dark: #17233F;
            --elive-orange: #FF9418;
            --elive-muted: #64748B;
        }

        .elive-login-page {
            background: #EEF3F8;
        }

        .elive-login-page .fi-simple-main {
            width: min(1080px, calc(100vw - 32px)) !important;
            max-width: 1080px !important;
            padding: 0 !important;
            overflow: hidden;
            border-radius: 24px;
            background: #FFFFFF;
            box-shadow: 0 22px 60px rgba(15, 23, 42, 0.12);
        }

        .elive-login-page .fi-simple-header,
        .elive-login-page .fi-logo {
            display: none !important;
        }

        .elive-login-shell {
            min-height: 590px;
            display: grid;
            grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr);
            background: #FFFFFF;
        }

        /*
        |--------------------------------------------------------------------------
        | Left Branding Panel
        |--------------------------------------------------------------------------
        */

        .elive-login-brand {
            position: relative;
            padding: 54px 50px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            background: var(--elive-blue);
            color: #FFFFFF;
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
            width: 205px;
            min-height: 74px;
        }

        .elive-login-brand-logo img {
            width: 205px;
            max-width: 100%;
            height: auto;
            display: block;
            object-fit: contain;
        }

        .elive-login-brand-copy {
            max-width: 370px;
            margin-top: 58px;
            margin-bottom: auto;
        }

        .elive-login-kicker {
            margin: 0 0 12px;
            color: #FFD1A0;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.13em;
            text-transform: uppercase;
        }

        .elive-login-brand-copy h1 {
            margin: 0;
            color: #FFFFFF;
            font-size: clamp(34px, 3.6vw, 46px);
            line-height: 1.05;
            letter-spacing: -0.035em;
        }

        .elive-login-brand-copy p {
            margin: 18px 0 0;
            max-width: 340px;
            color: rgba(255, 255, 255, 0.80);
            font-size: 15px;
            line-height: 1.7;
        }

        .elive-login-brand-footer {
            margin-top: auto;
            padding-top: 32px;
            color: rgba(255, 255, 255, 0.68);
            font-size: 12px;
        }

        /*
        |--------------------------------------------------------------------------
        | Right Login Panel
        |--------------------------------------------------------------------------
        */

        .elive-login-form-panel {
            padding: 68px 64px 50px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            background: #FFFFFF;
        }

        .elive-login-form-header {
            margin-bottom: 24px;
        }

        .elive-login-form-header .eyebrow {
            margin: 0 0 9px;
            color: var(--elive-orange);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 0.12em;
            text-transform: uppercase;
        }

        .elive-login-form-header h2 {
            margin: 0;
            color: var(--elive-blue);
            font-size: 32px;
            line-height: 1.1;
            letter-spacing: -0.025em;
        }

        .elive-login-form-header p {
            margin: 10px 0 0;
            color: var(--elive-muted);
            font-size: 14px;
            line-height: 1.6;
        }

        /*
        |--------------------------------------------------------------------------
        | Filament Form Styling
        |--------------------------------------------------------------------------
        */

        .elive-login-page .fi-fo-field-wrp {
            margin-bottom: 18px;
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
            min-height: 20px;
            margin-bottom: 7px !important;
            font-size: 13px !important;
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
            min-height: 48px !important;
            border: 1px solid #CBD5E1 !important;
            border-radius: 12px !important;
            background: #FFFFFF !important;
            box-shadow: none !important;
        }

        .elive-login-page .fi-input-wrp:focus-within {
            border-color: var(--elive-blue) !important;
            box-shadow: 0 0 0 3px rgba(35, 63, 126, 0.10) !important;
        }

        .elive-login-page .fi-input {
            min-height: 46px !important;
            color: #0F172A !important;
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
            font-size: 13px !important;
            font-weight: 600 !important;
        }

        .elive-login-page .fi-checkbox-input {
            border-radius: 5px !important;
        }

        .elive-login-page .fi-btn.fi-color-primary,
        .elive-login-page button[type="submit"] {
            min-height: 50px !important;
            border-radius: 12px !important;
            background: var(--elive-blue) !important;
            color: #FFFFFF !important;
            font-weight: 800 !important;
            box-shadow: 0 8px 18px rgba(35, 63, 126, 0.16) !important;
        }

        .elive-login-page .fi-btn.fi-color-primary:hover,
        .elive-login-page button[type="submit"]:hover {
            background: #1B3267 !important;
        }

        .elive-login-page .fi-link {
            color: var(--elive-blue) !important;
        }

        .elive-login-back {
            margin-top: 20px;
            text-align: center;
        }

        .elive-login-back a {
            color: var(--elive-muted);
            font-size: 13px;
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
                width: min(620px, calc(100vw - 24px)) !important;
            }

            .elive-login-shell {
                grid-template-columns: 1fr;
            }

            .elive-login-brand {
                min-height: 270px;
                padding: 34px 34px 30px;
            }

            .elive-login-brand-copy {
                margin: 34px 0 0;
            }

            .elive-login-brand-copy h1 {
                font-size: 36px;
            }

            .elive-login-brand-footer {
                display: none;
            }

            .elive-login-form-panel {
                padding: 44px 34px 36px;
            }
        }

        @media (max-width: 520px) {
            .elive-login-page .fi-simple-main {
                width: calc(100vw - 16px) !important;
                border-radius: 20px;
            }

            .elive-login-brand {
                min-height: 230px;
                padding: 28px 24px;
            }

            .elive-login-brand-logo,
            .elive-login-brand-logo img {
                width: 160px;
            }

            .elive-login-brand-logo {
                min-height: 58px;
            }

            .elive-login-brand-copy {
                margin-top: 28px;
            }

            .elive-login-brand-copy h1 {
                font-size: 31px;
            }

            .elive-login-brand-copy p {
                font-size: 13px;
            }

            .elive-login-form-panel {
                padding: 38px 24px 30px;
            }

            .elive-login-form-header h2 {
                font-size: 28px;
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
