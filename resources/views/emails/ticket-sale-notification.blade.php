@php
    $currency = strtoupper((string) ($order->currency ?: 'TZS'));
    $items = $order->items->map(fn ($item) => ($item->ticketType?->name ?: 'Ticket').' × '.(int) $item->quantity)->implode(', ');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subject }}</title>
    <style>
        @media only screen and (max-width:620px) {
            .outer { padding:0 !important; }
            .card { width:100% !important; border-radius:0 !important; }
            .pad { padding-left:20px !important; padding-right:20px !important; }
            .detail-label { width:38% !important; }
        }
    </style>
</head>
<body style="margin:0;background:#F6F8FC;color:#161943;font-family:Arial,Helvetica,sans-serif;">
    <div style="display:none;max-height:0;overflow:hidden;opacity:0;">Confirmed sale for {{ $event->name }} — order {{ $order->order_number }}</div>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="outer" style="padding:30px 10px;background:#F6F8FC;">
        <tr><td align="center">
            <table role="presentation" width="680" cellpadding="0" cellspacing="0" class="card" style="width:100%;max-width:680px;background:#FFFFFF;border:1px solid #E6E8EF;border-radius:14px;overflow:hidden;">
                <tr><td class="pad" style="padding:22px 30px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
                        <td align="left"><img src="{{ url('/eLive-Logo.png') }}" width="145" alt="eLive Events" style="display:block;width:145px;max-width:100%;height:auto;border:0;"></td>
                        <td align="right" style="font-size:12px;color:#667085;">Ticket Sale Notification</td>
                    </tr></table>
                </td></tr>
                <tr><td class="pad" align="center" style="background:#161943;padding:34px 30px;color:#FFFFFF;">
                    <div style="font-size:12px;letter-spacing:1.5px;color:#FFB74D;font-weight:bold;">NEW TICKET SALE</div>
                    <div style="font-size:28px;line-height:1.3;font-weight:bold;margin-top:10px;color:#FFFFFF;">Ticket Sale Confirmed!</div>
                    <div style="font-size:15px;line-height:1.5;margin-top:10px;color:#E7E9F0;">{{ $event->name }}</div>
                </td></tr>
                <tr><td class="pad" style="padding:30px 38px 12px;font-size:15px;line-height:1.7;color:#475467;">
                    A ticket purchase has been successfully confirmed on eLive Events.
                </td></tr>
                <tr><td class="pad" style="padding:8px 38px 22px;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #E6E8EF;border-radius:10px;">
                        @foreach ([
                            'Buyer Details' => [
                                'Full name' => filled($order->buyer_name) ? $order->buyer_name : 'Not provided',
                                'Phone number' => filled($order->buyer_phone) ? $order->buyer_phone : 'Not provided',
                                'Email address' => filled($order->buyer_email) ? $order->buyer_email : 'Not provided',
                            ],
                            'Ticket Information' => [
                                'Event' => $event->name,
                                'Order reference' => $order->order_number,
                                'Ticket category' => $items ?: 'Ticket',
                                'Quantity sold' => (string) $order->quantity,
                                'Payment status' => 'Confirmed',
                            ],
                            'Financial Summary' => [
                                'Total paid' => $currency.' '.number_format((float) $order->total, 2),
                                'Organizer net amount' => $currency.' '.number_format((float) $order->organizer_net_amount, 2),
                            ],
                        ] as $heading => $rows)
                            <tr><td colspan="2" style="padding:18px 18px 8px;color:#161943;font-size:16px;font-weight:bold;">{{ $heading }}</td></tr>
                            @foreach ($rows as $label => $value)
                                <tr>
                                    <td class="detail-label" width="40%" valign="top" style="padding:7px 12px 7px 18px;color:#667085;font-size:13px;">{{ $label }}</td>
                                    <td valign="top" style="padding:7px 18px 7px 4px;color:#161943;font-size:13px;font-weight:bold;word-break:break-word;">{{ $value }}</td>
                                </tr>
                            @endforeach
                        @endforeach
                        <tr><td colspan="2" style="height:14px;"></td></tr>
                    </table>
                </td></tr>
                <tr><td class="pad" style="padding:0 38px 22px;">
                    <div style="padding:16px;background:#F6F8FC;border-radius:8px;font-size:13px;line-height:1.65;color:#475467;">This is a confirmed ticket payment, not a settlement or payout confirmation. Organizer transfers are handled separately.</div>
                </td></tr>
                <tr><td align="center" class="pad" style="padding:0 38px 26px;">
                    <a href="{{ url('/admin') }}" style="display:inline-block;background:#007AB2;color:#FFFFFF;text-decoration:none;font-weight:bold;font-size:15px;padding:14px 25px;border-radius:8px;">Open eLive Dashboard</a>
                    <div style="font-size:12px;color:#667085;margin-top:12px;">Sign in with an authorized account to view ticket sales.</div>
                </td></tr>
                <tr><td class="pad" style="padding:5px 38px 30px;font-size:14px;line-height:1.7;color:#475467;">Thank you,<br><strong style="color:#161943;">eLive Events</strong></td></tr>
                <tr><td align="center" style="background:#161943;color:#E7E9F0;padding:27px 20px;font-size:12px;line-height:1.8;">
                    <strong style="color:#FFFFFF;font-size:15px;">eLive Events</strong><br>
                    Smart Events. Seamless Experience.<br>
                    <a href="{{ route('public.terms') }}" style="color:#FF9800;text-decoration:none;">Terms &amp; Conditions</a>
                    &nbsp;·&nbsp;
                    <a href="{{ url('/') }}" style="color:#FF9800;text-decoration:none;">events.elive.co.tz</a><br>
                    &copy; {{ date('Y') }} eLive Events. All rights reserved.
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
