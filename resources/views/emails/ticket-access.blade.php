@php
    $eventName = $event?->name ?? 'eLive Event';
    $eventStart = $event?->starts_at;
    $eventEnd = $event?->ends_at;
    $ticketCount = (int) $order->quantity;
    $currency = strtoupper((string) ($order->currency ?: 'TZS'));
@endphp

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{{ $subject }}</title>

    <style>
        @media only screen and (max-width: 620px) {
            .email-wrapper { padding: 0 !important; }
            .email-card { width: 100% !important; border-radius: 0 !important; }
            .email-padding { padding-left: 22px !important; padding-right: 22px !important; }
            .header-logo-cell,
            .header-event-cell { display: block !important; width: 100% !important; text-align: left !important; }
            .header-event-cell { padding-top: 14px !important; }
            .detail-column { display: block !important; width: 100% !important; box-sizing: border-box !important; border-right: 0 !important; }
            .hero-title { font-size: 27px !important; }
            .email-button { display: block !important; text-align: center !important; }
        }
    </style>
</head>

<body style="margin:0;padding:0;background:#F6F8FC;color:#161943;font-family:Arial,Helvetica,sans-serif;-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%;">
<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
    Your {{ $ticketCount }} ticket{{ $ticketCount === 1 ? '' : 's' }} for {{ $eventName }} {{ $ticketCount === 1 ? 'is' : 'are' }} ready.
</div>

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" class="email-wrapper" style="width:100%;margin:0;padding:30px 10px;background:#F6F8FC;">
    <tr>
        <td align="center">
            <table role="presentation" width="680" cellspacing="0" cellpadding="0" border="0" class="email-card" style="width:100%;max-width:680px;overflow:hidden;border:1px solid #E6E8EF;border-radius:14px;background:#FFFFFF;box-shadow:0 5px 25px rgba(22,25,67,.08);">
                <tr>
                    <td class="email-padding" style="padding:20px 28px;background:#FFFFFF;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">
                            <tr>
                                <td width="35%" valign="middle" align="left" class="header-logo-cell" style="width:35%;">
                                    <img src="{{ url('/eLive-Logo.png') }}" alt="eLive Events" width="145" style="display:block;width:145px;max-width:145px;height:auto;border:0;outline:none;">
                                </td>
                                <td width="65%" valign="middle" align="right" class="header-event-cell" style="width:65%;text-align:right;">
                                    <div style="font-size:14px;line-height:1.4;font-weight:700;color:#161943;">{{ $eventName }}</div>
                                    @if ($event?->venue)
                                        <div style="margin-top:3px;font-size:12px;line-height:1.45;color:#667085;">{{ $event->venue }}</div>
                                    @endif
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td align="center" class="email-padding" style="padding:34px 35px;background:#161943;color:#FFFFFF;">
                        <div style="margin-bottom:10px;font-size:12px;line-height:1.4;text-transform:uppercase;letter-spacing:2px;color:#D8DCE6;">Tickets ready</div>
                        <div class="hero-title" style="font-size:30px;line-height:1.25;font-weight:700;color:#FFFFFF;">{{ $eventName }}</div>
                        @if ($eventStart || $event?->venue)
                            <div style="margin-top:14px;font-size:14px;line-height:1.6;color:#E7E9F0;">
                                @if ($eventStart)
                                    {{ $eventStart->format('d M Y') }}
                                @endif
                                @if ($eventStart && $event?->venue)
                                    &nbsp; • &nbsp;
                                @endif
                                {{ $event?->venue }}
                            </div>
                        @endif
                    </td>
                </tr>

                <tr>
                    <td class="email-padding" style="padding:34px 45px 24px;font-size:16px;line-height:1.75;color:#475467;">
                        {!! nl2br(e($body)) !!}
                    </td>
                </tr>

                <tr>
                    <td class="email-padding" style="padding:0 45px 28px;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="width:100%;overflow:hidden;border:1px solid #E6E8EF;border-radius:12px;background:#F7FAFC;">
                            <tr>
                                <td width="50%" valign="top" class="detail-column" style="width:50%;padding:20px;border-right:1px solid #E6E8EF;border-bottom:1px solid #E6E8EF;">
                                    <div style="font-size:11px;line-height:1.4;text-transform:uppercase;letter-spacing:.5px;color:#667085;">Tickets</div>
                                    <div style="margin-top:6px;font-size:15px;line-height:1.5;font-weight:700;color:#161943;">{{ $ticketCount }}</div>
                                </td>
                                <td width="50%" valign="top" class="detail-column" style="width:50%;padding:20px;border-bottom:1px solid #E6E8EF;">
                                    <div style="font-size:11px;line-height:1.4;text-transform:uppercase;letter-spacing:.5px;color:#667085;">Venue</div>
                                    <div style="margin-top:6px;font-size:15px;line-height:1.5;font-weight:700;color:#161943;">{{ $event?->venue ?: '-' }}</div>
                                </td>
                            </tr>
                            <tr>
                                <td width="50%" valign="top" class="detail-column" style="width:50%;padding:20px;border-right:1px solid #E6E8EF;border-bottom:1px solid #E6E8EF;">
                                    <div style="font-size:11px;line-height:1.4;text-transform:uppercase;letter-spacing:.5px;color:#667085;">Date</div>
                                    <div style="margin-top:6px;font-size:15px;line-height:1.5;font-weight:700;color:#161943;">{{ $eventStart?->format('d M Y') ?? '-' }}</div>
                                </td>
                                <td width="50%" valign="top" class="detail-column" style="width:50%;padding:20px;border-bottom:1px solid #E6E8EF;">
                                    <div style="font-size:11px;line-height:1.4;text-transform:uppercase;letter-spacing:.5px;color:#667085;">Time</div>
                                    <div style="margin-top:6px;font-size:15px;line-height:1.5;font-weight:700;color:#161943;">
                                        {{ $eventStart?->format('h:i A') ?? '-' }}
                                        @if ($eventEnd)
                                            – {{ $eventEnd->format('h:i A') }}
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td width="50%" valign="top" class="detail-column" style="width:50%;padding:20px;border-right:1px solid #E6E8EF;">
                                    <div style="font-size:11px;line-height:1.4;text-transform:uppercase;letter-spacing:.5px;color:#667085;">Order</div>
                                    <div style="margin-top:6px;font-size:15px;line-height:1.5;font-weight:700;color:#161943;">{{ $order->order_number }}</div>
                                </td>
                                <td width="50%" valign="top" class="detail-column" style="width:50%;padding:20px;">
                                    <div style="font-size:11px;line-height:1.4;text-transform:uppercase;letter-spacing:.5px;color:#667085;">Amount paid</div>
                                    <div style="margin-top:6px;font-size:15px;line-height:1.5;font-weight:700;color:#161943;">{{ $currency }} {{ number_format((float) $order->total, 0) }}</div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td align="center" class="email-padding" style="padding:4px 45px 20px;">
                        <table role="presentation" cellspacing="0" cellpadding="0" border="0" align="center">
                            <tr>
                                <td align="center" bgcolor="#007AB2" style="border-radius:8px;">
                                    <a href="{{ $ticketsUrl }}" target="_blank" class="email-button" style="display:inline-block;padding:15px 30px;border-radius:8px;background:#007AB2;color:#FFFFFF;font-size:15px;line-height:1.2;font-weight:700;text-decoration:none;">View My Tickets</a>
                                </td>
                            </tr>
                        </table>
                        <div style="margin-top:18px;font-size:13px;line-height:1.6;color:#667085;">
                            Keep this link private because it provides access to your tickets and QR codes.
                        </div>
                        <div style="margin-top:14px;font-size:11px;line-height:1.6;color:#98A2B3;word-break:break-all;">
                            If the button does not work, open this link:<br>
                            <a href="{{ $ticketsUrl }}" target="_blank" style="color:#007AB2;text-decoration:none;">{{ $ticketsUrl }}</a>
                        </div>
                    </td>
                </tr>

                <tr>
                    <td class="email-padding" style="padding:8px 45px 36px;font-size:15px;line-height:1.7;color:#475467;">
                        Thank you,<br>
                        <strong style="color:#161943;">eLive Events</strong>
                    </td>
                </tr>

                <tr>
                    <td align="center" class="email-padding" style="padding:28px 30px;background:#161943;color:#FFFFFF;font-size:12px;line-height:1.8;">
                        <strong style="font-size:14px;color:#FFFFFF;">eLive Events</strong><br>
                        <span style="color:#D8DCE6;">Smart Events. Seamless Experience.</span><br><br>
                        <a href="https://events.elive.co.tz" target="_blank" style="color:#FF9800;text-decoration:none;font-weight:700;">events.elive.co.tz</a><br>
                        <span style="color:#D8DCE6;">© {{ date('Y') }} eLive Events. All rights reserved.</span>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
