<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    @php
        $successTitle = match ($attendee->status) {
            'pending_approval' => 'Registration Received',
            'waitlisted' => 'Registration Received',
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
                'Your registration was not approved. Please contact the event organizer.',

            'cancelled' =>
                'Your registration has been cancelled.',

            default =>
                $event->registration_success_message
                    ?: 'Your registration has been completed successfully.',
        };

        $isSuccessful = in_array(
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

        $canViewBadge =
            $registrationUrl
            && $isSuccessful;

        /*
         * Merchandise / payment summary.
         */
        $merchandiseSelections =
            $attendee->merchandiseSelections ?? collect();

        $paidSelections = $merchandiseSelections
            ->filter(
                fn ($selection) =>
                    (float) ($selection->total_price ?? 0) > 0
            );

        $paymentTotal = (float) $paidSelections->sum(
            fn ($selection) =>
                (float) ($selection->total_price ?? 0)
        );

        $paymentCurrency =
            $paidSelections
                ->pluck('currency')
                ->filter()
                ->first()
            ?? 'TZS';

        $paymentRequired = $paymentTotal > 0;

        $paymentStatus = $paymentRequired
            ? strtoupper(
                str_replace(
                    '_',
                    ' ',
                    (string) (
                        $paidSelections
                            ->pluck('payment_status')
                            ->filter()
                            ->first()
                        ?? 'pending'
                    )
                )
            )
            : null;

        $hasPaymentDetails =
            filled($event->payment_method)
            || filled($event->payment_account_name)
            || filled($event->payment_account_number)
            || filled($event->payment_instructions);
    @endphp

    <title>
        {{ $successTitle }} - {{ $event->name }}
    </title>

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <style>
        :root {
            --primary:
                {{ $branding['primary_color'] ?? '#1e3a8a' }};

            --button:
                {{ $branding['button_color'] ?? '#1e40af' }};

            --background:
                {{ $branding['background_color'] ?? '#f8fafc' }};

            --text: #0f172a;
            --muted: #64748b;
            --border: #e2e8f0;
            --soft: #f8fafc;

            --success: #16a34a;
            --warning: #d97706;
            --danger: #dc2626;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;

            display: flex;
            align-items: flex-start;
            justify-content: center;

            padding: 36px 20px;

            background:
                linear-gradient(
                    180deg,
                    #f8fafc 0%,
                    var(--background) 100%
                );

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

        .success-card {
            width: min(100%, 620px);

            padding: 42px 32px;

            background: #ffffff;

            border: 1px solid var(--border);

            border-radius: 24px;

            box-shadow:
                0 20px 50px
                rgba(15, 23, 42, 0.10);

            text-align: center;
        }

        .logo {
            display: block;

            max-width: 90px;
            max-height: 90px;

            margin: 0 auto 22px;

            object-fit: contain;
        }

        .icon {
            width: 76px;
            height: 76px;

            margin: 0 auto 22px;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 999px;

            font-size: 40px;
            font-weight: 900;
        }

        .icon.success {
            background: #dcfce7;
            color: #15803d;
        }

        .icon.warning {
            background: #ffedd5;
            color: #c2410c;
        }

        .icon.danger {
            background: #fee2e2;
            color: #b91c1c;
        }

        h1 {
            margin: 0;

            color: var(--primary);

            font-size: clamp(28px, 5vw, 38px);

            line-height: 1.2;

            font-weight: 900;
        }

        .name {
            margin-top: 20px;

            color: var(--text);

            font-size: 18px;
            font-weight: 800;
        }

        .message {
            max-width: 500px;

            margin: 10px auto 0;

            color: #475569;

            font-size: 16px;
            line-height: 1.7;
        }

        .event-name {
            color: var(--primary);
            font-weight: 800;
        }

        .payment-card {
            margin-top: 28px;

            padding: 20px;

            border:
                1px solid
                color-mix(
                    in srgb,
                    var(--primary) 24%,
                    var(--border)
                );

            border-radius: 18px;

            background:
                linear-gradient(
                    145deg,
                    color-mix(
                        in srgb,
                        var(--primary) 5%,
                        #ffffff
                    ),
                    #ffffff
                );

            text-align: left;
        }

        .payment-title {
            margin: 0;

            color: var(--primary);

            font-size: 19px;
            font-weight: 900;
        }

        .payment-subtitle {
            margin: 6px 0 0;

            color: var(--muted);

            font-size: 13px;
            line-height: 1.6;
        }

        .amount-box {
            margin-top: 16px;

            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 14px;

            padding: 14px 15px;

            border: 1px solid var(--border);
            border-radius: 14px;

            background: var(--soft);
        }

        .amount-label {
            color: var(--muted);
            font-size: 13px;
            font-weight: 700;
        }

        .amount-value {
            color: var(--text);
            font-size: 19px;
            font-weight: 900;
            white-space: nowrap;
        }

        .payment-status {
            margin-top: 10px;

            display: inline-flex;
            align-items: center;

            padding: 6px 10px;

            border-radius: 999px;

            background: #fff7ed;
            color: #9a3412;

            font-size: 11px;
            font-weight: 900;
        }

        .payment-details {
            margin-top: 16px;

            overflow: hidden;

            border: 1px solid var(--border);
            border-radius: 14px;

            background: #ffffff;
        }

        .payment-row {
            padding: 13px 14px;

            border-bottom:
                1px solid
                var(--border);
        }

        .payment-row:last-child {
            border-bottom: 0;
        }

        .payment-label {
            color: var(--muted);

            font-size: 10px;
            font-weight: 900;

            text-transform: uppercase;
            letter-spacing: 0.06em;
        }

        .payment-value {
            margin-top: 5px;

            color: var(--text);

            font-size: 15px;
            font-weight: 800;

            line-height: 1.45;

            word-break: break-word;
        }

        .account-number {
            font-size: 19px;
            letter-spacing: 0.03em;
        }

        .instructions {
            margin-top: 16px;

            padding: 14px;

            border-radius: 13px;

            background: #f8fafc;

            color: #475569;

            font-size: 13px;
            line-height: 1.65;
        }

        .payment-note {
            margin-top: 14px;

            color: #475569;

            font-size: 12px;
            line-height: 1.6;
        }

        .actions {
            margin-top: 28px;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            min-height: 48px;

            padding: 13px 22px;

            border-radius: 14px;

            background: var(--button);
            color: #ffffff;

            text-decoration: none;

            font-weight: 900;

            box-shadow:
                0 12px 24px
                rgba(30, 64, 175, 0.20);
        }

        .footer {
            margin-top: 30px;

            color: var(--muted);

            font-size: 12px;
        }

        @media (max-width: 600px) {
            body {
                padding: 16px;
            }

            .success-card {
                margin-top: 14px;

                padding: 34px 20px;

                border-radius: 20px;
            }

            .amount-box {
                align-items: flex-start;
                flex-direction: column;
            }

            .amount-value {
                white-space: normal;
            }

            .button {
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <main class="success-card">

        @if (! empty($branding['logo']))
            <img
                class="logo"
                src="{{ asset('storage/' . $branding['logo']) }}"
                alt="{{ $event->name }}"
            >
        @endif

        <div
            class="icon
            {{ $isSuccessful
                ? 'success'
                : (
                    in_array(
                        $attendee->status,
                        [
                            'pending_approval',
                            'waitlisted',
                        ],
                        true
                    )
                        ? 'warning'
                        : 'danger'
                )
            }}"
        >
            @if ($isSuccessful)
                ✓
            @elseif (
                in_array(
                    $attendee->status,
                    [
                        'pending_approval',
                        'waitlisted',
                    ],
                    true
                )
            )
                !
            @else
                ×
            @endif
        </div>

        <h1>
            {{ $successTitle }}
        </h1>

        <div class="name">
            {{ $attendee->full_name }}
        </div>

        <p class="message">
            {{ $successMessage }}

            <br>

            Event:
            <span class="event-name">
                {{ $event->name }}
            </span>
        </p>

        @if ($paymentRequired)
            <section class="payment-card">
                <h2 class="payment-title">
                    Payment Required
                </h2>

                <p class="payment-subtitle">
                    Your merchandise order has been received.
                    Complete the payment using the details below.
                </p>

                <div class="amount-box">
                    <span class="amount-label">
                        Amount to Pay
                    </span>

                    <span class="amount-value">
                        {{ $paymentCurrency }}
                        {{ number_format($paymentTotal, 2) }}
                    </span>
                </div>

                @if ($paymentStatus)
                    <span class="payment-status">
                        {{ $paymentStatus }}
                    </span>
                @endif

                @if ($hasPaymentDetails)
                    <div class="payment-details">

                        @if (filled($event->payment_method))
                            <div class="payment-row">
                                <div class="payment-label">
                                    Payment Method
                                </div>

                                <div class="payment-value">
                                    {{ $event->payment_method }}
                                </div>
                            </div>
                        @endif

                        @if (filled($event->payment_account_name))
                            <div class="payment-row">
                                <div class="payment-label">
                                    Account Name
                                </div>

                                <div class="payment-value">
                                    {{ $event->payment_account_name }}
                                </div>
                            </div>
                        @endif

                        @if (filled($event->payment_account_number))
                            <div class="payment-row">
                                <div class="payment-label">
                                    Account Number
                                </div>

                                <div class="payment-value account-number">
                                    {{ $event->payment_account_number }}
                                </div>
                            </div>
                        @endif

                    </div>

                    @if (filled($event->payment_instructions))
                        <div class="instructions">
                            {{ $event->payment_instructions }}
                        </div>
                    @endif
                @else
                    <div class="instructions">
                        Payment instructions will be provided by the event organizer.
                    </div>
                @endif

                <div class="payment-note">
                    Please keep your payment confirmation or transaction reference
                    for verification.
                </div>
            </section>
        @endif

        @if ($registrationUrl)
            <div class="actions">
                <a
                    class="button"
                    href="{{ $registrationUrl }}"
                >
                    {{ $canViewBadge
                        ? 'View My Badge'
                        : 'Check Registration Status'
                    }}
                </a>
            </div>
        @endif

        <div class="footer">
            Powered by eLive Events
        </div>

    </main>
</body>
</html>
