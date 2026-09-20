<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">

    <title>{{ $subject ?? 'eLive Events' }}</title>

    <style>
        /* Mobile email adjustments.
           Critical visual styles are still inline for Gmail compatibility. */
        @media only screen and (max-width: 620px) {
            .email-wrapper {
                padding: 10px 0 !important;
            }

            .email-container {
                width: 100% !important;
                max-width: 100% !important;
                border-radius: 0 !important;
            }

            .email-padding {
                padding-left: 20px !important;
                padding-right: 20px !important;
            }

            .brand-header {
                padding: 20px !important;
            }

            .brand-logo-cell,
            .brand-event-cell {
                display: block !important;
                width: 100% !important;
                text-align: left !important;
            }

            .brand-event-cell {
                padding-top: 16px !important;
            }

            .email-logo {
                width: 135px !important;
                max-width: 135px !important;
            }

            .header-event-name {
                font-size: 17px !important;
                line-height: 1.35 !important;
            }

            .header-event-venue {
                font-size: 13px !important;
                line-height: 1.5 !important;
            }

            .hero-image {
                width: 100% !important;
                max-width: 100% !important;
                height: auto !important;
            }

            .hero-copy {
                padding: 22px 20px 24px !important;
            }

            .hero-title {
                font-size: 34px !important;
                line-height: 1.08 !important;
            }

            .event-title {
                font-size: 24px !important;
            }

            .detail-column {
                display: block !important;
                width: 100% !important;
                box-sizing: border-box !important;
            }

            .main-message {
                padding-top: 28px !important;
            }
        }
    </style>
</head>

<body
    style="
        margin:0;
        padding:0;
        background:#F7F8FC;
        font-family:Arial,Helvetica,sans-serif;
        color:#161943;
        -webkit-text-size-adjust:100%;
        -ms-text-size-adjust:100%;
    "
>

@php
    /*
     * Optional Daily Highlights / campaign hero variables:
     *
     * $heroImageUrl  Absolute public URL to the thumbnail/banner image.
     * $heroTitle     Example: "Day 2 Highlights".
     * $heroDate      Example: "24 August 2026".
     *
     * Using a real <img> instead of CSS background-image is intentional:
     * Gmail mobile is substantially more reliable with normal image tags.
     */
    $resolvedHeroImageUrl = $heroImageUrl ?? null;
    $resolvedHeroTitle = $heroTitle ?? null;
    $resolvedHeroDate = $heroDate ?? null;
@endphp

<table
    role="presentation"
    width="100%"
    cellspacing="0"
    cellpadding="0"
    border="0"
    class="email-wrapper"
    style="
        width:100%;
        background:#F7F8FC;
        padding:30px 10px;
        margin:0;
    "
>
    <tr>
        <td align="center">

            <table
                role="presentation"
                width="100%"
                cellspacing="0"
                cellpadding="0"
                border="0"
                class="email-container"
                style="
                    width:100%;
                    max-width:680px;
                    background:#FFFFFF;
                    border-radius:14px;
                    overflow:hidden;
                    box-shadow:0 5px 25px rgba(22,25,67,.08);
                "
            >

                {{-- ========================================================= --}}
                {{-- ELIVE / EVENT HEADER                                      --}}
                {{-- ========================================================= --}}

                <tr>
                    <td
                        class="brand-header"
                        style="
                            padding:20px 28px;
                            background:#FFFFFF;
                        "
                    >
                        <table
                            role="presentation"
                            width="100%"
                            cellspacing="0"
                            cellpadding="0"
                            border="0"
                            style="width:100%;"
                        >
                            <tr>
                                <td
                                    class="brand-logo-cell"
                                    width="35%"
                                    valign="middle"
                                    align="left"
                                    style="width:35%;"
                                >
                                    <img
                                        src="{{ url('/eLive-Logo.png') }}"
                                        alt="eLive Events"
                                        width="145"
                                        class="email-logo"
                                        style="
                                            width:145px;
                                            max-width:145px;
                                            height:auto;
                                            display:block;
                                            border:0;
                                            outline:none;
                                            text-decoration:none;
                                        "
                                    >
                                </td>

                                <td
                                    class="brand-event-cell"
                                    width="65%"
                                    valign="middle"
                                    align="right"
                                    style="
                                        width:65%;
                                        text-align:right;
                                    "
                                >
                                    <div
                                        class="header-event-name"
                                        style="
                                            font-size:14px;
                                            line-height:1.4;
                                            font-weight:700;
                                            color:#161943;
                                        "
                                    >
                                        {{ $event?->name ?? 'eLive Events' }}
                                    </div>

                                    @if($event?->venue)
                                        <div
                                            class="header-event-venue"
                                            style="
                                                margin-top:3px;
                                                font-size:12px;
                                                line-height:1.45;
                                                color:#667085;
                                            "
                                        >
                                            {{ $event->venue }}
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>


                {{-- ========================================================= --}}
                {{-- OPTIONAL DAILY HIGHLIGHTS HERO                            --}}
                {{-- Real IMG for Gmail mobile compatibility                   --}}
                {{-- ========================================================= --}}

                @if(!empty($resolvedHeroImageUrl))
                    <tr>
                        <td
                            style="
                                padding:0;
                                margin:0;
                                background:#0F3B5D;
                                line-height:0;
                                font-size:0;
                            "
                        >
                            <img
                                src="{{ $resolvedHeroImageUrl }}"
                                alt="{{ $resolvedHeroTitle ?: ($event?->name ?? 'Event highlights') }}"
                                width="680"
                                class="hero-image"
                                style="
                                    display:block;
                                    width:100%;
                                    max-width:680px;
                                    height:auto;
                                    border:0;
                                    outline:none;
                                    text-decoration:none;
                                    margin:0;
                                    padding:0;
                                "
                            >
                        </td>
                    </tr>

                    @if(!empty($resolvedHeroTitle) || !empty($resolvedHeroDate))
                        <tr>
                            <td
                                class="hero-copy"
                                style="
                                    background:#0F3B5D;
                                    padding:22px 30px 26px;
                                    color:#FFFFFF;
                                "
                            >
                                @if(!empty($resolvedHeroTitle))
                                    <div
                                        class="hero-title"
                                        style="
                                            margin:0;
                                            font-size:40px;
                                            line-height:1.08;
                                            font-weight:800;
                                            color:#FFFFFF;
                                        "
                                    >
                                        {{ $resolvedHeroTitle }}
                                    </div>
                                @endif

                                @if(!empty($resolvedHeroDate))
                                    <table
                                        role="presentation"
                                        cellspacing="0"
                                        cellpadding="0"
                                        border="0"
                                        style="margin-top:16px;"
                                    >
                                        <tr>
                                            <td
                                                bgcolor="#FF9800"
                                                style="
                                                    background:#FF9800;
                                                    border-radius:999px;
                                                    padding:7px 14px;
                                                    color:#FFFFFF;
                                                    font-size:12px;
                                                    line-height:1.2;
                                                    font-weight:700;
                                                "
                                            >
                                                {{ $resolvedHeroDate }}
                                            </td>
                                        </tr>
                                    </table>
                                @endif
                            </td>
                        </tr>
                    @endif
                @else
                    {{-- ===================================================== --}}
                    {{-- STANDARD EVENT HERO                                   --}}
                    {{-- ===================================================== --}}

                    <tr>
                        <td
                            align="center"
                            class="email-padding"
                            style="
                                background:#161943;
                                padding:34px 35px;
                                color:#FFFFFF;
                            "
                        >
                            <div
                                style="
                                    font-size:12px;
                                    line-height:1.4;
                                    text-transform:uppercase;
                                    letter-spacing:2px;
                                    color:#D8DCE6;
                                    margin-bottom:10px;
                                "
                            >
                                {{ $emailLabel ?? 'Event Communication' }}
                            </div>

                            <div
                                class="event-title"
                                style="
                                    font-size:30px;
                                    line-height:1.25;
                                    font-weight:700;
                                    color:#FFFFFF;
                                "
                            >
                                {{ $event?->name ?? 'eLive Events' }}
                            </div>

                            @if($event?->starts_at)
                                <div
                                    style="
                                        font-size:14px;
                                        line-height:1.6;
                                        margin-top:14px;
                                        color:#E7E9F0;
                                    "
                                >
                                    {{ $event->starts_at->format('d M Y') }}

                                    @if(
                                        $event->ends_at
                                        && ! $event->starts_at->isSameDay($event->ends_at)
                                    )
                                        – {{ $event->ends_at->format('d M Y') }}
                                    @endif

                                    @if($event?->venue)
                                        &nbsp; • &nbsp; {{ $event->venue }}
                                    @endif
                                </div>
                            @endif
                        </td>
                    </tr>
                @endif


                {{-- ========================================================= --}}
                {{-- OPTIONAL ALERT                                            --}}
                {{-- ========================================================= --}}

                @if(!empty($alertTitle) || !empty($alertMessage))
                    <tr>
                        <td
                            class="email-padding"
                            style="padding:30px 45px 0;"
                        >
                            <table
                                role="presentation"
                                width="100%"
                                cellspacing="0"
                                cellpadding="0"
                                border="0"
                                style="
                                    width:100%;
                                    background:#FFF8E8;
                                    border:1px solid #FFD899;
                                    border-radius:10px;
                                "
                            >
                                <tr>
                                    <td style="padding:18px 20px;">
                                        @if(!empty($alertTitle))
                                            <div
                                                style="
                                                    font-size:14px;
                                                    line-height:1.5;
                                                    font-weight:700;
                                                    color:#9A5A00;
                                                    margin-bottom:6px;
                                                "
                                            >
                                                {{ $alertTitle }}
                                            </div>
                                        @endif

                                        @if(!empty($alertMessage))
                                            <div
                                                style="
                                                    font-size:14px;
                                                    line-height:1.65;
                                                    color:#6F4B00;
                                                "
                                            >
                                                {{ $alertMessage }}
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                @endif


                {{-- ========================================================= --}}
                {{-- MAIN MESSAGE                                              --}}
                {{-- ========================================================= --}}

                <tr>
                    <td
                        class="email-padding main-message"
                        style="
                            padding:34px 45px 24px;
                            font-size:16px;
                            line-height:1.7;
                            color:#161943;
                        "
                    >
                        @if($attendee)
                            <div
                                style="
                                    font-size:22px;
                                    line-height:1.4;
                                    font-weight:700;
                                    color:#161943;
                                    margin-bottom:18px;
                                "
                            >
                                Hello {{ $attendee->full_name ?? 'Attendee' }},
                            </div>
                        @endif

                        @if(!empty($messageBody))
                            <div
                                style="
                                    margin:0;
                                    color:#475467;
                                    font-size:16px;
                                    line-height:1.75;
                                "
                            >
                                {!! nl2br(e($messageBody)) !!}
                            </div>
                        @endif
                    </td>
                </tr>


                {{-- ========================================================= --}}
                {{-- EVENT INFORMATION                                         --}}
                {{-- ========================================================= --}}

                @if($event)
                    <tr>
                        <td
                            class="email-padding"
                            style="padding:0 45px 30px;"
                        >
                            <table
                                role="presentation"
                                width="100%"
                                cellspacing="0"
                                cellpadding="0"
                                border="0"
                                style="
                                    width:100%;
                                    background:#F7FAFC;
                                    border:1px solid #E6E8EF;
                                    border-radius:12px;
                                    overflow:hidden;
                                "
                            >
                                <tr>
                                    <td
                                        width="50%"
                                        valign="top"
                                        class="detail-column"
                                        style="
                                            width:50%;
                                            padding:20px;
                                            border-bottom:1px solid #E6E8EF;
                                            box-sizing:border-box;
                                        "
                                    >
                                        <div
                                            style="
                                                font-size:11px;
                                                line-height:1.4;
                                                color:#667085;
                                                text-transform:uppercase;
                                                letter-spacing:.5px;
                                            "
                                        >
                                            Category
                                        </div>

                                        <div
                                            style="
                                                margin-top:6px;
                                                font-size:15px;
                                                line-height:1.5;
                                                font-weight:700;
                                                color:#161943;
                                            "
                                        >
                                            {{ $attendee?->category?->name ?? '-' }}
                                        </div>
                                    </td>

                                    <td
                                        width="50%"
                                        valign="top"
                                        class="detail-column"
                                        style="
                                            width:50%;
                                            padding:20px;
                                            border-bottom:1px solid #E6E8EF;
                                            box-sizing:border-box;
                                        "
                                    >
                                        <div
                                            style="
                                                font-size:11px;
                                                line-height:1.4;
                                                color:#667085;
                                                text-transform:uppercase;
                                                letter-spacing:.5px;
                                            "
                                        >
                                            Venue
                                        </div>

                                        <div
                                            style="
                                                margin-top:6px;
                                                font-size:15px;
                                                line-height:1.5;
                                                font-weight:700;
                                                color:#161943;
                                            "
                                        >
                                            {{ $event->venue ?? '-' }}
                                        </div>
                                    </td>
                                </tr>

                                <tr>
                                    <td
                                        width="50%"
                                        valign="top"
                                        class="detail-column"
                                        style="
                                            width:50%;
                                            padding:20px;
                                            box-sizing:border-box;
                                        "
                                    >
                                        <div
                                            style="
                                                font-size:11px;
                                                line-height:1.4;
                                                color:#667085;
                                                text-transform:uppercase;
                                                letter-spacing:.5px;
                                            "
                                        >
                                            Date
                                        </div>

                                        <div
                                            style="
                                                margin-top:6px;
                                                font-size:15px;
                                                line-height:1.5;
                                                font-weight:700;
                                                color:#161943;
                                            "
                                        >
                                            @if($event->starts_at)
                                                {{ $event->starts_at->format('d M Y') }}

                                                @if(
                                                    $event->ends_at
                                                    && ! $event->starts_at->isSameDay($event->ends_at)
                                                )
                                                    – {{ $event->ends_at->format('d M Y') }}
                                                @endif
                                            @else
                                                -
                                            @endif
                                        </div>
                                    </td>

                                    <td
                                        width="50%"
                                        valign="top"
                                        class="detail-column"
                                        style="
                                            width:50%;
                                            padding:20px;
                                            box-sizing:border-box;
                                        "
                                    >
                                        <div
                                            style="
                                                font-size:11px;
                                                line-height:1.4;
                                                color:#667085;
                                                text-transform:uppercase;
                                                letter-spacing:.5px;
                                            "
                                        >
                                            Time
                                        </div>

                                        <div
                                            style="
                                                margin-top:6px;
                                                font-size:15px;
                                                line-height:1.5;
                                                font-weight:700;
                                                color:#161943;
                                            "
                                        >
                                            {{ $event->starts_at?->format('h:i A') ?? '-' }}
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                @endif


                {{-- ========================================================= --}}
                {{-- PRIMARY ACTION                                            --}}
                {{-- ========================================================= --}}

                @if(!empty($actionUrl) && !empty($actionLabel))
                    <tr>
                        <td
                            align="center"
                            class="email-padding"
                            style="padding:5px 45px 36px;"
                        >
                            @if(!empty($actionIntro))
                                <div
                                    style="
                                        margin-bottom:20px;
                                        font-size:15px;
                                        line-height:1.6;
                                        color:#475467;
                                    "
                                >
                                    {{ $actionIntro }}
                                </div>
                            @endif

                            <table
                                role="presentation"
                                cellspacing="0"
                                cellpadding="0"
                                border="0"
                                align="center"
                            >
                                <tr>
                                    <td
                                        align="center"
                                        bgcolor="#007AB2"
                                        style="border-radius:8px;"
                                    >
                                        <a
                                            href="{{ $actionUrl }}"
                                            target="_blank"
                                            style="
                                                display:inline-block;
                                                background:#007AB2;
                                                color:#FFFFFF;
                                                text-decoration:none;
                                                font-size:15px;
                                                line-height:1.2;
                                                font-weight:700;
                                                padding:15px 30px;
                                                border-radius:8px;
                                            "
                                        >
                                            {{ $actionLabel }}
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            @if(!empty($actionNote))
                                <div
                                    style="
                                        margin-top:18px;
                                        font-size:13px;
                                        line-height:1.6;
                                        color:#667085;
                                    "
                                >
                                    {{ $actionNote }}
                                </div>
                            @endif

                            <div
                                style="
                                    margin-top:18px;
                                    font-size:11px;
                                    line-height:1.6;
                                    color:#98A2B3;
                                    word-break:break-all;
                                "
                            >
                                If the button does not work, open this link:<br>

                                <a
                                    href="{{ $actionUrl }}"
                                    target="_blank"
                                    style="
                                        color:#007AB2;
                                        text-decoration:none;
                                    "
                                >
                                    {{ $actionUrl }}
                                </a>
                            </div>
                        </td>
                    </tr>
                @endif


                {{-- ========================================================= --}}
                {{-- SIGNATURE                                                 --}}
                {{-- ========================================================= --}}

                <tr>
                    <td
                        class="email-padding"
                        style="
                            padding:8px 45px 36px;
                            font-size:15px;
                            line-height:1.7;
                            color:#475467;
                        "
                    >
                        Thank you,<br>

                        <strong style="color:#161943;">
                            eLive Events
                        </strong>
                    </td>
                </tr>


                {{-- ========================================================= --}}
                {{-- FOOTER                                                    --}}
                {{-- ========================================================= --}}

                <tr>
                    <td
                        align="center"
                        class="email-padding"
                        style="
                            background:#161943;
                            color:#FFFFFF;
                            padding:28px 30px;
                            font-size:12px;
                            line-height:1.8;
                        "
                    >
                        <strong
                            style="
                                color:#FFFFFF;
                                font-size:14px;
                            "
                        >
                            eLive Events
                        </strong>

                        <br>

                        <span style="color:#D8DCE6;">
                            Smart Events. Seamless Experience.
                        </span>

                        <br><br>

                        <a
                            href="https://events.elive.co.tz"
                            target="_blank"
                            style="
                                color:#FF9800;
                                text-decoration:none;
                                font-weight:700;
                            "
                        >
                            events.elive.co.tz
                        </a>

                        <br>

                        <span style="color:#D8DCE6;">
                            © {{ date('Y') }} eLive Events. All rights reserved.
                        </span>
                    </td>
                </tr>

            </table>

        </td>
    </tr>
</table>

</body>
</html>
