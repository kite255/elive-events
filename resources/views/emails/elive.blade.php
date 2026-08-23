<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">

    <title>{{ $subject ?? 'eLive Events' }}</title>

    <style>
        @media only screen and (max-width: 620px) {
            .email-wrapper {
                padding: 15px 8px !important;
            }

            .email-container {
                width: 100% !important;
            }

            .email-padding {
                padding-left: 22px !important;
                padding-right: 22px !important;
            }

            .event-title {
                font-size: 25px !important;
            }

            .detail-column {
                display: block !important;
                width: 100% !important;
                box-sizing: border-box !important;
            }

            .email-logo {
                width: 175px !important;
                max-width: 175px !important;
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
                {{-- ELIVE HEADER                                              --}}
                {{-- ========================================================= --}}

                <tr>
                    <td
                        align="center"
                        style="
                            padding:28px 30px 24px;
                            background:#FFFFFF;
                        "
                    >
                        <img
                            src="{{ url('/eLive-Logo.png') }}"
                            alt="eLive Events"
                            width="200"
                            class="email-logo"
                            style="
                                width:200px;
                                max-width:200px;
                                height:auto;
                                display:block;
                                border:0;
                                outline:none;
                                text-decoration:none;
                            "
                        >
                    </td>
                </tr>


                {{-- ========================================================= --}}
                {{-- EMAIL TYPE / EVENT HERO                                   --}}
                {{-- ========================================================= --}}

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
                        class="email-padding"
                        style="
                            padding:38px 45px 24px;
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
                            style="
                                padding:5px 45px 36px;
                            "
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


                            {{-- Table-based button for better email compatibility --}}

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
                                        style="
                                            border-radius:8px;
                                        "
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