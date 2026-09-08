<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>
        Payment Status - {{ $payment->event?->name ?? 'eLive Events' }}
    </title>

    <style>
        :root {
            --elive-navy: #161943;
            --elive-blue: #007ab2;
            --elive-orange: #ff9800;

            --success: #15803d;
            --success-bg: #f0fdf4;

            --warning: #a16207;
            --warning-bg: #fefce8;

            --danger: #b91c1c;
            --danger-bg: #fef2f2;

            --muted: #64748b;
            --background: #f6f8fc;
            --card: #ffffff;
            --border: #e5e7eb;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            font-family:
                Inter,
                ui-sans-serif,
                system-ui,
                -apple-system,
                BlinkMacSystemFont,
                "Segoe UI",
                sans-serif;

            background: var(--background);
            color: #111827;
        }

        .payment-page {
            min-height: 100vh;

            display: flex;
            align-items: center;
            justify-content: center;

            padding: 32px 16px;
        }

        .payment-card {
            width: 100%;
            max-width: 640px;

            background: var(--card);

            border: 1px solid var(--border);
            border-radius: 24px;

            padding: 38px;

            box-shadow:
                0 18px 55px rgba(22, 25, 67, 0.08);
        }

        .brand {
            display: flex;
            align-items: center;
            justify-content: space-between;

            gap: 20px;

            margin-bottom: 34px;
        }

        .brand-name {
            font-size: 24px;
            font-weight: 800;
            color: var(--elive-navy);
        }

        .event-name {
            max-width: 300px;

            color: var(--muted);
            font-size: 14px;
            line-height: 1.4;
            text-align: right;
        }

        .status-icon {
            width: 76px;
            height: 76px;

            border-radius: 50%;

            display: flex;
            align-items: center;
            justify-content: center;

            margin-bottom: 22px;

            font-size: 36px;
            font-weight: 800;
        }

        .status-icon.success {
            background: var(--success-bg);
            color: var(--success);
        }

        .status-icon.processing {
            background: var(--warning-bg);
            color: var(--warning);
        }

        .status-icon.failed {
            background: var(--danger-bg);
            color: var(--danger);
        }

        .status-icon.neutral {
            background: #f1f5f9;
            color: var(--elive-navy);
        }

        h1 {
            margin: 0 0 12px;

            color: var(--elive-navy);

            font-size: 30px;
            line-height: 1.2;
        }

        .status-message {
            margin: 0 0 30px;

            color: var(--muted);

            line-height: 1.7;
            font-size: 15px;
        }

        .details {
            margin-bottom: 28px;

            padding: 20px 0;

            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
        }

        .detail-row {
            display: flex;
            justify-content: space-between;

            gap: 24px;

            padding: 9px 0;
        }

        .detail-label {
            color: var(--muted);
            font-size: 14px;
        }

        .detail-value {
            color: var(--elive-navy);

            font-size: 14px;
            font-weight: 700;

            text-align: right;

            overflow-wrap: anywhere;
        }

        .payment-status {
            display: inline-flex;

            align-items: center;

            padding: 6px 10px;

            border-radius: 999px;

            font-size: 12px;
            font-weight: 800;

            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .payment-status.success {
            color: var(--success);
            background: var(--success-bg);
        }

        .payment-status.processing {
            color: var(--warning);
            background: var(--warning-bg);
        }

        .payment-status.failed {
            color: var(--danger);
            background: var(--danger-bg);
        }

        .payment-status.neutral {
            color: var(--elive-navy);
            background: #f1f5f9;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;

            gap: 12px;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            min-height: 46px;

            padding: 12px 20px;

            border-radius: 12px;

            border: 1px solid transparent;

            font-size: 14px;
            font-weight: 800;

            text-decoration: none;

            transition:
                transform 0.15s ease,
                box-shadow 0.15s ease,
                background 0.15s ease;
        }

        .button:hover {
            transform: translateY(-1px);
        }

        .button-primary {
            background: var(--elive-blue);
            color: #ffffff;

            box-shadow:
                0 8px 20px rgba(0, 122, 178, 0.18);
        }

        .button-secondary {
            background: #ffffff;
            color: var(--elive-navy);

            border-color: var(--border);
        }

        .button-success {
            background: var(--success);
            color: #ffffff;
        }

        .notice {
            margin-top: 24px;

            padding: 14px 16px;

            background: #f8fafc;

            border: 1px solid var(--border);
            border-radius: 12px;

            color: var(--muted);

            font-size: 13px;
            line-height: 1.6;
        }

        @media (max-width: 640px) {
            .payment-page {
                align-items: flex-start;
                padding-top: 20px;
            }

            .payment-card {
                padding: 26px 20px;
                border-radius: 18px;
            }

            .brand {
                align-items: flex-start;
                flex-direction: column;
            }

            .event-name {
                max-width: none;
                text-align: left;
            }

            h1 {
                font-size: 25px;
            }

            .detail-row {
                flex-direction: column;
                gap: 5px;
            }

            .detail-value {
                text-align: left;
            }

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

<div class="payment-page">

    <div class="payment-card">

        <div class="brand">

            <div class="brand-name">
                eLive Events
            </div>

            @if ($payment->event)
                <div class="event-name">
                    {{ $payment->event->name }}
                </div>
            @endif

        </div>


        @if ($payment->isCompleted())

            <div class="status-icon success">
                ✓
            </div>

            <h1>
                Payment Successful
            </h1>

            <p class="status-message">
                Your payment has been received successfully.
                Your event registration is being finalized and any
                eligible badge or confirmation will be prepared automatically.
            </p>

        @elseif (
            $payment->isPending()
            || $payment->isProcessing()
        )

            <div class="status-icon processing">
                …
            </div>

            <h1>
                Payment Processing
            </h1>

            <p class="status-message">
                Your payment is still being processed.
                Please wait while the payment provider confirms the transaction.
                Avoid making another payment unless this transaction fails.
            </p>

        @elseif ($payment->isFailed())

            <div class="status-icon failed">
                ×
            </div>

            <h1>
                Payment Failed
            </h1>

            <p class="status-message">
                We could not confirm this payment.
                You can try the payment again using the button below.
            </p>

        @elseif (
            $payment->status
            === \App\Models\Payment::STATUS_CANCELLED
        )

            <div class="status-icon failed">
                ×
            </div>

            <h1>
                Payment Cancelled
            </h1>

            <p class="status-message">
                This payment was cancelled before completion.
                You can restart the payment whenever you are ready.
            </p>

        @elseif (
            $payment->status
            === \App\Models\Payment::STATUS_REFUNDED
        )

            <div class="status-icon neutral">
                ↺
            </div>

            <h1>
                Payment Refunded
            </h1>

            <p class="status-message">
                This payment has been refunded.
            </p>

        @else

            <div class="status-icon neutral">
                ?
            </div>

            <h1>
                Payment Status
            </h1>

            <p class="status-message">
                The current status of this payment is
                {{ ucfirst(str_replace('_', ' ', $payment->status)) }}.
            </p>

        @endif


        <div class="details">

            <div class="detail-row">
                <div class="detail-label">
                    Event
                </div>

                <div class="detail-value">
                    {{ $payment->event?->name ?? 'Event' }}
                </div>
            </div>


            <div class="detail-row">
                <div class="detail-label">
                    Attendee
                </div>

                <div class="detail-value">
                    {{
                        $payment->attendee?->full_name
                        ?? $payment->attendee?->name
                        ?? 'Attendee'
                    }}
                </div>
            </div>


            <div class="detail-row">
                <div class="detail-label">
                    Amount
                </div>

                <div class="detail-value">
                    {{ strtoupper($payment->currency) }}
                    {{ number_format((float) $payment->amount, 2) }}
                </div>
            </div>


            <div class="detail-row">
                <div class="detail-label">
                    Reference
                </div>

                <div class="detail-value">
                    {{ $payment->reference }}
                </div>
            </div>


            <div class="detail-row">
                <div class="detail-label">
                    Status
                </div>

                <div class="detail-value">

                    @php
                        $statusClass =
                            $payment->isCompleted()
                                ? 'success'
                                : (
                                    $payment->isPending()
                                    || $payment->isProcessing()
                                        ? 'processing'
                                        : (
                                            $payment->isFailed()
                                            || $payment->status
                                                === \App\Models\Payment::STATUS_CANCELLED
                                                ? 'failed'
                                                : 'neutral'
                                        )
                                );
                    @endphp

                    <span class="payment-status {{ $statusClass }}">
                        {{
                            str_replace(
                                '_',
                                ' ',
                                $payment->status
                            )
                        }}
                    </span>

                </div>
            </div>


            @if ($payment->payment_method)

                <div class="detail-row">
                    <div class="detail-label">
                        Payment Method
                    </div>

                    <div class="detail-value">
                        {{ $payment->payment_method }}
                    </div>
                </div>

            @endif


            @if ($payment->paid_at)

                <div class="detail-row">
                    <div class="detail-label">
                        Paid At
                    </div>

                    <div class="detail-value">
                        {{
                            $payment->paid_at
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


        <div class="actions">

            @if (
                $payment->isPending()
                || $payment->isProcessing()
            )

                <a
                    href="{{ route('payments.status', $payment) }}"
                    class="button button-primary"
                >
                    Check Again
                </a>

            @endif


            @if (
                $payment->isFailed()
                || $payment->status
                    === \App\Models\Payment::STATUS_CANCELLED
                || $payment->status
                    === \App\Models\Payment::STATUS_EXPIRED
            )

                <a
                    href="{{ route('payments.pay', $payment) }}"
                    class="button button-primary"
                >
                    Try Payment Again
                </a>

            @endif


            @if (
                $payment->isCompleted()
                && $payment->attendee
                && filled(
                    $payment->attendee->public_token
                )
            )

                <a
                    href="{{ route(
                        'public.attendees.show',
                        $payment->attendee->public_token
                    ) }}"
                    class="button button-success"
                >
                    View Registration
                </a>

            @endif


            @if ($payment->event)

                <a
                    href="{{ route(
                        'public.events.show',
                        $payment->event
                    ) }}"
                    class="button button-secondary"
                >
                    Back to Event
                </a>

            @endif

        </div>


        @if (
            $payment->isPending()
            || $payment->isProcessing()
        )

            <div class="notice">
                Payment confirmation can take a short time depending
                on the payment method. You can safely use
                <strong>Check Again</strong> to refresh the status.
            </div>

        @endif

    </div>

</div>

</body>
</html>