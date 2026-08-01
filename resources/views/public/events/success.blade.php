<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Registration Successful - {{ $event->name }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>

<body style="
    margin: 0;
    background: {{ $branding['background_color'] }};
    color: #0f172a;
    font-family: Arial, sans-serif;
">
    <div style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;">
        <div style="
            width:100%;
            max-width:760px;
            background:#ffffff;
            border:1px solid #e5e7eb;
            border-radius:24px;
            overflow:hidden;
            box-shadow:0 16px 35px rgba(15,23,42,0.12);
        ">
            <div style="height:10px;background:{{ $branding['primary_color'] }};"></div>

            <div style="padding:32px;text-align:center;">
                @if ($branding['logo'])
                    <img
                        src="{{ asset('storage/' . $branding['logo']) }}"
                        alt="Logo"
                        style="
                            width:82px;
                            height:82px;
                            object-fit:contain;
                            border-radius:18px;
                            border:1px solid #e5e7eb;
                            padding:8px;
                            background:white;
                            margin-bottom:18px;
                        "
                    >
                @endif

                <div style="
                    width:72px;
                    height:72px;
                    margin:0 auto 18px auto;
                    border-radius:999px;
                    display:flex;
                    align-items:center;
                    justify-content:center;
                    background:#dcfce7;
                    color:#166534;
                    font-size:38px;
                    font-weight:900;
                ">
                    ✓
                </div>

                <h1 style="
                    margin:0;
                    color:{{ $branding['primary_color'] }};
                    font-size:30px;
                    font-weight:900;
                    line-height:1.2;
                ">
                    Registration Successful
                </h1>

                <p style="margin:12px auto 0 auto;color:#475569;font-size:16px;max-width:580px;line-height:1.6;">
                    {{ $event->registration_success_message ?: 'Thank you. Your registration has been received successfully.' }}
                </p>

                @if ($attendee->status === 'pending_approval')
                    <div style="
                        margin-top:22px;
                        background:#fff7ed;
                        color:#9a3412;
                        border:1px solid #fed7aa;
                        border-radius:16px;
                        padding:16px;
                        font-weight:700;
                        text-align:left;
                        line-height:1.5;
                    ">
                        Your registration is pending approval. You can use the button below to check your approval and badge status later.
                    </div>
                @elseif ($attendee->status === 'waitlisted')
                    <div style="
                        margin-top:22px;
                        background:#eff6ff;
                        color:#1d4ed8;
                        border:1px solid #bfdbfe;
                        border-radius:16px;
                        padding:16px;
                        font-weight:700;
                        text-align:left;
                        line-height:1.5;
                    ">
                        You have been added to the waitlist. You can use the button below to check your waitlist status later.
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
                        text-align:left;
                        line-height:1.5;
                    ">
                        Your registration is confirmed. Use the button below to view your badge and check-in QR code.
                    </div>
                @endif

                <div style="
                    margin-top:24px;
                    display:grid;
                    grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
                    gap:12px;
                    text-align:left;
                ">
                    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;padding:14px;">
                        <div style="font-size:11px;font-weight:800;text-transform:uppercase;color:#64748b;">Attendee</div>
                        <div style="font-size:15px;font-weight:800;margin-top:5px;">{{ $attendee->full_name }}</div>
                    </div>

                    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;padding:14px;">
                        <div style="font-size:11px;font-weight:800;text-transform:uppercase;color:#64748b;">Event</div>
                        <div style="font-size:15px;font-weight:800;margin-top:5px;">{{ $event->name }}</div>
                    </div>

                    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;padding:14px;">
                        <div style="font-size:11px;font-weight:800;text-transform:uppercase;color:#64748b;">Badge Number</div>
                        <div style="font-size:15px;font-weight:800;margin-top:5px;">
                            {{ $attendee->badge_number ?: 'Will be generated' }}
                        </div>
                    </div>

                    <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;padding:14px;">
                        <div style="font-size:11px;font-weight:800;text-transform:uppercase;color:#64748b;">Status</div>

                        @php
                            $statusLabel = strtoupper(str_replace('_', ' ', $attendee->status));

                            $statusStyle = match ($attendee->status) {
                                'registered', 'confirmed', 'approved', 'checked_in' => 'background:#dcfce7;color:#166534;',
                                'pending_approval' => 'background:#ffedd5;color:#9a3412;',
                                'waitlisted' => 'background:#dbeafe;color:#1d4ed8;',
                                'rejected', 'cancelled' => 'background:#fee2e2;color:#991b1b;',
                                default => 'background:#f1f5f9;color:#334155;',
                            };
                        @endphp

                        <div style="
                            display:inline-flex;
                            margin-top:7px;
                            padding:7px 10px;
                            border-radius:999px;
                            font-size:12px;
                            font-weight:900;
                            {{ $statusStyle }}
                        ">
                            {{ $statusLabel }}
                        </div>
                    </div>

                    @if ($attendee->phone)
                        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;padding:14px;">
                            <div style="font-size:11px;font-weight:800;text-transform:uppercase;color:#64748b;">Phone</div>
                            <div style="font-size:15px;font-weight:800;margin-top:5px;">{{ $attendee->phone }}</div>
                        </div>
                    @endif

                    @if ($attendee->email)
                        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;padding:14px;">
                            <div style="font-size:11px;font-weight:800;text-transform:uppercase;color:#64748b;">Email</div>
                            <div style="font-size:15px;font-weight:800;margin-top:5px;word-break:break-word;">{{ $attendee->email }}</div>
                        </div>
                    @endif

                    @if ($event->starts_at)
                        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;padding:14px;">
                            <div style="font-size:11px;font-weight:800;text-transform:uppercase;color:#64748b;">Event Date</div>
                            <div style="font-size:15px;font-weight:800;margin-top:5px;">
                                {{ $event->starts_at->format('d M Y, H:i') }}
                            </div>
                        </div>
                    @endif

                    @if ($event->venue)
                        <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:16px;padding:14px;">
                            <div style="font-size:11px;font-weight:800;text-transform:uppercase;color:#64748b;">Venue</div>
                            <div style="font-size:15px;font-weight:800;margin-top:5px;">{{ $event->venue }}</div>
                        </div>
                    @endif
                </div>

                @if (! empty($registrationStats['capacity']))
                    <div style="
                        margin-top:20px;
                        background:#f8fafc;
                        border:1px solid #e2e8f0;
                        border-radius:16px;
                        padding:16px;
                        text-align:left;
                    ">
                        <div style="display:flex;justify-content:space-between;gap:12px;align-items:center;margin-bottom:8px;">
                            <div style="font-size:13px;font-weight:900;color:#334155;">Registration Capacity</div>
                            <div style="font-size:13px;font-weight:900;color:#334155;">
                                {{ $registrationStats['accepted'] }} / {{ $registrationStats['capacity'] }}
                            </div>
                        </div>

                        @php
                            $capacity = max(1, (int) $registrationStats['capacity']);
                            $accepted = min($capacity, (int) $registrationStats['accepted']);
                            $percentage = round(($accepted / $capacity) * 100);
                        @endphp

                        <div style="height:10px;background:#e2e8f0;border-radius:999px;overflow:hidden;">
                            <div style="
                                width:{{ $percentage }}%;
                                height:10px;
                                background:{{ $branding['primary_color'] }};
                                border-radius:999px;
                            "></div>
                        </div>

                        <div style="font-size:12px;color:#64748b;margin-top:8px;">
                            Remaining slots: {{ $registrationStats['remaining'] ?? 0 }}
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
                    @if ($attendee->public_token)
                        <a
                            href="{{ $attendee->publicUrl() }}"
                            target="_blank"
                            style="
                                display:inline-flex;
                                background:{{ $branding['button_color'] }};
                                color:white;
                                border-radius:14px;
                                padding:12px 18px;
                                font-weight:900;
                                text-decoration:none;
                            "
                        >
                            View My Badge
                        </a>
                    @endif

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
                        Back to Registration Page
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

                @if ($attendee->public_token)
                    <div style="
                        margin-top:18px;
                        background:#f8fafc;
                        border:1px solid #e2e8f0;
                        border-radius:14px;
                        padding:12px;
                        color:#64748b;
                        font-size:12px;
                        word-break:break-all;
                    ">
                        Save this link to check your badge/status later:<br>
                        <strong style="color:#334155;">{{ $attendee->publicUrl() }}</strong>
                    </div>
                @endif

                <div style="text-align:center;color:#64748b;font-size:13px;margin-top:24px;">
                    Powered by eLive Events
                </div>
            </div>
        </div>
    </div>
</body>
</html>