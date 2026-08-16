<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? 'eLive Events' }}</title>
</head>

<body style="margin:0;padding:0;background:#F7F8FC;font-family:'Creato Display',Arial,Helvetica,sans-serif;color:#161943;">

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
       style="background:#F7F8FC;padding:30px 10px;">
<tr>
<td align="center">

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
       style="max-width:680px;background:#FFFFFF;border-radius:14px;overflow:hidden;box-shadow:0 5px 25px rgba(22,25,67,.08);">

{{-- ELIVE HEADER --}}
<tr>
<td align="center" style="padding:30px 30px 24px;">
    <img
        src="{{ asset('images/elive-events-logo.png') }}"
        alt="eLive Events"
        width="220"
        style="max-width:220px;width:100%;height:auto;display:block;"
    >
</td>
</tr>

{{-- EMAIL TYPE / EVENT HERO --}}
<tr>
<td align="center"
    style="background:#161943;padding:35px 30px;color:#FFFFFF;">

    <div style="font-size:13px;text-transform:uppercase;letter-spacing:2px;opacity:.85;margin-bottom:10px;">
        {{ $emailLabel ?? 'Event Communication' }}
    </div>

    <div style="font-size:30px;line-height:1.2;font-weight:700;color:#FFFFFF;">
        {{ $event?->name ?? 'eLive Events' }}
    </div>

    @if($event?->starts_at)
        <div style="font-size:15px;margin-top:16px;opacity:.95;color:#FFFFFF;">
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

{{-- OPTIONAL IMPORTANT UPDATE ALERT --}}
@if(!empty($alertTitle) || !empty($alertMessage))
<tr>
<td style="padding:30px 45px 0;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
           style="background:#FFF8E8;border:1px solid #FFD899;border-radius:10px;">
        <tr>
            <td style="padding:18px 20px;">
                @if(!empty($alertTitle))
                    <div style="font-size:14px;font-weight:700;color:#9A5A00;margin-bottom:6px;">
                        {{ $alertTitle }}
                    </div>
                @endif

                @if(!empty($alertMessage))
                    <div style="font-size:14px;line-height:1.6;color:#6F4B00;">
                        {{ $alertMessage }}
                    </div>
                @endif
            </td>
        </tr>
    </table>
</td>
</tr>
@endif

{{-- MAIN BODY --}}
<tr>
<td style="padding:40px 45px 20px;font-size:16px;line-height:1.7;color:#161943;">

    @if($attendee)
        <div style="font-size:22px;font-weight:700;color:#161943;margin-bottom:18px;">
            Hello {{ $attendee->full_name ?? 'Attendee' }},
        </div>
    @endif

    <div style="margin-bottom:20px;color:#475467;">
        {!! nl2br(e($messageBody ?? '')) !!}
    </div>

</td>
</tr>

{{-- EVENT INFORMATION --}}
@if($event)
<tr>
<td style="padding:0 45px 30px;">

<table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"
       style="background:#F7FAFC;border:1px solid #E6E8EF;border-radius:12px;">

<tr>
<td width="50%" style="padding:20px;border-bottom:1px solid #E6E8EF;">
    <div style="font-size:11px;color:#667085;text-transform:uppercase;letter-spacing:.04em;">Category</div>
    <div style="margin-top:5px;font-weight:700;color:#161943;">
        {{ $attendee?->category?->name ?? '-' }}
    </div>
</td>

<td width="50%" style="padding:20px;border-bottom:1px solid #E6E8EF;">
    <div style="font-size:11px;color:#667085;text-transform:uppercase;letter-spacing:.04em;">Venue</div>
    <div style="margin-top:5px;font-weight:700;color:#161943;">
        {{ $event->venue ?? '-' }}
    </div>
</td>
</tr>

<tr>
<td style="padding:20px;">
    <div style="font-size:11px;color:#667085;text-transform:uppercase;letter-spacing:.04em;">Date</div>
    <div style="margin-top:5px;font-weight:700;color:#161943;">
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

<td style="padding:20px;">
    <div style="font-size:11px;color:#667085;text-transform:uppercase;letter-spacing:.04em;">Time</div>
    <div style="margin-top:5px;font-weight:700;color:#161943;">
        {{ $event->starts_at?->format('h:i A') ?? '-' }}
    </div>
</td>
</tr>

</table>

</td>
</tr>
@endif

{{-- PRIMARY ACTION --}}
@if(!empty($actionUrl) && !empty($actionLabel))
<tr>
<td align="center" style="padding:5px 45px 35px;">

    @if(!empty($actionIntro))
        <div style="margin-bottom:20px;color:#475467;">
            {{ $actionIntro }}
        </div>
    @endif

    <a
        href="{{ $actionUrl }}"
        style="display:inline-block;background:#161943;color:#FFFFFF;text-decoration:none;font-size:15px;font-weight:700;padding:15px 30px;border-radius:8px;"
    >
        {{ $actionLabel }}
    </a>

    @if(!empty($actionNote))
        <div style="margin-top:20px;font-size:14px;color:#667085;">
            {{ $actionNote }}
        </div>
    @endif

</td>
</tr>
@endif

{{-- SIGNATURE --}}
<tr>
<td style="padding:10px 45px 35px;font-size:15px;line-height:1.6;color:#475467;">
    Thank you,<br>
    <strong style="color:#161943;">eLive Events</strong>
</td>
</tr>

{{-- FOOTER --}}
<tr>
<td align="center"
    style="background:#161943;color:#FFFFFF;padding:25px 30px;font-size:12px;line-height:1.8;">

    <strong style="color:#FFFFFF;">eLive Events</strong><br>
    <span style="color:#D8DCE6;">Smart Events. Seamless Experience.</span>

    <br><br>

    <a href="https://events.elive.co.tz"
       style="color:#FF9800;text-decoration:none;font-weight:700;">
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
