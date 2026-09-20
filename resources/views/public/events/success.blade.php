<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    @php
        /*
        |--------------------------------------------------------------------------
        | Registration Status
        |--------------------------------------------------------------------------
        */

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
        |--------------------------------------------------------------------------
        | Registration Payment
        |--------------------------------------------------------------------------
        |
        | $payment and $paymentRequired are passed by
        | PublicRegistrationController::success().
        |
        */

        $registrationPayment = $payment ?? null;

        $registrationPaymentRequired =
            (bool) ($paymentRequired ?? false);

        $registrationPaymentStatusClass = 'neutral';
        $registrationPaymentTitle = null;
        $registrationPaymentMessage = null;
        $registrationPaymentActionText = null;
        $registrationPaymentActionUrl = null;

        if ($registrationPayment) {
            if ($registrationPayment->isCompleted()) {
                $registrationPaymentStatusClass = 'success';

                $registrationPaymentTitle =
                    'Payment Successful';

                $registrationPaymentMessage =
                    'Your registration payment has been received successfully.';

                $registrationPaymentActionText =
                    'View Payment Status';

                $registrationPaymentActionUrl =
                    route(
                        'payments.status',
                        $registrationPayment
                    );
            } elseif (
                $registrationPayment->isPending()
                || $registrationPayment->isProcessing()
            ) {
                $registrationPaymentStatusClass = 'processing';

                $registrationPaymentTitle =
                    'Payment Processing';

                $registrationPaymentMessage =
                    'Your payment is still being processed. You can check the latest payment status below.';

                $registrationPaymentActionText =
                    'Check Payment Status';

                $registrationPaymentActionUrl =
                    route(
                        'payments.status',
                        $registrationPayment
                    );
            } elseif (
                $registrationPayment->isFailed()
                || in_array(
                    $registrationPayment->status,
                    [
                        \App\Models\Payment::STATUS_CANCELLED,
                        \App\Models\Payment::STATUS_EXPIRED,
                    ],
                    true
                )
            ) {
                $registrationPaymentStatusClass = 'failed';

                $registrationPaymentTitle =
                    'Payment Not Completed';

                $registrationPaymentMessage =
                    'Your payment was not completed. You can try the payment again.';

                $registrationPaymentActionText =
                    'Try Payment Again';

                $registrationPaymentActionUrl =
                    route(
                        'payments.pay',
                        $registrationPayment
                    );
            } elseif (
                $registrationPayment->status
                === \App\Models\Payment::STATUS_REFUNDED
            ) {
                $registrationPaymentStatusClass = 'neutral';

                $registrationPaymentTitle =
                    'Payment Refunded';

                $registrationPaymentMessage =
                    'This payment has been refunded.';

                $registrationPaymentActionText =
                    'View Payment Status';

                $registrationPaymentActionUrl =
                    route(
                        'payments.status',
                        $registrationPayment
                    );
            } else {
                $registrationPaymentTitle =
                    'Payment Status';

                $registrationPaymentMessage =
                    'You can view the current status of your payment below.';

                $registrationPaymentActionText =
                    'View Payment Status';

                $registrationPaymentActionUrl =
                    route(
                        'payments.status',
                        $registrationPayment
                    );
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Merchandise Summary
        |--------------------------------------------------------------------------
        */

        $merchandiseSelections =
            $attendee->merchandiseSelections
            ?? collect();

        $paidMerchandiseSelections =
            $merchandiseSelections
                ->filter(
                    fn ($selection) =>
                        (float) (
                            $selection->total_price
                            ?? 0
                        ) > 0
                );

        $merchandisePaymentTotal =
            (float) $paidMerchandiseSelections
                ->sum(
                    fn ($selection) =>
                        (float) (
                            $selection->total_price
                            ?? 0
                        )
                );

        $merchandisePaymentCurrency =
            $paidMerchandiseSelections
                ->pluck('currency')
                ->filter()
                ->first()
            ?? 'TZS';

        $merchandisePaymentRequired =
            $merchandisePaymentTotal > 0;

        $merchandisePaymentStatus =
            $merchandisePaymentRequired
                ? strtoupper(
                    str_replace(
                        '_',
                        ' ',
                        (string) (
                            $paidMerchandiseSelections
                                ->pluck('payment_status')
                                ->filter()
                                ->first()
                            ?? 'pending'
                        )
                    )
                )
                : null;

        $hasManualPaymentDetails =
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

    <link
        rel="icon"
        href="{{ asset('favicon.ico') }}"
    >

    <link
        rel="stylesheet"
        href="{{ asset('css/creato-font.css') }}"
    >

    <style>
        :root {
            --primary:
                {{ $branding['primary_color'] ?? '#161943' }};

            --button:
                {{ $branding['button_color'] ?? '#161943' }};

            --background:
                {{ $branding['background_color'] ?? '#F7F8FC' }};

            --elive-navy: #161943;
            --elive-blue: #007AB2;
            --elive-orange: #FF9800;

            --text: #0F172A;
            --muted: #667085;
            --border: #E6E8EF;
            --soft: #F7F8FC;

            --success: #15803D;
            --success-bg: #F0FDF4;

            --warning: #A16207;
            --warning-bg: #FEFCE8;

            --danger: #B91C1C;
            --danger-bg: #FEF2F2;

            --font:
                'Creato Display',
                ui-sans-serif,
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                'Segoe UI',
                sans-serif;
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
                radial-gradient(
                    circle at top left,
                    rgba(0, 122, 178, 0.08),
                    transparent 34%
                ),
                linear-gradient(
                    180deg,
                    #F7F8FC 0%,
                    var(--background) 100%
                );

            color: var(--text);

            font-family: var(--font);
        }

        button,
        input,
        select,
        textarea,
        option,
        h1,
        h2,
        h3,
        h4,
        h5,
        h6 {
            font-family: var(--font);
        }

        .success-card {
            position: relative;
            overflow: hidden;

            width: min(100%, 660px);

            padding: 42px 32px;

            background: #FFFFFF;

            border: 1px solid var(--border);
            border-radius: 24px;

            box-shadow:
                0 20px 50px
                rgba(22, 25, 67, 0.10);

            text-align: center;
        }

        .success-card::before {
            content: "";

            position: absolute;

            top: 0;
            left: 0;

            width: 100%;
            height: 5px;

            background:
                linear-gradient(
                    90deg,
                    var(--elive-navy),
                    var(--elive-blue),
                    var(--elive-orange)
                );
        }

        /*
        |--------------------------------------------------------------------------
        | Logo
        |--------------------------------------------------------------------------
        */

        .logo {
            display: block;

            width: auto;
            height: auto;

            max-width: 145px;
            max-height: 80px;

            margin: 0 auto 24px;

            object-fit: contain;
        }

        /*
        |--------------------------------------------------------------------------
        | Registration Status
        |--------------------------------------------------------------------------
        */

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
            background: #DCFCE7;
            color: #15803D;
        }

        .icon.warning {
            background: #FFEDD5;
            color: #C2410C;
        }

        .icon.danger {
            background: #FEE2E2;
            color: #B91C1C;
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
            max-width: 520px;

            margin: 10px auto 0;

            color: #475569;

            font-size: 16px;
            line-height: 1.7;
        }

        .event-name {
            color: var(--primary);
            font-weight: 800;
        }

        /*
        |--------------------------------------------------------------------------
        | Payment Card
        |--------------------------------------------------------------------------
        */

        .payment-card {
            margin-top: 28px;

            padding: 20px;

            border: 1px solid var(--border);
            border-radius: 18px;

            background:
                linear-gradient(
                    145deg,
                    #F8FAFC,
                    #FFFFFF
                );

            text-align: left;
        }

        .payment-card.success {
            border-color: #BBF7D0;
            background: var(--success-bg);
        }

        .payment-card.processing {
            border-color: #FDE68A;
            background: var(--warning-bg);
        }

        .payment-card.failed {
            border-color: #FECACA;
            background: var(--danger-bg);
        }

        .payment-card.neutral {
            border-color: var(--border);
            background: #F8FAFC;
        }

        .payment-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;

            gap: 16px;
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

        .payment-status {
            display: inline-flex;
            align-items: center;

            flex-shrink: 0;

            padding: 6px 10px;

            border-radius: 999px;

            font-size: 11px;
            font-weight: 900;

            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .payment-status.success {
            background: #DCFCE7;
            color: var(--success);
        }

        .payment-status.processing {
            background: #FEF3C7;
            color: var(--warning);
        }

        .payment-status.failed {
            background: #FEE2E2;
            color: var(--danger);
        }

        .payment-status.neutral {
            background: #E2E8F0;
            color: var(--elive-navy);
        }

        /*
        |--------------------------------------------------------------------------
        | Payment Information
        |--------------------------------------------------------------------------
        */

        .amount-box {
            margin-top: 16px;

            display: flex;
            justify-content: space-between;
            align-items: center;

            gap: 14px;

            padding: 14px 15px;

            border: 1px solid var(--border);
            border-radius: 14px;

            background: rgba(255, 255, 255, 0.78);
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

        .payment-details {
            margin-top: 16px;

            overflow: hidden;

            border: 1px solid var(--border);
            border-radius: 14px;

            background: #FFFFFF;
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

            background: #FFFFFF;

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

        /*
        |--------------------------------------------------------------------------
        | Actions
        |--------------------------------------------------------------------------
        */

        .actions {
            margin-top: 28px;

            display: flex;
            flex-wrap: wrap;
            justify-content: center;

            gap: 12px;
        }

        .payment-actions {
            margin-top: 18px;

            display: flex;
            flex-wrap: wrap;

            gap: 10px;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            min-height: 48px;

            padding: 13px 22px;

            border: 1px solid transparent;
            border-radius: 14px;

            background: var(--button);
            color: #FFFFFF;

            text-decoration: none;

            font-size: 14px;
            font-weight: 900;

            box-shadow:
                0 12px 24px
                rgba(22, 25, 67, 0.20);

            transition:
                transform 150ms ease,
                box-shadow 150ms ease,
                opacity 150ms ease;
        }

        .button:hover {
            transform: translateY(-1px);

            box-shadow:
                0 16px 30px
                rgba(22, 25, 67, 0.24);
        }

        .button:focus-visible {
            outline: 3px solid rgba(0, 122, 178, 0.28);
            outline-offset: 3px;
        }

        .button-payment {
            background: var(--elive-blue);
        }

        .button-success {
            background: var(--success);
        }

        .button-secondary {
            background: #FFFFFF;

            color: var(--elive-navy);

            border-color: var(--border);

            box-shadow: none;
        }

        /*
        |--------------------------------------------------------------------------
        | Footer
        |--------------------------------------------------------------------------
        */

        .footer {
            margin-top: 30px;

            color: var(--muted);

            font-size: 12px;
        }

        .footer strong {
            color: var(--elive-navy);
            font-weight: 800;
        }

        /*
        |--------------------------------------------------------------------------
        | Mobile
        |--------------------------------------------------------------------------
        */

        @media (max-width: 600px) {
            body {
                padding: 16px;
            }

            .success-card {
                margin-top: 14px;

                padding: 34px 20px;

                border-radius: 20px;
            }

            .logo {
                max-width: 125px;
                max-height: 70px;
            }

            .payment-header {
                flex-direction: column;
            }

            .amount-box {
                align-items: flex-start;
                flex-direction: column;
            }

            .amount-value {
                white-space: normal;
            }

            .payment-actions,
            .actions {
                flex-direction: column;
            }

            .button {
                width: 100%;
            }
        }
    </style>
</head>

<body>

    <main class="success-card">

        {{-- Event logo first, eLive logo as fallback --}}
        @if (! empty($branding['logo']))

            <img
                class="logo"
                src="{{ asset('storage/' . $branding['logo']) }}"
                alt="{{ $event->name }}"
            >

        @else

            <img
                class="logo"
                src="{{ asset('eLive-Logo.png') }}"
                alt="eLive Events"
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


        {{-- Registration payment through Pesapal --}}
        @if ($registrationPayment)

            <section
                class="payment-card
                {{ $registrationPaymentStatusClass }}"
            >

                <div class="payment-header">

                    <div>

                        <h2 class="payment-title">
                            {{ $registrationPaymentTitle }}
                        </h2>

                        <p class="payment-subtitle">
                            {{ $registrationPaymentMessage }}
                        </p>

                    </div>


                    <span
                        class="payment-status
                        {{ $registrationPaymentStatusClass }}"
                    >
                        {{
                            str_replace(
                                '_',
                                ' ',
                                $registrationPayment->status
                            )
                        }}
                    </span>

                </div>


                <div class="amount-box">

                    <span class="amount-label">
                        Registration Payment
                    </span>

                    <span class="amount-value">
                        {{
                            strtoupper(
                                $registrationPayment->currency
                            )
                        }}

                        {{
                            number_format(
                                (float)
                                $registrationPayment->amount,
                                2
                            )
                        }}
                    </span>

                </div>


                <div class="payment-details">

                    <div class="payment-row">

                        <div class="payment-label">
                            Payment Reference
                        </div>

                        <div class="payment-value">
                            {{
                                $registrationPayment->reference
                            }}
                        </div>

                    </div>


                    @if (
                        filled(
                            $registrationPayment->payment_method
                        )
                    )

                        <div class="payment-row">

                            <div class="payment-label">
                                Payment Method
                            </div>

                            <div class="payment-value">
                                {{
                                    $registrationPayment
                                        ->payment_method
                                }}
                            </div>

                        </div>

                    @endif


                    @if ($registrationPayment->paid_at)

                        <div class="payment-row">

                            <div class="payment-label">
                                Paid At
                            </div>

                            <div class="payment-value">
                                {{
                                    $registrationPayment
                                        ->paid_at
                                        ->timezone(
                                            config(
                                                'app.timezone'
                                            )
                                        )
                                        ->format(
                                            'd M Y, H:i'
                                        )
                                }}
                            </div>

                        </div>

                    @endif

                </div>


                @if ($registrationPaymentActionUrl)

                    <div class="payment-actions">

                        <a
                            href="{{
                                $registrationPaymentActionUrl
                            }}"
                            class="
                                button
                                {{
                                    $registrationPayment
                                        ->isCompleted()
                                        ? 'button-success'
                                        : 'button-payment'
                                }}
                            "
                        >
                            {{
                                $registrationPaymentActionText
                            }}
                        </a>

                    </div>

                @endif


                @if (
                    $registrationPayment->isPending()
                    || $registrationPayment->isProcessing()
                )

                    <div class="payment-note">
                        Payment confirmation can take a short
                        time depending on the payment method.
                        Please avoid making another payment
                        while this transaction is still processing.
                    </div>

                @endif

            </section>


        @elseif (
            $registrationPaymentRequired
            && $isSuccessful
        )

            {{-- Registration requires payment but no Payment record exists --}}
            <section class="payment-card processing">

                <div class="payment-header">

                    <div>

                        <h2 class="payment-title">
                            Payment Required
                        </h2>

                        <p class="payment-subtitle">
                            Your registration has been received,
                            but a payment transaction is not
                            currently available.
                        </p>

                    </div>

                    <span class="payment-status processing">
                        Required
                    </span>

                </div>


                <div class="instructions">
                    Please contact the event organizer if you
                    were not redirected to the payment page.
                </div>

            </section>

        @endif


        {{-- Merchandise payment summary --}}
        @if ($merchandisePaymentRequired)

            <section class="payment-card neutral">

                <div class="payment-header">

                    <div>

                        <h2 class="payment-title">
                            Merchandise Payment
                        </h2>

                        <p class="payment-subtitle">
                            Your merchandise selection has been
                            recorded.
                        </p>

                    </div>


                    @if ($merchandisePaymentStatus)

                        <span class="payment-status processing">
                            {{ $merchandisePaymentStatus }}
                        </span>

                    @endif

                </div>


                <div class="amount-box">

                    <span class="amount-label">
                        Merchandise Total
                    </span>

                    <span class="amount-value">
                        {{ $merchandisePaymentCurrency }}

                        {{
                            number_format(
                                $merchandisePaymentTotal,
                                2
                            )
                        }}
                    </span>

                </div>


                @if ($hasManualPaymentDetails)

                    <div class="payment-details">

                        @if (
                            filled(
                                $event->payment_method
                            )
                        )

                            <div class="payment-row">

                                <div class="payment-label">
                                    Payment Method
                                </div>

                                <div class="payment-value">
                                    {{ $event->payment_method }}
                                </div>

                            </div>

                        @endif


                        @if (
                            filled(
                                $event->payment_account_name
                            )
                        )

                            <div class="payment-row">

                                <div class="payment-label">
                                    Account Name
                                </div>

                                <div class="payment-value">
                                    {{
                                        $event
                                            ->payment_account_name
                                    }}
                                </div>

                            </div>

                        @endif


                        @if (
                            filled(
                                $event->payment_account_number
                            )
                        )

                            <div class="payment-row">

                                <div class="payment-label">
                                    Account Number
                                </div>

                                <div
                                    class="
                                        payment-value
                                        account-number
                                    "
                                >
                                    {{
                                        $event
                                            ->payment_account_number
                                    }}
                                </div>

                            </div>

                        @endif

                    </div>


                    @if (
                        filled(
                            $event->payment_instructions
                        )
                    )

                        <div class="instructions">
                            {{
                                $event
                                    ->payment_instructions
                            }}
                        </div>

                    @endif

                @else

                    <div class="instructions">
                        Payment instructions will be provided
                        by the event organizer.
                    </div>

                @endif


                <div class="payment-note">
                    Please keep your payment confirmation or
                    transaction reference for verification.
                </div>

            </section>

        @endif


        {{-- Registration / badge --}}
        @if ($registrationUrl)

            <div class="actions">

                <a
                    class="button"
                    href="{{ $registrationUrl }}"
                >
                    {{
                        $canViewBadge
                            ? 'View My Badge'
                            : 'Check Registration Status'
                    }}
                </a>

                @if (
                    $registrationPayment
                    && ! $registrationPayment
                        ->isCompleted()
                )

                    <a
                        class="button button-secondary"
                        href="{{
                            route(
                                'payments.status',
                                $registrationPayment
                            )
                        }}"
                    >
                        Payment Status
                    </a>

                @endif

            </div>

        @endif


        <div class="footer">
            Powered by
            <strong>eLive Events</strong>
        </div>

    </main>

</body>
</html>