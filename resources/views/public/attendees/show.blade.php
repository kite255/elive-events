<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $attendee->full_name }} - {{ $event->name }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>

<body style="
    margin:0;
    background:{{ $branding['background_color'] }};
    color:#0f172a;
    font-family:Arial, sans-serif;
">
    <div style="min-height:100vh;padding:28px 16px;">
        <div style="max-width:980px;margin:0 auto;">
            <div style="
                background:#ffffff;
                border:1px solid #e5e7eb;
                border-radius:24px;
                overflow:hidden;
                box-shadow:0 16px 35px rgba(15,23,42,0.12);
            ">
                @if ($branding['banner'])
                    <div style="
                        height:220px;
                        background-image:url('{{ asset('storage/' . $branding['banner']) }}');
                        background-size:cover;
                        background-position:center;
                    "></div>
                @else
                    <div style="height:14px;background:{{ $branding['primary_color'] }};"></div>
                @endif

                <div style="padding:28px;">
                    <div style="display:flex;gap:18px;align-items:center;flex-wrap:wrap;">
                        @if ($branding['logo'])
                            <img
                                src="{{ asset('storage/' . $branding['logo']) }}"
                                alt="Logo"
                                style="
                                    width:76px;
                                    height:76px;
                                    object-fit:contain;
                                    border-radius:16px;
                                    border:1px solid #e5e7eb;
                                    padding:8px;
                                    background:white;
                                "
                            >
                        @endif

                        <div style="flex:1;min-width:260px;">
                            <h1 style="
                                margin:0;
                                color:{{ $branding['primary_color'] }};
                                font-size:30px;
                                font-weight:900;
                                line-height:1.2;
                            ">
                                {{ $attendee->full_name }}
                            </h1>

                            <p style="margin:8px 0 0 0;color:#64748b;font-size:15px;">
                                {{ $event->name }}
                            </p>
                        </div>

                        @php
                            $statusLabel = strtoupper(str_replace('_', ' ', $attendee->status));

                            $statusStyle = match ($attendee->status) {
                                'registered', 'confirmed', 'approved' => 'background:#dcfce7;color:#166534;',
                                'checked_in' => 'background:#dbeafe;color:#1d4ed8;',
                                'pending_approval' => 'background:#ffedd5;color:#9a3412;',
                                'waitlisted' => 'background:#e0e7ff;color:#3730a3;',
                                'rejected', 'cancelled' => 'background:#fee2e2;color:#991b1b;',
                                default => 'background:#f1f5f9;color:#334155;',
                            };

                            $qrPath = 'qr-codes/attendee-' . $attendee->id . '.svg';
                            $qrPublicUrl = asset('storage/' . $qrPath);
                        @endphp

                        <div style="
                            display:inline-flex;
                            padding:9px 14px;
                            border-radius:999px;
                            font-size:12px;
                            font-weight:900;
                            {{ $statusStyle }}
                        ">
                            {{ $statusLabel }}
                        </div>
                    </div>

                    @if ($attendee->status === 'pending_approval')
                        <div style="
                            margin-top:22px;
                            background:#fff7ed;
                            color:#9a3412;
                            border:1px solid #fed7aa;
                            border-radius:16px;
                            padding:16px;
                            font-weight:700;
                            line-height:1.5;
                        ">
                            Your registration is pending approval. Your badge will be available after the event organizer approves your registration.
                        </div>
                    @elseif ($attendee->status === 'waitlisted')
                        <div style="
                            margin-top:22px;
                            background:#eef2ff;
                            color:#3730a3;
                            border:1px solid #c7d2fe;
                            border-radius:16px;
                            padding:16px;
                            font-weight:700;
                            line-height:1.5;
                        ">
                            You are currently on the waitlist. The organizer will contact you if a space becomes available.
                        </div>
                    @elseif (in_array($attendee->status, ['rejected', 'cancelled'], true))
                        <div style="
                            margin-top:22px;
                            background:#fee2e2;
                            color:#991b1b;
                            border:1px solid #fecaca;
                            border-radius:16px;
                            padding:16px;
                            font-weight:700;
                            line-height:1.5;
                        ">
                            This registration is not active. Please contact the event organizer for assistance.
                        </div>
                    @else
                        <div style="
                            margin-top:22px;
                            background:#ecfdf5;
                            color:#047857;
                            border:1px solid #a7f3d0;
                            border-radius:16px;
                            padding:16px;
                            font-weight:700;
                            line-height:1.5;
                        ">
                            Your registration is active. Please present your QR code or badge at the entrance.
                        </div>
                    @endif

                    <div style="
                        margin-top:24px;
                        display:grid;
                        grid-template-columns:repeat(auto-fit,minmax(210px,1fr));
                        gap:12px;
                    ">
                        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;padding:14px;">
                            <div style="font-size:11px;font-weight:800;text-transform:uppercase;color:#64748b;">Badge Number</div>
                            <div style="font-size:15px;font-weight:900;margin-top:5px;">{{ $attendee->badge_number }}</div>
                        </div>

                        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;padding:14px;">
                            <div style="font-size:11px;font-weight:800;text-transform:uppercase;color:#64748b;">Category</div>
                            <div style="font-size:15px;font-weight:900;margin-top:5px;">{{ $attendee->category?->name ?: 'General' }}</div>
                        </div>

                        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;padding:14px;">
                            <div style="font-size:11px;font-weight:800;text-transform:uppercase;color:#64748b;">Badge Type</div>
                            <div style="font-size:15px;font-weight:900;margin-top:5px;">{{ $attendee->badgeType?->name ?: 'Default' }}</div>
                        </div>

                        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;padding:14px;">
                            <div style="font-size:11px;font-weight:800;text-transform:uppercase;color:#64748b;">Event Date</div>
                            <div style="font-size:15px;font-weight:900;margin-top:5px;">
                                {{ $event->starts_at?->format('d M Y, H:i') ?? 'To be announced' }}
                            </div>
                        </div>

                        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;padding:14px;">
                            <div style="font-size:11px;font-weight:800;text-transform:uppercase;color:#64748b;">Venue</div>
                            <div style="font-size:15px;font-weight:900;margin-top:5px;">{{ $event->venue ?: 'To be announced' }}</div>
                        </div>

                        @if ($attendee->organization_name)
                            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;padding:14px;">
                                <div style="font-size:11px;font-weight:800;text-transform:uppercase;color:#64748b;">Organization</div>
                                <div style="font-size:15px;font-weight:900;margin-top:5px;">{{ $attendee->organization_name }}</div>
                            </div>
                        @endif
                    </div>

                    <div style="
                        margin-top:24px;
                        display:grid;
                        grid-template-columns:repeat(auto-fit,minmax(280px,1fr));
                        gap:18px;
                        align-items:start;
                    ">
                        <div style="
                            border:1px solid #e2e8f0;
                            border-radius:20px;
                            padding:20px;
                            text-align:center;
                            background:#ffffff;
                        ">
                            <h2 style="
                                margin:0 0 14px 0;
                                font-size:20px;
                                font-weight:900;
                                color:{{ $branding['primary_color'] }};
                            ">
                                Event Badge
                            </h2>

                            @if ($attendee->hasBadge())
                                <div style="
                                    border:1px solid #e2e8f0;
                                    border-radius:16px;
                                    padding:12px;
                                    background:#f8fafc;
                                    overflow:hidden;
                                ">
                                    <img
                                        src="{{ $attendee->badgeUrl() }}"
                                        alt="Badge"
                                        style="
                                            width:100%;
                                            max-width:320px;
                                            height:auto;
                                            border-radius:12px;
                                        "
                                    >
                                </div>

                                <a
                                    href="{{ $attendee->badgeUrl() }}"
                                    target="_blank"
                                    style="
                                        display:inline-flex;
                                        margin-top:14px;
                                        background:{{ $branding['button_color'] }};
                                        color:white;
                                        border-radius:14px;
                                        padding:12px 18px;
                                        font-weight:900;
                                        text-decoration:none;
                                    "
                                >
                                    Download Badge
                                </a>
                            @else
                                <div style="
                                    background:#f8fafc;
                                    border:1px dashed #cbd5e1;
                                    border-radius:16px;
                                    padding:28px;
                                    color:#64748b;
                                    font-weight:700;
                                    line-height:1.5;
                                ">
                                    Badge is not available yet.
                                    @if ($attendee->status === 'pending_approval')
                                        It will be generated after approval.
                                    @endif
                                </div>
                            @endif
                        </div>

                        <div style="
                            border:1px solid #e2e8f0;
                            border-radius:20px;
                            padding:20px;
                            background:#ffffff;
                        ">
                            <h2 style="
                                margin:0 0 14px 0;
                                font-size:20px;
                                font-weight:900;
                                color:{{ $branding['primary_color'] }};
                                text-align:center;
                            ">
                                Check-in QR
                            </h2>

                            @if ($attendee->qrToken)
                                <div style="
                                    background:#f8fafc;
                                    border:1px solid #e2e8f0;
                                    border-radius:16px;
                                    padding:18px;
                                    text-align:center;
                                ">
                                    <img
                                        src="{{ $qrPublicUrl }}"
                                        alt="Check-in QR Code"
                                        style="
                                            width:220px;
                                            max-width:100%;
                                            height:auto;
                                            background:white;
                                            padding:12px;
                                            border-radius:16px;
                                            border:1px solid #e2e8f0;
                                        "
                                    >

                                    <div style="margin-top:10px;font-size:13px;color:#64748b;">
                                        QR token ending:
                                    </div>

                                    <div style="font-size:18px;font-weight:900;margin-top:4px;">
                                        {{ $attendee->qrToken->token_last4 }}
                                    </div>
                                </div>

                                <p style="margin:12px 0 0 0;color:#64748b;font-size:13px;line-height:1.5;text-align:center;">
                                    Use this QR code at the entrance for check-in.
                                </p>
                            @else
                                <div style="
                                    background:#fff7ed;
                                    color:#9a3412;
                                    border:1px solid #fed7aa;
                                    border-radius:16px;
                                    padding:18px;
                                    font-weight:700;
                                    text-align:center;
                                ">
                                    QR token is not available yet.
                                </div>
                            @endif
                        </div>
                    </div>

                    @if ($attendee->registrationAnswers && $attendee->registrationAnswers->count())
                        <div style="
                            margin-top:24px;
                            border:1px solid #e2e8f0;
                            border-radius:20px;
                            padding:20px;
                            background:#ffffff;
                        ">
                            <h2 style="
                                margin:0 0 14px 0;
                                font-size:20px;
                                font-weight:900;
                                color:{{ $branding['primary_color'] }};
                            ">
                                Submitted Information
                            </h2>

                            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;">
                                @foreach ($attendee->registrationAnswers as $answer)
                                    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:14px;padding:14px;">
                                        <div style="font-size:11px;font-weight:800;text-transform:uppercase;color:#64748b;">
                                            {{ $answer->field?->label ?? 'Question' }}
                                        </div>

                                        <div style="font-size:14px;font-weight:800;margin-top:5px;word-break:break-word;">
                                            @php
                                                $answerValue = $answer->answer;

                                                $decoded = is_string($answerValue) ? json_decode($answerValue, true) : null;

                                                if (is_array($decoded)) {
                                                    $answerValue = implode(', ', $decoded);
                                                }
                                            @endphp

                                            {{ $answerValue }}
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div style="
                        margin-top:24px;
                        display:flex;
                        gap:12px;
                        justify-content:center;
                        flex-wrap:wrap;
                    ">
                        <a
                            href="{{ route('public.registration.show', $event) }}"
                            style="
                                display:inline-flex;
                                background:#334155;
                                color:white;
                                border-radius:14px;
                                padding:12px 18px;
                                font-weight:900;
                                text-decoration:none;
                            "
                        >
                            Event Registration Page
                        </a>

                        @if ($branding['support_phone'])
                            <a
                                href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $branding['support_phone']) }}"
                                style="
                                    display:inline-flex;
                                    background:#16a34a;
                                    color:white;
                                    border-radius:14px;
                                    padding:12px 18px;
                                    font-weight:900;
                                    text-decoration:none;
                                "
                            >
                                Contact Support
                            </a>
                        @elseif ($branding['support_email'])
                            <a
                                href="mailto:{{ $branding['support_email'] }}"
                                style="
                                    display:inline-flex;
                                    background:#334155;
                                    color:white;
                                    border-radius:14px;
                                    padding:12px 18px;
                                    font-weight:900;
                                    text-decoration:none;
                                "
                            >
                                Contact Support
                            </a>
                        @endif
                    </div>
                </div>
            </div>

            <div style="text-align:center;color:#64748b;font-size:13px;margin-top:18px;">
                Powered by eLive Events
            </div>
        </div>
    </div>
</body>
</html>