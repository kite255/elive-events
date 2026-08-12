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
            align-items: center;
            justify-content: center;

            padding: 20px;

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
            width: min(
                100%,
                560px
            );

            padding: 42px 32px;

            background: #ffffff;

            border:
                1px solid
                var(--border);

            border-radius: 24px;

            box-shadow:
                0 20px 50px
                rgba(
                    15,
                    23,
                    42,
                    0.10
                );

            text-align: center;
        }

        .logo {
            display: block;

            max-width: 90px;
            max-height: 90px;

            margin:
                0 auto
                22px;

            object-fit: contain;
        }

        .icon {
            width: 76px;
            height: 76px;

            margin:
                0 auto
                22px;

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

            font-size:
                clamp(
                    28px,
                    5vw,
                    38px
                );

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
            max-width: 460px;

            margin:
                10px auto
                0;

            color: #475569;

            font-size: 16px;
            line-height: 1.7;
        }

        .event-name {
            color: var(--primary);
            font-weight: 800;
        }

        .actions {
            margin-top: 28px;
        }

        .button {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            min-height: 48px;

            padding:
                13px 22px;

            border-radius: 14px;

            background: var(--button);
            color: #ffffff;

            text-decoration: none;

            font-weight: 900;

            box-shadow:
                0 12px 24px
                rgba(
                    30,
                    64,
                    175,
                    0.20
                );
        }

        .footer {
            margin-top: 30px;

            color: var(--muted);

            font-size: 12px;
        }

        @media (
            max-width: 600px
        ) {
            body {
                align-items: flex-start;
                padding: 16px;
            }

            .success-card {
                margin-top: 30px;

                padding:
                    34px
                    20px;

                border-radius: 20px;
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